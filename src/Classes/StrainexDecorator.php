<?php

namespace Dsone\ExceptionHandler\Classes;

use Request;
use Throwable;
use Carbon\Carbon;
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
	 * @var \Cerbero\ExceptionHandler\HandlersRepository
	 */
	protected $repository;

	/**
	 * Set the dependencies.
	 *
	 * @param \Illuminate\Contracts\Debug\ExceptionHandler $defaultHandler
	 * @param \Cerbero\ExceptionHandler\HandlersRepository $repository
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
			$referer = str_replace(['https://', 'http://'], '', $referer);
			$filterReferer = config('strainex.filters.referer', []);
			$mapReferer = [];
			if (isset($mapReferer[0])) {  // sequential array
				foreach ($filterReferer as $ref) {
					$mapReferer[$ref] = 1;
				}
			} else {  // assume map
				$mapReferer = $filterReferer;
			}

			if (isset($mapReferer[$referer])) {
				$referer = true;
			} else {
				$referer = false;
			}
		}

		$requestUrl = Request::url();
		$preg = '/' . implode('|', config('strainex.filters.url', [ '.*' ])) . '/ims';
		$urlMatched = preg_match($preg, $requestUrl, $match, PREG_UNMATCHED_AS_NULL);	

		if ($urlMatched || $referer) {
			// Block IP
			if (config('strainex.block_requests', false)) {
				$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
				if ($ip) {
					$data = [
						'ip'		=> $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null,
						'expire'	=> Carbon::now()->getTimestamp() + (config('app.env') === 'local' ? 15 : 21600),  // expiration date
						'userAgent'	=> $_SERVER['HTTP_SEC_CH_UA'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
						'country'	=> $_SERVER['HTTP_CF_IPCOUNTRY'] ?? 'Unknown',
						'url'		=> $requestUrl,
						'code'		=> $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $exception->getStatusCode() : null,
						'time'		=> Carbon::now()->getTimestamp(),
					];

					Redis::set(
						config('strainex.redis_string', 'strainex:ip:ban:') . $ip,
						serialize($data),
						'ex',  // define 4th param as seconds
						(config('app.env', 'production') === 'local' ? 15 : 21600)  // 15s in dev, 6h in non-dev
					);

					// Invoke optional callback
					$callback = config('strainex.callbacks.blocked', false);
					if ($callback && is_callable($callback)) {
						$callback($exception, $data, !$referer ? 1 : 2);
					}

					// Trigger new error, going back to $this->report, returning early because !!$strainex_abort
					abort(config('strainex.blocked_status'));
				}
			// only a filtered event without blocking
			} else {
				// Invoke optional callback
				$callback = config('strainex.callbacks.filtered', false);
				if ($callback && is_callable($callback)) {
					$callback($exception, !$referer ? 1 : 2);
				}

				// Trigger new error, going back to $this->report, returning early because !!$strainex_abort
				abort(config('strainex.filtered_status'));
			}
		} else {
			// Invoke optional callback
			$callback = config('strainex.callbacks.passed', false);
			if ($callback && is_callable($callback)) {
				$callback($exception);
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