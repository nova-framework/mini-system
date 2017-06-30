<?php

namespace Mini\Foundation\Providers;

use Mini\Foundation\Console\UpCommand;
use Mini\Foundation\Console\DownCommand;
use Mini\Foundation\Console\ServeCommand;
use Mini\Foundation\Console\OptimizeCommand;
use Mini\Foundation\Console\RouteListCommand;
use Mini\Foundation\Console\EventMakeCommand;
use Mini\Foundation\Console\ModelMakeCommand;
use Mini\Foundation\Console\ViewClearCommand;
use Mini\Foundation\Console\PolicyMakeCommand;
use Mini\Foundation\Console\CommandMakeCommand;
use Mini\Foundation\Console\ConsoleMakeCommand;
use Mini\Foundation\Console\EnvironmentCommand;
use Mini\Foundation\Console\KeyGenerateCommand;
use Mini\Foundation\Console\ListenerMakeCommand;
use Mini\Foundation\Console\ProviderMakeCommand;
use Mini\Foundation\Console\HandlerEventCommand;
use Mini\Foundation\Console\HandlerCommandCommand;
use Mini\Foundation\Console\VendorPublishCommand;

use Mini\Support\ServiceProvider;


class ConsoleServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = true;

	/**
	 * The service providers to be registered.
	 *
	 * @var array
	 */
	protected $providers = array(
		'Mini\Console\Scheduling\ScheduleServiceProvider',
		'Mini\Foundation\Providers\ComposerServiceProvider',
		'Mini\Foundation\Providers\PublisherServiceProvider',
	);

	/**
	 * The commands to be registered.
	 *
	 * @var array
	 */
	protected $commands = array(
		'CommandMake'		=> 'command.command.make',
		'ConsoleMake'		=> 'command.console.make',
		'EventMake'			=> 'command.event.make',
		'Down'				=> 'command.down',
		'Environment'		=> 'command.environment',
		'HandlerCommand'	=> 'command.handler.command',
		'HandlerEvent'		=> 'command.handler.event',
		'KeyGenerate'		=> 'command.key.generate',
		'ListenerMake'		=> 'command.listener.make',
		'ModelMake'			=> 'command.model.make',
		'Optimize'			=> 'command.optimize',
		'PolicyMake'		=> 'command.policy.make',
		'ProviderMake'		=> 'command.provider.make',
		'RouteList'			=> 'command.route.list',
		'Serve'				=> 'command.serve',
		'Up'				=> 'command.up',
		'VendorPublish'		=> 'command.vendor.publish',
		'ViewClear'			=> 'command.view.clear'
	);

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		foreach($this->providers as $provider) {
			$this->app->register($provider);
		}

		foreach (array_keys($this->commands) as $command) {
			$method = "register{$command}Command";

			call_user_func(array($this, $method));
		}

		$this->commands(array_values($this->commands));
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerCommandMakeCommand()
	{
		$this->app->singleton('command.command.make', function ($app) {
			return new CommandMakeCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerConsoleMakeCommand()
	{
		$this->app->singleton('command.console.make', function ($app) {
			return new ConsoleMakeCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerEventMakeCommand()
	{
		$this->app->singleton('command.event.make', function ($app) {
			return new EventMakeCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerDownCommand()
	{
		$this->app->singleton('command.down', function ()
		{
			return new DownCommand;
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerEnvironmentCommand()
	{
		$this->app->singleton('command.environment', function ()
		{
			return new EnvironmentCommand;
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerHandlerCommandCommand()
	{
		$this->app->singleton('command.handler.command', function ($app) {
			return new HandlerCommandCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerHandlerEventCommand()
	{
		$this->app->singleton('command.handler.event', function ($app) {
			return new HandlerEventCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerKeyGenerateCommand()
	{
		$this->app->singleton('command.key.generate', function ($app)
		{
			return new KeyGenerateCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerListenerMakeCommand()
	{
		$this->app->singleton('command.listener.make', function ($app) {
			return new ListenerMakeCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerModelMakeCommand()
	{
		$this->app->singleton('command.model.make', function ($app) {
			return new ModelMakeCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerOptimizeCommand()
	{
		$this->app->singleton('command.optimize', function ($app)
		{
			return new OptimizeCommand($app['composer']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerPolicyMakeCommand()
	{
		$this->app->singleton('command.policy.make', function ($app) {
			return new PolicyMakeCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerProviderMakeCommand()
	{
		$this->app->singleton('command.provider.make', function ($app) {
			return new ProviderMakeCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerRouteListCommand()
	{
		$this->app->singleton('command.route.list', function ($app)
		{
			return new RouteListCommand($app['router']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerServeCommand()
	{
		$this->app->singleton('command.serve', function ()
		{
			return new ServeCommand;
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerUpCommand()
	{
		$this->app->singleton('command.up', function ()
		{
			return new UpCommand;
		});
	}

	/**
	 * Register the vendor publish console command.
	 *
	 * @return void
	 */
	protected function registerVendorPublishCommand()
	{
		$this->app->singleton('command.vendor.publish', function ($app)
		{
			return new VendorPublishCommand($app['files']);
		});
	}

	/**
	 * Register the command.
	 *
	 * @return void
	 */
	protected function registerViewClearCommand()
	{
		$this->app->singleton('command.view.clear', function ($app) {
			return new ViewClearCommand($app['files']);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array_values($this->commands);
	}
}
