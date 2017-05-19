<?php

namespace Mini\Plugin\Providers;

use Mini\Plugin\Console\PluginListCommand;
use Mini\Plugin\Console\MakePluginCommand;
use Mini\Support\ServiceProvider;


class ConsoleServiceProvider extends ServiceProvider
{

	/**
	 * Register the application services.
	 */
	public function register()
	{
		$this->registerPluginListCommand();

		$this->registerMakePluginCommand();
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
	private function registerMakePluginCommand()
	{
		$this->app->bindShared('command.make.plugin', function ($app)
		{
			return new MakePluginCommand($app['files'], $app['plugins']);
		});

		$this->commands('command.make.plugin');
	}

}
