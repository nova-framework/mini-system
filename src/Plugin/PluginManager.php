<?php

namespace Mini\Plugin;

use Mini\Foundation\Application;
use Mini\Plugin\Repository;
use Mini\Support\Str;


class PluginManager
{
	/**
	 * @var \Mini\Foundation\Application
	 */
	protected $app;

	/**
	 * @var \Mini\Plugin\Repository
	 */
	protected $repository;


	/**
	 * Create a new Plugin Manager instance.
	 *
	 * @param Application $app
	 */
	public function __construct(Application $app, Repository $repository)
	{
		$this->app = $app;

		$this->repository = $repository;
	}

	/**
	 * Register the plugin service provider file from all plugins.
	 *
	 * @return mixed
	 */
	public function register()
	{
		$plugins = $this->repository->enabled();

		$plugins->each(function($properties)
		{
			$this->registerServiceProvider($properties);
		});
	}

	/**
	 * Register the Plugin Service Provider.
	 *
	 * @param array $properties
	 *
	 * @return void
	 *
	 * @throws \Mini\Plugin\FileMissingException
	 */
	protected function registerServiceProvider($properties)
	{
		$basename = $properties['basename'];

		$namespace = $this->resolveNamespace($properties);

		// Calculate the name of Service Provider, including the namespace.
		$serviceProvider = "{$namespace}\\Providers\\PluginServiceProvider";

		if (class_exists($serviceProvider)) {
			$this->app->register($serviceProvider);
		}
	}

	/**
	 * Resolve the correct Plugin namespace.
	 *
	 * @param array $properties
	 */
	public function resolveNamespace($properties)
	{
		if (isset($properties['namespace'])) {
			return $properties['namespace'];
		}

		return Str::studly($properties['slug']);
	}

	/**
	 * Resolve the correct plugin files path.
	 *
	 * @param array $properties
	 *
	 * @return string
	 */
	public function resolveClassPath($properties)
	{
		return $properties['path'] .'src' .DS;
	}

	/**
	 * Dynamically pass methods to the repository.
	 *
	 * @param string $method
	 * @param mixed  $arguments
	 *
	 * @return mixed
	 */
	public function __call($method, $arguments)
	{
		return call_user_func_array(array($this->repository, $method), $arguments);
	}
}
