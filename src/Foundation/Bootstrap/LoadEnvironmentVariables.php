<?php

namespace Mini\Foundation\Bootstrap;

use Mini\Config\EnvironmentVariables;
use Mini\Foundation\Application;


class LoadEnvironmentVariables
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Mini\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        $env = $app['env'];

        with(new EnvironmentVariables($app))->load($env);
    }
}
