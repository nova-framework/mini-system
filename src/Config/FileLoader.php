<?php

namespace Mini\Config;

use Mini\Config\Contracts\LoaderInterface;
use Mini\Filesystem\Filesystem;


class FileLoader implements LoaderInterface
{
	/**
	 * The filesystem instance.
	 *
	 * @var \Mini\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * The default configuration path.
	 *
	 * @var string
	 */
	protected $defaultPath;

	/**
	 * All of the named path hints.
	 *
	 * @var array
	 */
	protected $hints = array();

	/**
	 * A cache of whether namespaces and groups exists.
	 *
	 * @var array
	 */
	protected $exists = array();


	/**
	 * Create a new file configuration loader.
	 *
	 * @param  \Mini\Filesystem\Filesystem  $files
	 * @param  string  $defaultPath
	 * @return void
	 */
	public function __construct(Filesystem $files, $defaultPath)
	{
		$this->files = $files;

		$this->defaultPath = $defaultPath;
	}

	/**
	 * Load the given configuration group.
	 *
	 * @param  string  $environment
	 * @param  string  $group
	 * @param  string  $namespace
	 * @return array
	 */
	public function load($environment, $group, $namespace = null)
	{
		$items = array();

		$path = $this->getPath($namespace);

		if (is_null($path)) {
			return $items;
		}

		$group = ucfirst($group);

		$file = "{$path}/{$group}.php";

		if ($this->files->exists($file)) {
			$items = $this->getRequire($file);
		}

		$environment = ucfirst($environment);

		$file = "{$path}/{$environment}/{$group}.php";

		if ($this->files->exists($file)) {
			$items = $this->mergeEnvironment($items, $file);
		}

		return $items;
	}

	/**
	 * Merge the items in the given file into the items.
	 *
	 * @param  array   $items
	 * @param  string  $file
	 * @return array
	 */
	protected function mergeEnvironment(array $items, $file)
	{
		return array_replace_recursive($items, $this->getRequire($file));
	}

	/**
	 * Determine if the given group exists.
	 *
	 * @param  string  $group
	 * @param  string  $namespace
	 * @return bool
	 */
	public function exists($group, $namespace = null)
	{
		$key = $group .$namespace;

		//
		$group = ucfirst($group);

		if (isset($this->exists[$key])) {
			return $this->exists[$key];
		}

		$path = $this->getPath($namespace);

		if (is_null($path)) {
			return $this->exists[$key] = false;
		}

		$file = "{$path}/{$group}.php";

		$exists = $this->files->exists($file);

		return $this->exists[$key] = $exists;
	}

	/**
	 * Apply any cascades to an array of Package options.
	 *
	 * @param  string  $env
	 * @param  string  $package
	 * @param  string  $group
	 * @param  array   $items
	 * @return array
	 */
	public function cascadePackage($env, $package, $group, $items)
	{
		$group = ucfirst($group);

		$file = str_replace('/', DS, "Packages/{$package}/{$group}.php");

		if ($this->files->exists($path = $this->defaultPath .DS .$file)) {
			$items = array_merge(
				$items, $this->getRequire($path)
			);
		}

		$path = $this->getPackagePath($env, $package, $group);

		if ($this->files->exists($path)) {
			$items = array_merge(
				$items, $this->getRequire($path)
			);
		}

		return $items;
	}

	/**
	 * Get the Package path for an environment and group.
	 *
	 * @param  string  $env
	 * @param  string  $package
	 * @param  string  $group
	 * @return string
	 */
	protected function getPackagePath($env, $package, $group)
	{
		$file = str_replace('/', DS, "Packages/{$package}/{$env}/{$group}.php");

		return $this->defaultPath .DS .$file;
	}

	/**
	 * Get the configuration path for a namespace.
	 *
	 * @param  string  $namespace
	 * @return string
	 */
	protected function getPath($namespace)
	{
		if (is_null($namespace)) {
			return $this->defaultPath;
		} elseif (isset($this->hints[$namespace])) {
			return $this->hints[$namespace];
		}
	}

	/**
	 * Add a new namespace to the loader.
	 *
	 * @param  string  $namespace
	 * @param  string  $hint
	 * @return void
	 */
	public function addNamespace($namespace, $hint)
	{
		$this->hints[$namespace] = $hint;
	}

	/**
	 * Returns all registered namespaces with the config
	 * loader.
	 *
	 * @return array
	 */
	public function getNamespaces()
	{
		return $this->hints;
	}

	/**
	 * Get a file's contents by requiring it.
	 *
	 * @param  string  $path
	 * @return mixed
	 */
	protected function getRequire($path)
	{
		return $this->files->getRequire($path);
	}

	/**
	 * Get the Filesystem instance.
	 *
	 * @return \Mini\Filesystem\Filesystem
	 */
	public function getFilesystem()
	{
		return $this->files;
	}

}
