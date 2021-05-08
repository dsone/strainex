<?php

namespace Dsone\Strainex\Tests;

use Dsone\Strainex\Providers\StrainexServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase {
	public function setUp(): void {
		parent::setUp();

		//$this->config = require_once(__DIR__ . '/../src/config/strainex.php');
		//\Config::set('strainex', $this->config);
	}

	protected function getPackageProviders($app) {
		return [
			StrainexServiceProvider::class,
		];
	}

	protected function getEnvironmentSetUp($app) {
	}
}