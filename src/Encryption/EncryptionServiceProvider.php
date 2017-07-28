<?php
/**
 * EncryptionServiceProvider - Implements a Service Provider for Encryption.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 */

namespace Mini\Encryption;

use Mini\Encryption\Encrypter;
use Mini\Support\ServiceProvider;


class EncryptionServiceProvider extends ServiceProvider
{
    /**
     * Register the Service Provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bindShared('encrypter', function($app)
        {
            return new Encrypter($app['config']['app.key']);
        });
    }
}

