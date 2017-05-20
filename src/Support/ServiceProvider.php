<?php

namespace Mini\Support;

use Mini\Support\Str;


abstract class ServiceProvider
{
	/**
	 * The application instance.
	 *
	 * @var \Mini\Foundation\Application
	 */
	protected $app;

	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;


	/**
	 * Create a new service provider instance.
	 *
	 * @param  \Mini\Foundation\Application	 $app
	 * @return void
	 */
	public function __construct($app)
	{
		$this->app = $app;
	}

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot() {}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	abstract public function register();

	/**
	 * Register the package's component namespaces.
	 *
	 * @param  string  $package
	 * @param  string  $namespace
	 * @param  string  $path
	 * @return void
	 */
	public function package($package, $namespace = null, $path = null)
	{
		$namespace = $this->getPackageNamespace($package, $namespace);

		//
		$path = $path ?: $this->guessPackagePath();

		// Determine the Package Config path.
		$config = $path .DS .'Config';

		if ($this->app['files']->isDirectory($config)) {
			$this->app['config']->package($package, $config, $namespace);
		}

		// Determine the Package Language path.
		$language = $path .DS .'Language';

		if ($this->app['files']->isDirectory($language)) {
			$this->app['language']->package($package, $language, $namespace);
		}

		// Determine the Package Views path.
		$views = $path .DS .'Views';

		if ($this->app['files']->isDirectory($views)) {
			$this->app['view']->addNamespace($package, $views);
		}
	}

	/**
	 * Guess the package path for the provider.
	 *
	 * @return string
	 */
	public function guessPackagePath()
	{
		$reflection = new ReflectionClass($this);

		$path = $reflection->getFileName();

		return realpath(dirname($path) .'/../');
	}

	/**
	 * Determine the namespace for a package.
	 *
	 * @param  string  $package
	 * @param  string  $namespace
	 * @return string
	 */
	protected function getPackageNamespace($package, $namespace)
	{
		if (is_null($namespace)) {
			list(, $namespace) = array_pad(explode('/', $package, 2), 2, $package);

			return Str::snake($namespace);
		}

		return $namespace;
	}

	/**
	 * Register the package's custom Forge commands.
	 *
	 * @param  array  $commands
	 * @return void
	 */
	public function commands($commands)
	{
		$commands = is_array($commands) ? $commands : func_get_args();

		$this->app['events']->listen('forge.start', function($forge) use ($commands)
		{
			$forge->resolveCommands($commands);
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}

	/**
	 * Determine if the provider is deferred.
	 *
	 * @return bool
	 */
	public function isDeferred()
	{
		return $this->defer;
	}
}
