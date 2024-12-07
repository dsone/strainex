<?php

namespace Dsone\Strainex\Classes;

use Request;
use Throwable;
use Carbon\Carbon;
use ReflectionMethod;
use Illuminate\Support\Facades\Redis;
use Illuminate\Contracts\Debug\ExceptionHandler;

/**
 * The exception handler decorator.
 *
 */
class StrainexDecorator implements ExceptionHandler
{
	static $strainex_abort = false;

	/**
	 * The default Laravel exception handler.
	 *
	 * @var \Illuminate\Contracts\Debug\ExceptionHandler
	 */
	protected $defaultHandler;

	/**
	 * The custom handlers repository.
	 *
	 * @var \Dsone\Strainex\Classes\StrainexRepository
	 */
	protected $repository;

	/**
	 * Set the dependencies.
	 *
	 * @param \Illuminate\Contracts\Debug\ExceptionHandler $defaultHandler
	 * @param \Dsone\Strainex\Classes\StrainexRepository $repository
	 */
	public function __construct(ExceptionHandler $defaultHandler, StrainexRepository $repository)
	{
		$this->defaultHandler = $defaultHandler;
		$this->repository = $repository;
	}

	/**
	 * Report or log an exception.
	 *
	 * @param \Throwable $e
	 * @return mixed
	 *
	 * @throws \Throwable
	 */
	public function report(Throwable $e)
	{
		if (self::$strainex_abort) {
			// Prevent recursion || strainex filtered this exception out
			return;
		}
		$this->filterEvent($e);

		foreach ($this->repository->getReportersByException($e) as $reporter) {
			if ($report = $reporter($e)) {
				return $report;
			}
		}

		$this->defaultHandler->report($e);
	}

	/**
	 * Checks if a needle matches (exists in) a haystack.
	 *
	 * @param	string	$needle		The needle to check.
	 * @param	Array	$haystack	Either a sequential array or a map to check.
	 * @return	boolean				True on match, false otherwise.
	 */
	private function isMatch(string $needle, Array $haystack) {
		if (isset($haystack[0])) {  // sequential array
			return in_array($needle, $haystack);
		}

		if (isset($haystack[$needle])) {  // assume map
			return true;
		}

		return false;
	}

	/**
	 * Filter out configured events by requested URL or referer.
	 * Aborts before any other exception handler will be called if exception is a filtered event.
	 *
	 * @param Throwable $exception
	 * @return void
 	*/
	private function filterEvent(Throwable $exception) {
		self::$strainex_abort = true;

		$referer = $_SERVER['HTTP_REFERER'] ?? false;
		if ($referer) {
			$referer = trim(str_replace(['https://', 'http://'], '', $referer));
			$filterReferer = config('strainex.filters.referer', []);
			$referer = $this->isMatch($referer, $filterReferer);
		}

		$userAgent = $_SERVER['HTTP_SEC_CH_UA'] ?? $_SERVER['HTTP_USER_AGENT'] ?? false;
		if ($userAgent) {
			$userAgent = strtolower(trim($userAgent));
			$filterUserAgents = config('strainex.filters.userAgents', []);
			$userAgent = $this->isMatch($userAgent, $filterUserAgents);
		}

		$requestUrl = Request::url();
		$preg = '/' . implode('|', config('strainex.filters.url', [ '_____' ])) . '/ims';
		$urlMatched = preg_match($preg, $requestUrl, $match, PREG_UNMATCHED_AS_NULL);

		if ($urlMatched || $referer || $userAgent) {
			$criteriaBits = (!$urlMatched ? 0 : 1) + (!$referer ? 0 : 2) + (!$userAgent ? 0 : 4);

			// Block IP
			if (config('strainex.block_requests', false)) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
				if ($ip) {
					$ip = config('strainex.hash_ip', false) ? crypt($ip, config('strainex.hash_salt', '')) : $ip;

					$data = [
						'ip'			=> $ip,
						'expire'		=> Carbon::now()->getTimestamp() + (config('app.env') === 'local' ? 15 : 21600),  // expiration date
						'userAgent'		=> $_SERVER['HTTP_SEC_CH_UA'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
						'country'		=> $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'Unknown',
						'url'			=> $requestUrl,
						'code'			=> $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $exception->getStatusCode() : null,
						'criteriaBits'	=> $criteriaBits,
						'time'			=> Carbon::now()->getTimestamp(),
					];

					Redis::set(
						config('strainex.redis_string', 'strainex:ip:ban:') . $ip,
						serialize($data),
						'ex',  // define 4th param as seconds
						(config('app.env', 'production') === 'local' ? 10 : config('strainex.block_timeout'))  // 10s in dev, 6h in prod
					);

					// Invoke optional callback
					$callback = config('strainex.callbacks.blocked', false);
					$this->invokeCallback($callback, $exception, $data, $criteriaBits);

					// Exit
					if (config('strainex.always_exit', false)) {
						exit(0);
					}
					// Trigger new error, going back to $this->report, returning early because !!$strainex_abort
					abort(config('strainex.blocked_status'));
				}
			// only a filtered event without blocking
			} else {
				// Invoke optional callback
				$callback = config('strainex.callbacks.filtered', false);
				$this->invokeCallback($callback, $exception, $criteriaBits);

				// Exit
				if (config('strainex.always_exit', false)) {
					exit(0);
				}
				// Trigger new error, going back to $this->report, returning early because !!$strainex_abort
				abort(config('strainex.filtered_status'));
			}
		} else {
			// Invoke optional callback
			$callback = config('strainex.callbacks.passed', false);
			$this->invokeCallback($callback, $exception);
		}
	}

	/**
	 * Invoke a callback, either a single callable or an array of class/method pairs.
	 * Supports static and non-static methods as well as callable functions.
	 *
	 * @param	mixed	$callback		Either a callable or an array of class/method pairs.
	 * @param	mixed	...$cbParams	Parameters to pass to the callback.
	 * @return	void
	 */
	private function invokeCallback($callback, ...$cbParams) {
		if (!$callback) {
			return;
		}

		if ($callback && is_callable($callback)) {
			$callback(...$cbParams);
		} else if (is_array($callback)) {
			foreach ($callback as $cb) {
				if (is_callable($cb)) {
					$cb(...$cbParams);
					continue;
				}
				if (!is_array($cb)) {
					continue;
				}
				list($class, $method) = $cb;

				if (class_exists($class)) {
					if (method_exists($class, $method)) {
						$reflection = new ReflectionMethod($class, $method);

						if ($reflection->isStatic()) {
							$class::$method(...$cbParams);
						} else {
							$instance = new $class();
							$instance->$method(...$cbParams);
						}
					}
				}
			}
		}
	}


	// ==================================
	// ===== Default implementation =====
	// ==================================
	/**
	 * Determine if the exception should be reported.
	 *
	 * @param \Throwable $e
	 * @return bool
	 */
	public function shouldReport(Throwable $e)
	{
		return $this->defaultHandler->shouldReport($e);
	}

	/**
	 * Render an exception into a response.
	 *
	 * @param \Illuminate\Http\Request  $request
	 * @param \Throwable $e
	 * @return \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\Response
	 */
	public function render($request, Throwable $e)
	{
		foreach ($this->repository->getRenderersByException($e) as $renderer) {
			if ($render = $renderer($e, $request)) {
				return $render;
			}
		}

		return $this->defaultHandler->render($request, $e);
	}

	/**
	 * Render an exception to the console.
	 *
	 * @param \Symfony\Component\Console\Output\OutputInterface $output
	 * @param \Throwable $e
	 * @return mixed
	 */
	public function renderForConsole($output, Throwable $e)
	{
		$this->defaultHandler->renderForConsole($output, $e);
	}
}