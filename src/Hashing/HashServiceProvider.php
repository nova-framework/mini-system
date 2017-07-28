<?php
/**
 * HashServiceProvider - Implements a Service Provider for Hashing.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 */

namespace Mini\Hashing;

use Mini\Hashing\BcryptHasher;
use Mini\Support\ServiceProvider;


class HashServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the Provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;


    /**
     * Register the Service Provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bindShared('hash', function()
        {
            return new BcryptHasher();
        });
    }

    /**
     * Get the Services provided by the Provider.
     *
     * @return array
     */
    public function provides()
    {
        return array('hash');
    }

}
