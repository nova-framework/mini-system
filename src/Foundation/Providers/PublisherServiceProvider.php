<?php

namespace Mini\Foundation\Providers;

use Mini\Support\ServiceProvider;
use Mini\Foundation\Publishers\AssetPublisher;
use Mini\Foundation\Publishers\ConfigPublisher;
use Mini\Foundation\Publishers\ViewPublisher;
use Mini\Foundation\Console\AssetPublishCommand;
use Mini\Foundation\Console\ConfigPublishCommand;
use Mini\Foundation\Console\ViewPublishCommand;


class PublisherServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->registerAssetPublisher();

		$this->registerConfigPublisher();

		$this->registerViewPublisher();

		$this->commands(
			'command.asset.publish', 'command.config.publish', 'command.view.publish'
		);
	}

	/**
	 * Register the asset publisher service and command.
	 *
	 * @return void
	 */
	protected function registerAssetPublisher()
	{
		$this->registerAssetPublishCommand();

		$this->app->bindShared('asset.publisher', function($app)
		{
			$publicPath = $app['path.public'];

			$publisher = new AssetPublisher($app['files'], $publicPath);

			$publisher->setPackagePath($app['path.base'] .DS .'vendor');

			return $publisher;
		});
	}

	/**
	 * Register the asset publish console command.
	 *
	 * @return void
	 */
	protected function registerAssetPublishCommand()
	{
		$this->app->bindShared('command.asset.publish', function($app)
		{
			return new AssetPublishCommand($app['asset.router'], $app['asset.publisher']);
		});
	}

	/**
	 * Register the configuration publisher class and command.
	 *
	 * @return void
	 */
	protected function registerConfigPublisher()
	{
		$this->registerConfigPublishCommand();

		$this->app->bindShared('config.publisher', function($app)
		{
			$path = $app['path'] .DS .'Config';

			$publisher = new ConfigPublisher($app['files'], $app['config'], $path);

			return $publisher;
		});
	}

	/**
	 * Register the configuration publish console command.
	 *
	 * @return void
	 */
	protected function registerConfigPublishCommand()
	{
		$this->app->bindShared('command.config.publish', function($app)
		{
			$configPublisher = $app['config.publisher'];

			return new ConfigPublishCommand($configPublisher);
		});
	}

	/**
	 * Register the view publisher class and command.
	 *
	 * @return void
	 */
	protected function registerViewPublisher()
	{
		$this->registerViewPublishCommand();

		$this->app->bindShared('view.publisher', function($app)
		{
			$viewPath = $app['path'] .DS .'Views';

			$packagePath = $app['path.base'] .DS .'plugins';

			//
			$publisher = new ViewPublisher($app['files'], $viewPath);

			$publisher->setPackagePath($packagePath);

			return $publisher;
		});
	}

	/**
	 * Register the view publish console command.
	 *
	 * @return void
	 */
	protected function registerViewPublishCommand()
	{
		$this->app->bindShared('command.view.publish', function($app)
		{
			return new ViewPublishCommand($app['view.publisher']);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array(
			'asset.publisher', 'command.asset.publish',
			'config.publisher', 'command.config.publish',
			'view.publisher', 'command.view.publish'
		);
	}

}
