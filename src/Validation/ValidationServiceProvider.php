<?php
/**
 * ValidationServiceProvider - Implements a Service Provider for Validation.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace Mini\Validation;

use Mini\Validation\Presence\DatabasePresenceVerifier;
use Mini\Validation\Factory;
use Mini\Validation\Translator;
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
		$this->registerTranslator();

		$this->registerPresenceVerifier();

		$this->app->bindShared('validator', function($app)
		{
			$translator = $app['validation.translator'];

			// Get a Validation Factory instance.
			$validator = new Factory($translator);

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
			$connection = $app['db']->connection();

			return new DatabasePresenceVerifier($connection);
		});
	}

	/**
	 * Register the Database Presence Verifier.
	 *
	 * @return void
	 */
	protected function registerTranslator()
	{
		$this->app->bindShared('validation.translator', function($app)
		{
			$lines = $this->app['config']->get('validation', array());

			return new Translator($lines);
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
