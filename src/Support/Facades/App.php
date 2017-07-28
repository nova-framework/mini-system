<?php

namespace Mini\Support\Facades;

use Mini\Support\Facades\Facade;


/**
 * @see \Mini\Foundation\Application
 */
class App extends Facade
{
    /**
     * Return the Application instance.
     *
     * @return \Mini\Foundation\Application
     */
    public static function instance()
    {
        $accessor = static::getFacadeAccessor();

        return static::resolveFacadeInstance($accessor);
    }

    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'app'; }
}
