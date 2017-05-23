<?php

namespace Mini\Routing;

use Mini\Routing\Assets\Router as AssetsRouter;
use Mini\Routing\Redirector;
use Mini\Routing\ResponseFactory;
use Mini\Routing\Router;
use Mini\Routing\UrlGenerator;
use Mini\Support\ServiceProvider;


class RoutingServiceProvider extends ServiceProvider
{

	/**
	 * Register the Service Provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerRouter();

		$this->registerUrlGenerator();

		$this->registerRedirector();

		$this->registerResponseFactory();

		$this->registerAssetDispatcher();
	}

	/**
	 * Register the router instance.
	 *
	 * @return void
	 */
	protected function registerRouter()
	{
		$this->app['router'] = $this->app->share(function ($app)
		{
			return new Router($app['events'], $app);
		});
	}

	/**
	 * Register the URL generator service.
	 *
	 * @return void
	 */
	protected function registerUrlGenerator()
	{
		$this->app['url'] = $this->app->share(function ($app)
		{
			$routes = $app['router']->getRoutes();

			$url = new UrlGenerator($routes, $app->rebinding('request', function($app, $request)
			{
				$app['url']->setRequest($request);
			}));

			$url->setSessionResolver(function ()
			{
				return $this->app['session'];
			});

			return $url;
		});
	}

	/**
	 * Register the Redirector service.
	 *
	 * @return void
	 */
	protected function registerRedirector()
	{
		$this->app['redirect'] = $this->app->share(function ($app)
		{
			$redirector = new Redirector($app['url']);

			if (isset($app['session.store'])) {
				$redirector->setSession($app['session.store']);
			}

			return $redirector;
		});
	}

	/**
	 * Register the response factory implementation.
	 *
	 * @return void
	 */
	protected function registerResponseFactory()
	{
		$this->app->singleton('response.factory', function ($app)
		{
			return new ResponseFactory();
		});
	}

	/**
	 * Register the Assets Dispatcher.
	 *
	 * @return void
	 */
	protected function registerAssetDispatcher()
	{
		$this->app->bindShared('asset.router', function($app)
		{
			return new AssetsRouter();
		});
	}
}
