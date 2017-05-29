<?php

namespace Mini\View;

use Mini\View\Engines\EngineResolver;
use Mini\View\Engines\PhpEngine;
use Mini\View\Engines\TemplateEngine;
use Mini\View\Factory;
use Mini\View\FileViewFinder;
use Mini\View\Section;
use Mini\View\Template;
use Mini\Support\ServiceProvider;


class ViewServiceProvider extends ServiceProvider
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
		$this->registerTemplate();

		$this->registerEngineResolver();

		$this->registerViewFinder();

		$this->registerFactory();

		$this->registerSection();
	}

	/**
	 * Register the Template compiler instance.
	 *
	 * @return void
	 */
	public function registerTemplate()
	{
		$this->app->bindShared('template', function($app)
		{
			$cachePath = $app['config']['view.compiled'];

			return new Template($app['files'], $cachePath);
		});
	}

	/**
	 * Register the Engine Resolver instance.
	 *
	 * @return void
	 */
	public function registerEngineResolver()
	{
		$this->app->bindShared('view.engine.resolver', function($app)
		{
			$resolver = new EngineResolver();

			// Register the Default Engine instance.
			$resolver->register('default', function()
			{
				return new PhpEngine();
			});

			// Register the Template Engine instance.
			$resolver->register('template', function() use ($app)
			{
				return new TemplateEngine($app['template'], $app['files']);
			});

			return $resolver;
		});
	}

	/**
	 * Register the View Factory instance.
	 *
	 * @return void
	 */
	public function registerFactory()
	{
		$this->app->bindShared('view', function($app)
		{
			$resolver = $app['view.engine.resolver'];

			$finder = $app['view.finder'];

			$factory = new Factory($resolver, $finder, $app['files']);

			$factory->share('app', $app);

			return $factory;
		});
	}

	/**
	 * Register the view finder implementation.
	 *
	 * @return void
	 */
	public function registerViewFinder()
	{
		$this->app->bindShared('view.finder', function($app)
		{
			$paths = $app['config']->get('view.paths', array());

			return new FileViewFinder($app['files'], $paths);
		});
	}

	/**
	 * Register the View Factory instance.
	 *
	 * @return void
	 */
	public function registerSection()
	{
		$this->app->bindShared('view.section', function($app)
		{
			return new Section($app['view']);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array('view', 'view.engine.resolver', 'view.finder', 'template', 'section');
	}
}
