<?php

namespace Dsone\ExceptionHandler\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Dsone\ExceptionHandler\Classes\StrainexDecorator;
use Dsone\ExceptionHandler\Classes\StrainexRepository;

/**
 * The exception handler service provider.
 *
 */
class StrainexServiceProvider extends ServiceProvider
{
	/**
	 * Bootstrap the application events.
	 * 
	 * @return void
	 */
	public function boot()
	{
		$this->publishes([
			__DIR__.'/../config/strainex.php' => config_path('strainex.php'),
		]);

		if (!config('strainex.disable', false) && config('strainex.block_requests', false)) {
			$this->abortIfBlocked();
		}
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerRepository();
		$this->extendExceptionHandler();
	}

	/**
	 * Register the custom exception handlers repository.
	 *
	 * @return void
	 */
	private function registerRepository()
	{
		$this->app->singleton(StrainexRepository::class, StrainexRepository::class);
	}

	/**
	 * Extend the Laravel default exception handler.
	 *
	 * @return void
	 */
	private function extendExceptionHandler()
	{
		if (!config('strainex.disable', false)) {
			$this->app->extend(ExceptionHandler::class, function(ExceptionHandler $handler, $app) {
				return new StrainexDecorator($handler, $app[StrainexRepository::class]);
			});
		}
	}

	/**
	 * If blocking users is activated, this blocks the user.
	 * Or prints out how long an entity is blocked when APP_ENV is local.
	 *
	 * @return void
	 */
	private function abortIfBlocked() {
		$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? null;

		if ($ip && Redis::get(config('strainex.redis_string', 'strainex:ip:ban:') . $ip)) {
			// When in production mode -> block request entirely
			if (config('app.env') !== 'local') {
				if (config('strainex.always_exit', false)) { exit(0); }

				StrainexDecorator::$strainex_abort = true;
				abort(config('strainex.blocked_status'));
			}

			$data = unserialize(Redis::get(config('strainex.redis_string', 'strainex:ip:ban:') . $ip));
			// Otherwise, display more info
			dd(
				'Blocked for ' . ($data['expire']-Carbon::now()->getTimestamp()) . ' more second(s)'
			);
		}
	}
}