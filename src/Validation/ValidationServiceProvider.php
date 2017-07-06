<?php
/**
 * ValidationServiceProvider - Implements a Service Provider for Validation.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace Mini\Validation;

use Mini\Validation\DatabasePresenceVerifier;
use Mini\Validation\Factory;
use Mini\Support\ServiceProvider;


class ValidationServiceProvider extends ServiceProvider
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
		$this->registerPresenceVerifier();

		$this->app->bindShared('validator', function($app)
		{
			$validator = new Factory($app['config']);

			if (isset($app['validation.presence'])) {
				$presenceVerifier = $app['validation.presence'];

				$validator->setPresenceVerifier($presenceVerifier);
			}

			return $validator;
		});
	}

	/**
	 * Register the Database Presence Verifier.
	 *
	 * @return void
	 */
	protected function registerPresenceVerifier()
	{
		$this->app->bindShared('validation.presence', function($app)
		{
			return new DatabasePresenceVerifier( $app['db']);
		});
	}

	/**
	 * Get the services provided by the Provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('validator');
	}
}
