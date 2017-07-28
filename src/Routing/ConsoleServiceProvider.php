<?php

namespace Mini\Routing;

use Mini\Routing\Console\ControllerMakeCommand;
use Mini\Routing\Console\MiddlewareMakeCommand;

use Mini\Support\ServiceProvider;


class ConsoleServiceProvider extends ServiceProvider
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
        $this->app->bindShared('command.controller.make', function($app)
        {
            return new ControllerMakeCommand($app['files']);
        });

        $this->app->bindShared('command.middleware.make', function($app)
        {
            return new MiddlewareMakeCommand($app['files']);
        });

        $this->commands('command.controller.make', 'command.middleware.make');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'command.controller.make', 'command.middleware.make'
        );
    }

}
