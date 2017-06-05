<?php

namespace Mini\Routing;

use Mini\Http\Request;
use Mini\Http\Response;
use Mini\Routing\Assets\Dispatcher;
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
		$this->app->bindShared('asset.dispatcher', function($app)
		{
			return new Dispatcher();
		});

		// Register the default Asset Routes to Dispatcher.
		$dispatcher = $this->app['asset.dispatcher'];

		$dispatcher->route('assets/(.*)', function (Request $request, $path) use ($dispatcher)
		{
			$path = base_path('assets') .DS .str_replace('/', DS, $path);

			return $dispatcher->serve($path, $request);
		});

		$dispatcher->route('packages/([^/]+)/(.*)', function (Request $request, $plugin, $path) use ($dispatcher)
		{
			if (! is_null($basePath = $dispatcher->findNamedPath($plugin))) {
				$path = $basePath .str_replace('/', DS, $path);

				return $dispatcher->serve($path, $request);
			}

			return new Response('File Not Found', 404);
		});
	}
}
