<?php

namespace Mini\Bus;

use Mini\Bus\Dispatcher;
use Mini\Support\ServiceProvider;


class BusServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;


	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->singleton('Mini\Bus\Dispatcher', function ($app)
		{
			return new Dispatcher($app);
		});

		$this->app->alias(
			'Mini\Bus\Dispatcher', 'Mini\Bus\Contracts\DispatcherInterface'
		);
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array(
			'Mini\Bus\Dispatcher',
			'Mini\Bus\Contracts\DispatcherInterface',
		);
	}
}
