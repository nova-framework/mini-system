<?php

namespace Mini\Foundation\Support\Providers;

use Mini\Routing\Router;
use Mini\Support\ServiceProvider;


class RouteServiceProvider extends ServiceProvider
{
	/**
	 * The Controller namespace for the application.
	 *
	 * @var string|null
	 */
	protected $namespace;


	/**
	 * Bootstrap any application services.
	 *
	 * @param  \Mini\Routing\Router  $router
	 * @return void
	 */
	public function boot(Router $router)
	{
		$this->loadRoutes();
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		//
	}

	/**
	 * Load the application routes.
	 *
	 * @return void
	 */
	protected function loadRoutes()
	{
		if (method_exists($this, 'map')) {
			call_user_func(array($this, 'map'), $this->app['router']);
		}
	}

	/**
	 * Load the standard routes file for the application.
	 *
	 * @param  string  $path
	 * @return mixed
	 */
	protected function loadRoutesFrom($path)
	{
		if (is_null($this->namespace)) {
			$router = $this->app['router'];

			return require $path;
		}

		$router->group(array('namespace' => $this->namespace), function ($router) use ($path)
		{
			require $path;
		});
	}

	/**
	 * Pass dynamic methods onto the router instance.
	 *
	 * @param  string  $method
	 * @param  array  $parameters
	 * @return mixed
	 */
	public function __call($method, $parameters)
	{
		$instance = $this->app['router'];

		return call_user_func_array(array($instance, $method), $parameters);
	}
}
