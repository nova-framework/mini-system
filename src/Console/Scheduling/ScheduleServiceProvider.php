<?php

namespace Mini\Console\Scheduling;

use Mini\Support\ServiceProvider;


class ScheduleServiceProvider extends ServiceProvider
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
        $this->commands('Mini\Console\Scheduling\ScheduleRunCommand');
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'Mini\Console\Scheduling\ScheduleRunCommand',
        );
    }
}
