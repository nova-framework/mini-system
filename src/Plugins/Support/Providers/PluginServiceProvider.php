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

}
