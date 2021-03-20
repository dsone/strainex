<?php

return [
	/**
	 * Completely disable Strainex.
	 * 
	 */
	'disable' => env('STRAINEX_DISABLE', false),

	/**
	 * List of available filters.
	 * 
	 * Currently that is referer and accessed URLs.
	 */
	'filters' => [
		/**
		 * A list of referer to filter out.
		 * Leave out any "http(s)://".
		 * 
		 * If you have hundreds or perhaps thousands of referers to filter,
		 * you might speed up things within Strainex by providing a map ("lookup table") instead of a sequential array.
		 * Sequential: [ 'A', 'B', 'C', 'D', 'E' ] - totally fine if list is not too long
		 * Map: [ 'A' => 1, 'B' => 1, 'C' => 1, 'D' => 1, 'E' => 1 ] - a lot better for large lists
		 */
		'referer' => [
			
		],
		/**
		 * RegExp usable partial URL strings.
		 * One URL string per line.
		 * Case insensitive. Will be automatically concatenated with "|".
		 */
		'url' => [
			'\.env',
			'\.git',

			'admin\.txt',
			'admins\.txt',
			'admin\/includes',
			'admin\/index\.php',
			'administrator\/index\.php',
			'admin\/view\/',

			'composer\.json',
			'config\.bak\.php',
			'debug\.xml',

			'emergency\.php',
			'execute-solution',
			'extjs\.php',

			'good\.txt',
			'install\.xml',
			'misc\/ajax\.js',

			'php_info',
			'phpinfo',
			'phptest',
			'phpunit',

			'shell\.txt',
			'toc\.json',
			'upload\.php',

			'wp-includes',
			'wp-admin',
			'wp-login',
			'wp-content',
			'wp-good\.txt',
		]
	],

	/**
	 * Optional callables that are invoked at different states.
	 * 
	 * Can be a class with a _static_ `method` in the form of
	 *   [ MyExceptionEvent:class, 'method' ].
	 * 
	 * Beware that when a second request for an IP blocked entity comes along,
	 * the invoked callback might not be able to use all of Laravel's features,
	 * since Strainex tries to leave the boostrapping asap. 
	 */
	'callbacks' => [
		/**
		 * A request was blocked, only invokable if block_requests is true.
		 * In determining the $type SEO referer takes precedence over a URL match.
		 * Even though both types might occur for a single request.
		 * 
		 * The callable gets three parameters:
		 * $exception	Throwable, the original exception
		 * $data		Array, the data that is written to Redis, including IP, HTTP_CF_IPCOUNTRY if present and user agent
		 * $type		int, 1 if URL match, 2 if referer match
		 */
		'blocked'	=> false,
		/**
		 * Almost the same as blocked, just that this is only invokable if block_requests is false.
		 * The callable gets only the original $exception and $type as parameter.
		 */
		'filtered'	=> false,
		/**
		 * The negated case of filtered and blocked basically.
		 * Most useful for debugging purposes.
		 * Does not rely on the block_requests setting.
		 */
		'passed'	=> false
	],

	/**
	 * The status code to respond with as for filtered events.
	 * Make sure to use an HTML status code that is supported by Laravel.
	 * Not supported status codes throw a "message not found" Exception, ie status code 418 is not supported.
	 * 
	 * Default: 503 - Maintenance
	 */
	'filtered_status' => env('STRAINEX_FILTERED_STATUS', 503),
	'blocked_status' => env('STRAINEX_BLOCKED_STATUS', 500),

	/**
	 * Should requests be blocked.
	 * To enable this you need to have Redis available.
	 * Blocking is done based on IP. Keep in mind that some scanners change IP and try again.
	 */
	'block_requests' => env('STRAINEX_BLOCK_REQUESTS', false),

	/**
	 * The time in seconds for how long a scanner will be blocked.
	 * If your APP_ENV is set to local (ie in dev environments),
	 * the timeout is fixed at 15 seconds for testing purposes.
	 * 
	 * Default: 6 hours (21600)
	 */
	'block_timeout' => env('STRAINEX_BLOCK_TIMEOUT', 21600),

	/**
	 * The string that is going to be used with Redis.
	 * Change this if you need a specific format.
	 */
	'redis_string' => env('STRAINEX_REDIS_STRING', 'strainex:ip:ban:'),
];