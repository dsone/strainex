# Strainex

Your website gets countless 404 SEO referer spam in your logs? Autonomous vulnerability scanners frequently and regularly scan your website for the same useless things that aren't installed? You have custom (error) logging throughout your application to catch exceptions?  
There's an absurd amount of noise for the yet bazillionth time scan of a technology you have not even installed? You are using Laravel?  
Strainex might be of service to you.

### Beware
_Strainex is not meant to make your website more secure._

If you want to make your website more secure than this is not the package you're looking for.  
In such cases a honeypot setup, fail2ban, plain DENY/ALLOW in your .htaccess or similar tools might be better suited for your use case.

## What Strainex does: reducing noise
Strainex is an attempt at reducing noise in your (custom) logging for when errors occur. By filtering out common SEO spam and scan attempts that end in a 404 most of the times in an easy way.  
It's customizable, so you can add arbitrary URL requests to "block" further requests from that one entity visiting you on a daily basis.  
Blocking here means two things:
1. Preventing any more exception handlers to run, which in turn would trigger your custom logging/handling (basically "filtering")
2. Optionally prevent any further requests from the same IP for a configurable time (proper "blocking")
  
With Strainex you can keep those logs of yours a bit more clean by reducing the noise easily by 90% that way.

### How it works
When an entity requests a URL from your Laravel website, like `example.com/wp-admin`, it usually returns a 404 in a Laravel context. Within Laravel, a 404 is an HttpException. It is thrown somewhere in your application for a not found route.
  
The default Exception handler in `app\Exceptions\Handler` is invoked at last (unless re-configured with other handlers), doing whatever you have configured in there for such cases. The entity gets to see the 404 only (or any other triggered exception code) and goes ahead to request the next URL like `example.com/vendor/phpunit`, rinse and repeat. Filling up your logs with noise.
  
Strainex does not change the normal behaviour of how Laravel handles these exceptions. Instead, it adds onto that by wrapping itself around these exceptions (404 and other HttpExceptions) via a Decorator Pattern, checks if certain configured routes were accessed, specific referer or user agents are detected and aborts itself before invoking any other exception handlers.
  
If blocking is enabled, Strainex saves the IP in a Redis instance. The next request from that IP is checked within the boot process of Laravel. If the request is from a known entity, Strainex aborts the boot process. Strainex returns a configurable response code (default 500 for blocked, 503 for filtered) or simply exits. Keeping your logs clean(er) and your app from bootstrapping any further.

## Requirements
* PHP >=7.3
* Laravel >8.*
* Redis _(optional)_
  
If you want to also block entities, you need Redis setup on your machine. 
In that case any subsequent request of a previously blocked entity is being prevented by aborting early.

## Installation

1. Add this repository to your composer json:
	```
	"repositories": [
		{ "type": "vcs", "url": "https://github.com/dsone/strainex" }
	]
	```
2. Install via 
	`composer require dsone/strainex`
3. Publish config of `Dsone\Strainex\Providers\StrainexServiceProvider` via
	`php artisan vendor:publish` 
4. Read commentary in `config/strainex.php` and edit settings for the URL and referer to filter out
5. Edit .env vars as you see fit or leave defaults as defined in the config file

## Credit
...where credit is due.
  
The Decorator Pattern around exception handling in Laravel was adapted from [cerbero90/exception-handler](https://github.com/cerbero90/exception-handler).
