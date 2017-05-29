<?php

namespace Mini\Plugins\Support\Providers;

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
	 * The event handler mappings for the application.
	 *
	 * @var array
	 */
	protected $listen = array();

	/**
	 * The subscriber classes to register.
	 *
	 * @var array
	 */
	protected $subscribe = array();

	/**
	 * The policy mappings for the application.
	 *
	 * @var array
	 */
	protected $policies = array();


	/**
	 * Register the application's event listeners.
	 *
	 * @return void
	 */
	public function boot()
	{
		$events = $this->app['events'];

		foreach ($this->listen as $event => $listeners) {
			foreach ($listeners as $listener) {
				$events->listen($event, $listener);
			}
		}

		foreach ($this->subscribe as $subscriber) {
			$events->subscribe($subscriber);
		}
	}

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

	/**
	 * Bootstrap the plugin from the specified file.
	 *
	 * @param  string  $path
	 * @return mixed
	 */
	protected function bootstrapFrom($path)
	{
		$app = $this->app;

		return require $path;
	}

	/**
	 * Register the application's policies.
	 *
	 * @param  \Mini\Auth\Contracts\Access\GateInterface  $gate
	 * @return void
	 */
	public function registerPolicies(Gate $gate)
	{
		foreach ($this->policies as $key => $value) {
			$gate->policy($key, $value);
		}
	}

	/**
	 * Register any additional module middleware.
	 *
	 * @param  array|string  $middleware
	 * @return void
	 */
	protected function addMiddleware($middleware)
	{
		$middlewares = is_array($middleware) ? $middleware : func_get_args();

		//
		$kernel = $this->app['Mini\Contracts\Http\KernelInterface'];

		foreach ($middlewares as $middleware) {
			$kernel->pushMiddleware($middleware);
		}
	}

	/**
	 * Get the events and handlers.
	 *
	 * @return array
	 */
	public function listens()
	{
		return $this->listen;
	}
}
