<?php

namespace Mini\Plugin\Providers;

use Mini\Plugin\Console\PluginListCommand;
use Mini\Plugin\Console\PluginMakeCommand;
use Mini\Support\ServiceProvider;


class ConsoleServiceProvider extends ServiceProvider
{

	/**
	 * Register the application services.
	 */
	public function register()
	{
		$this->registerPluginListCommand();

		$this->registerPluginMakeCommand();
	}

	/**
	 * Register the module:list command.
	 */
	protected function registerPluginListCommand()
	{
		$this->app->singleton('command.plugin.list', function ($app)
		{
			return new PluginListCommand($app['plugins']);
		});

		$this->commands('command.plugin.list');
	}

	/**
	 * Register the make:plugin command.
	 */
	private function registerPluginMakeCommand()
	{
		$this->app->bindShared('command.make.plugin', function ($app)
		{
			return new PluginMakeCommand($app['files'], $app['plugins']);
		});

		$this->commands('command.make.plugin');
	}

}
