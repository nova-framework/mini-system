<?php

namespace Mini\Config;

use Mini\Config\Contracts\LoaderInterface;
use Mini\Support\NamespacedItemResolver;

use Closure;
use ArrayAccess;


class Repository extends NamespacedItemResolver implements ArrayAccess
{
	/**
	 * The loader implementation.
	 *
	 * @var \Mini\Config\Contracts\LoaderInterface
	 */
	protected $loader;

	/**
	 * The current environment.
	 *
	 * @var string
	 */
	protected $environment;

	/**
	 * All of the configuration items.
	 *
	 * @var array
	 */
	protected $items = array();

	/**
	 * All of the registered packages.
	 *
	 * @var array
	 */
	protected $packages = array();

	/**
	 * The after load callbacks for namespaces.
	 *
	 * @var array
	 */
	protected $afterLoad = array();

	/**
	 * Create a new configuration repository.
	 *
	 * @param  \Mini\Config\Contracts\LoaderInterface  $loader
	 * @param  string  $environment
	 * @return void
	 */
	public function __construct(LoaderInterface $loader, $environment)
	{
		$this->loader = $loader;

		$this->environment = $environment;
	}

	/**
	 * Determine if the given configuration value exists.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function has($key)
	{
		$default = microtime(true);

		return $this->get($key, $default) !== $default;
	}

	/**
	 * Determine if a configuration group exists.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function hasGroup($key)
	{
		list($namespace, $group, $item) = $this->parseKey($key);

		return $this->loader->exists($group, $namespace);
	}

	/**
	 * Get the specified configuration value.
	 *
	 * @param  string  $key
	 * @param  mixed   $default
	 * @return mixed
	 */
	public function get($key, $default = null)
	{
		list($namespace, $group, $item) = $this->parseKey($key);

		$collection = $this->getCollection($group, $namespace);

		$this->load($group, $namespace, $collection);

		return array_get($this->items[$collection], $item, $default);
	}

	/**
	 * Set a given configuration value.
	 *
	 * @param  string  $key
	 * @param  mixed   $value
	 * @return void
	 */
	public function set($key, $value)
	{
		list($namespace, $group, $item) = $this->parseKey($key);

		$collection = $this->getCollection($group, $namespace);

		$this->load($group, $namespace, $collection);

		if (is_null($item)) {
			$this->items[$collection] = $value;
		} else {
			array_set($this->items[$collection], $item, $value);
		}
	}

	/**
	 * Load the configuration group for the key.
	 *
	 * @param  string  $group
	 * @param  string  $namespace
	 * @param  string  $collection
	 * @return void
	 */
	protected function load($group, $namespace, $collection)
	{
		$env = $this->environment;

		if (isset($this->items[$collection])) {
			return;
		}

		$items = $this->loader->load($env, $group, $namespace);

		if (isset($this->afterLoad[$namespace])) {
			$items = $this->callAfterLoad($namespace, $group, $items);
		}

		$this->items[$collection] = $items;
	}

	/**
	 * Call the after load callback for a namespace.
	 *
	 * @param  string  $namespace
	 * @param  string  $group
	 * @param  array   $items
	 * @return array
	 */
	protected function callAfterLoad($namespace, $group, $items)
	{
		$callback = $this->afterLoad[$namespace];

		return call_user_func($callback, $this, $group, $items);
	}

	/**
	 * Parse an array of namespaced segments.
	 *
	 * @param  string  $key
	 * @return array
	 */
	protected function parseNamespacedSegments($key)
	{
		list($namespace, $item) = explode('::', $key);

		if (array_key_exists($namespace, $this->packages)) {
			return $this->parsePackageSegments($key, $namespace, $item);
		}

		return parent::parseNamespacedSegments($key);
	}

	/**
	 * Parse the segments of a package namespace.
	 *
	 * @param  string  $key
	 * @param  string  $namespace
	 * @param  string  $item
	 * @return array
	 */
	protected function parsePackageSegments($key, $namespace, $item)
	{
		$itemSegments = explode('.', $item);

		if (! $this->loader->exists($itemSegments[0], $namespace)) {
			return array($namespace, 'config', $item);
		}

		return parent::parseNamespacedSegments($key);
	}

	/**
	 * Register a Package for cascading configuration.
	 *
	 * @param  string  $package
	 * @param  string  $hint
	 * @param  string  $namespace
	 * @return void
	 */
	public function package($package, $hint, $namespace = null)
	{
		$namespace = $this->getPackageNamespace($package, $namespace);

		$this->packages[$namespace] = $package;

		$this->addNamespace($namespace, $hint);

		$this->afterLoading($namespace, function($me, $group, $items) use ($package)
		{
			$env = $me->getEnvironment();

			$loader = $me->getLoader();

			return $loader->cascadePackage($env, $package, $group, $items);
		});
	}

	/**
	 * Get the configuration namespace for a Package.
	 *
	 * @param  string  $package
	 * @param  string  $namespace
	 * @return string
	 */
	protected function getPackageNamespace($package, $namespace)
	{
		if (is_null($namespace)) {
			list($vendor, $namespace) = explode('/', $package);
		}

		return $namespace;
	}

	/**
	 * Register an after load callback for a given namespace.
	 *
	 * @param  string   $namespace
	 * @param  \Closure  $callback
	 * @return void
	 */
	public function afterLoading($namespace, Closure $callback)
	{
		$this->afterLoad[$namespace] = $callback;
	}

	/**
	 * Get the collection identifier.
	 *
	 * @param  string  $group
	 * @param  string  $namespace
	 * @return string
	 */
	protected function getCollection($group, $namespace = null)
	{
		$namespace = $namespace ?: '*';

		return $namespace .'::' .$group;
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
		$this->loader->addNamespace($namespace, $hint);
	}

	/**
	 * Returns all registered namespaces with the config
	 * loader.
	 *
	 * @return array
	 */
	public function getNamespaces()
	{
		return $this->loader->getNamespaces();
	}

	/**
	 * Get the loader implementation.
	 *
	 * @return \Mini\Config\LoaderInterface
	 */
	public function getLoader()
	{
		return $this->loader;
	}

	/**
	 * Set the loader implementation.
	 *
	 * @param  \Mini\Config\LoaderInterface  $loader
	 * @return void
	 */
	public function setLoader(LoaderInterface $loader)
	{
		$this->loader = $loader;
	}

	/**
	 * Get the current configuration environment.
	 *
	 * @return string
	 */
	public function getEnvironment()
	{
		return $this->environment;
	}

	/**
	 * Get the after load callback array.
	 *
	 * @return array
	 */
	public function getAfterLoadCallbacks()
	{
		return $this->afterLoad;
	}

	/**
	 * Get the current configuration packages.
	 *
	 * @return string
	 */
	public function getPackages()
	{
		return $this->packages;
	}

	/**
	 * Get all of the configuration items.
	 *
	 * @return array
	 */
	public function getItems()
	{
		return $this->items;
	}

	/**
	 * Determine if the given configuration option exists.
	 *
	 * @param  string  $key
	 * @return bool
	 */
	public function offsetExists($key)
	{
		return $this->has($key);
	}

	/**
	 * Get a configuration option.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function offsetGet($key)
	{
		return $this->get($key);
	}

	/**
	 * Set a configuration option.
	 *
	 * @param  string  $key
	 * @param  mixed  $value
	 * @return void
	 */
	public function offsetSet($key, $value)
	{
		$this->set($key, $value);
	}

	/**
	 * Unset a configuration option.
	 *
	 * @param  string  $key
	 * @return void
	 */
	public function offsetUnset($key)
	{
		$this->set($key, null);
	}

}
