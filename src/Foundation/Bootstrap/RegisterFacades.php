<?php

namespace Mini\Foundation\Bootstrap;

use Mini\Foundation\Application;
use Mini\Foundation\AliasLoader;
use Mini\Support\Facades\Facade;


class RegisterFacades
{
    /**
     * Bootstrap the given application.
     *
     * @param  \Mini\Foundation\Application  $app
     * @return void
     */
    public function bootstrap(Application $app)
    {
        Facade::clearResolvedInstances();

        Facade::setFacadeApplication($app);

        //
        $aliases = $app['config']->get('app.aliases', array());

        AliasLoader::getInstance($aliases)->register();
    }
}
