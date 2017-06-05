<?php

namespace Mini\Foundation\Publishers;

use Mini\Config\Repository as Config;
use Mini\Filesystem\Filesystem;


class ConfigPublisher
{
	/**
	 * The Filesystem instance.
	 *
	 * @var \Mini\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * The Config Repository instance.
	 *
	 * @var \Mini\Config\Repository
	 */
	protected $config;

	/**
	 * The destination of the config files.
	 *
	 * @var string
	 */
	protected $publishPath;


	/**
	 * Create a new configuration publisher instance.
	 *
	 * @param  \Mini\Filesystem\Filesystem  $files
	 * @param  string  $publishPath
	 * @return void
	 */
	public function __construct(Filesystem $files, Config $config, $publishPath)
	{
		$this->files = $files;

		$this->config = $config;

		$this->publishPath = $publishPath;
	}

	/**
	 * Publish configuration files from a given path.
	 *
	 * @param  string  $package
	 * @param  string  $source
	 * @return bool
	 */
	public function publish($package, $source)
	{
		$destination = $this->getDestinationPath($package);

		$this->makeDestination($destination);

		return $this->files->copyDirectory($source, $destination);
	}

	/**
	 * Publish the configuration files for a package.
	 *
	 * @param  string  $package
	 * @param  string  $packagePath
	 * @return bool
	 */
	public function publishPackage($package)
	{
		$source = $this->getSource($package);

		return $this->publish($package, $source);
	}

	/**
	 * Get the source configuration directory to publish.
	 *
	 * @param  string  $package
	 * @return string
	 *
	 * @throws \InvalidArgumentException
	 */
	protected function getSource($package)
	{
		$namespaces = $this->config->getNamespaces();

		$source = isset($namespaces[$package]) ? $namespaces[$package] : null;

		if (is_null($source) || ! $this->files->isDirectory($source)) {
			throw new \InvalidArgumentException("Configuration not found.");
		}

		return $source;
	}

	/**
	 * Create the destination directory if it doesn't exist.
	 *
	 * @param  string  $destination
	 * @return void
	 */
	protected function makeDestination($destination)
	{
		if ( ! $this->files->isDirectory($destination)) {
			$this->files->makeDirectory($destination, 0777, true);
		}
	}

	/**
	 * Determine if a given package has already been published.
	 *
	 * @param  string  $package
	 * @return bool
	 */
	public function alreadyPublished($package)
	{
		$path = $this->getDestinationPath($package);

		return $this->files->isDirectory($path);
	}

	/**
	 * Get the target destination path for the configuration files.
	 *
	 * @param  string  $package
	 * @return string
	 */
	public function getDestinationPath($package)
	{
		$packages = $this->config->getPackages();

		$namespace = isset($packages[$package]) ? $packages[$package] : null;

		if (is_null($namespace)) {
			throw new \InvalidArgumentException("Configuration not found.");
		}

		return $this->publishPath .str_replace('/', DS, "/Packages/{$namespace}");
	}

}
