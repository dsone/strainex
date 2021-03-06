# Strainex

Your website gets countless SEO referer spam in your logs?  
Autonomous vulnerability scanners frequently and regularly scan your website for the same useless things?  
You have custom (error) logging throughout your application?  
There's an absurd amount of noise for the yet bazillionth time scan of a Wordpress vulnerability even though you have no Wordpress installed?  
All you try to do is get those recurring bugs fixed that legit clients produce but you have to wade through thousands of lines of logs?

## Reducing noise
Strainex is not meant to make your website more secure.  
But Strainex is an attempt at reducing that noise in your (custom) logging by filtering out the most common attemps.  
It's customizable so you can add that one scanner that visits you on a daily basis with new IPs by blacklisting specific URLs, like "wp-content", or ".env" and abort early.  
With Strainex you can keep those logs of yours a bit more clean by reducing the noise by easily 90%.

## How it works
When an entity requests a URL from your Laravel website, like `example.com/wp-admin`, it usually returns a 404.  
Within Laravel, a 404 is an HttpException. It is thrown somewhere in your application for a not found route.  
The default Exception handler in `app\Exceptions\Handler` is invoked at last (unless re-configured with other handlers), does your logging, sends mails or whatever you have configured in there.  
The entity gets to see only the 404 and goes ahead to request the next URL like `example.com/vendor/phpunit`, rinse and repeat.
  
Strainex does not change the normal behaviour of how Laravel handles the exceptions. Instead, it adds onto that by intercepting those 404, checks if certain configured routes were accessed or specific referer are detected and aborts itself before invoking any other exception handlers.
  
If blocking is enabled, the next request is checked within the boot process of Laravel, if the entity is known to be blocked (there's a Redis entry for that entity's IP), it aborts before any new exception is thrown, keeping your logs clean.

## Requirements
* Laravel 8.*
* Redis _(optional)_
  
If you want to also block visitors, you need Redis setup on your machine. 
In that case any subsequent visit of a previously blocked entity is being prevented by aborting early.

## Installation

1. `composer require dsone/strainex`  
2. Publish config of `Dsone\ExceptionHandler\Providers\StrainexServiceProvider` via  
	`php artisan vendor:publish` 