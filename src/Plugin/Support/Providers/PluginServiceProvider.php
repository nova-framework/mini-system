<?php

namespace Mini\Plugin\Support\Providers;

use Mini\Auth\Contracts\Access\GateInterface as Gate;
use Mini\Support\ServiceProvider;


class PluginServiceProvider extends ServiceProvider
{
	/**
	 * The provider class names.
	 *
	 * @var array
	 */
	protected $providers = array();


	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		foreach ($this->providers as $provider) {
			$this->app->register($provider);
		}
	}
}
