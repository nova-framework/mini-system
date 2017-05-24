<?php

namespace Mini\Foundation;

use Mini\Config\FileLoader;
use Mini\Container\Container;
use Mini\Events\EventServiceProvider;
use Mini\Filesystem\Filesystem;
use Mini\Foundation\EnvironmentDetector;
use Mini\Foundation\ProviderRepository;
use Mini\Http\Request;
use Mini\Routing\RoutingServiceProvider;
use Mini\Support\Arr;
use Mini\Support\ServiceProvider;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use Closure;


class Application extends Container
{
	/**
	 * The Mini-me framework version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.0';

	/**
	 * Indicates if the application has "booted".
	 *
	 * @var bool
	 */
	protected $booted = false;

	/**
	 * Indicates if the application has been bootstrapped before.
	 *
	 * @var bool
	 */
	protected $hasBeenBootstrapped = false;

	/**
	 * The array of booting callbacks.
	 *
	 * @var array
	 */
	protected $bootingCallbacks = array();

	/**
	 * The array of booted callbacks.
	 *
	 * @var array
	 */
	protected $bootedCallbacks = array();

	/**
	 * The array of finish callbacks.
	 *
	 * @var array
	 */
	protected $terminatingCallbacks = array();

	/**
	 * All of the registered service providers.
	 *
	 * @var array
	 */
	protected $serviceProviders = array();

	/**
	 * The names of the loaded service providers.
	 *
	 * @var array
	 */
	protected $loadedProviders = array();

	/**
	 * The deferred services and their providers.
	 *
	 * @var array
	 */
	protected $deferredServices = array();


	/**
	 * Create a new Nova application instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->registerBaseBindings();

		$this->registerBaseServiceProviders();

		$this->registerCoreContainerAliases();
	}

	/**
	 * Get the version number of the application.
	 *
	 * @return string
	 */
	public function version()
	{
		return static::VERSION;
	}

	/**
	 * Register the basic bindings into the container.
	 *
	 * @return void
	 */
	protected function registerBaseBindings()
	{
		static::setInstance($this);

		$this->instance('app', $this);

		$this->instance('Mini\Container\Container', $this);
	}

	/**
	 * Register all of the base service providers.
	 *
	 * @return void
	 */
	protected function registerBaseServiceProviders()
	{
		$this->register(new EventServiceProvider($this));

		$this->register(new RoutingServiceProvider($this));
	}

	/**
	 * Run the given array of bootstrap classes.
	 *
	 * @param  array  $bootstrappers
	 * @return void
	 */
	public function bootstrapWith(array $bootstrappers)
	{
		$this->hasBeenBootstrapped = true;

		foreach ($bootstrappers as $bootstrapper) {
			$this->make($bootstrapper)->bootstrap($this);
		}
	}

	/**
	 * Determine if the application has been bootstrapped before.
	 *
	 * @return bool
	 */
	public function hasBeenBootstrapped()
	{
		return $this->hasBeenBootstrapped;
	}

	/**
	 * Bind the installation paths to the application.
	 *
	 * @param  array  $paths
	 * @return void
	 */
	public function bindInstallPaths(array $paths)
	{
		$this->instance('path', realpath($paths['app']));

		//
		$paths = Arr::except($paths, array('app'));

		foreach ($paths as $key => $value) {
			$this->instance("path.{$key}", realpath($value));
		}
	}

	/**
	 * Get or check the current application environment.
	 *
	 * @param  mixed
	 * @return string
	 */
	public function environment()
	{
		if (count(func_get_args()) > 0) {
			return in_array($this['env'], func_get_args());
		}

		return $this['env'];
	}

	/**
	 * Determine if application is in local environment.
	 *
	 * @return bool
	 */
	public function isLocal()
	{
		return $this['env'] == 'local';
	}

	/**
	 * Detect the application's current environment.
	 *
	 * @param  array|string  $envs
	 * @return string
	 */
	public function detectEnvironment($envs)
	{
		$args = isset($_SERVER['argv']) ? $_SERVER['argv'] : null;

		return $this['env'] = (new EnvironmentDetector())->detect($envs, $args);
	}

	/**
	 * Determine if we are running in the console.
	 *
	 * @return bool
	 */
	public function runningInConsole()
	{
		return php_sapi_name() == 'cli';
	}

	/**
	 * Register all of the configured providers..
	 *
	 * @return void
	 */
	public function registerConfiguredProviders()
	{
		$config = $this->make('config');

		with(new ProviderRepository($this, $config['app.manifest']))
			->load($config['app.providers']);
	}

	/**
	 * Register a service provider with the application.
	 *
	 * @param  \Mini\Support\ServiceProvider|string  $provider
	 * @param  array  $options
	 * @param  bool  $force
	 * @return \Mini\Support\ServiceProvider
	 */
	public function register($provider, $options = array(), $force = false)
	{
		if (! is_null($registered = $this->getRegistered($provider)) && ! $force) {
			return $registered;
		}

		if (is_string($provider)) {
			$provider = $this->resolveProviderClass($provider);
		}

		$provider->register();

		foreach ($options as $key => $value) {
			$this[$key] = $value;
		}

		$this->markAsRegistered($provider);

		if ($this->booted) {
			$this->bootProvider($provider);
		}

		return $provider;
	}

	/**
	 * Get the registered service provider instnace if it exists.
	 *
	 * @param  \Mini\Support\ServiceProvider|string  $provider
	 * @return \Mini\Support\ServiceProvider|null
	 */
	public function getRegistered($provider)
	{
		$name = is_string($provider) ? $provider : get_class($provider);

		if (array_key_exists($name, $this->loadedProviders)) {
			return Arr::first($this->serviceProviders, function($key, $value) use ($name)
			{
				return (get_class($value) == $name);
			});
		}
	}

	/**
	 * Resolve a service provider instance from the class name.
	 *
	 * @param  string  $provider
	 * @return \Mini\Support\ServiceProvider
	 */
	public function resolveProviderClass($provider)
	{
		return new $provider($this);
	}

	/**
	 * Mark the given provider as registered.
	 *
	 * @param  \Mini\Support\ServiceProvider
	 * @return void
	 */
	protected function markAsRegistered($provider)
	{
		$className = get_class($provider);

		$this->serviceProviders[] = $provider;

		$this->loadedProviders[$className] = true;
	}

	/**
	 * Load and boot all of the remaining deferred providers.
	 *
	 * @return void
	 */
	public function loadDeferredProviders()
	{
		foreach ($this->deferredServices as $service => $provider) {
			$this->loadDeferredProvider($service);
		}

		$this->deferredServices = array();
	}

	/**
	 * Load the provider for a deferred service.
	 *
	 * @param  string  $service
	 * @return void
	 */
	protected function loadDeferredProvider($service)
	{
		$provider = $this->deferredServices[$service];

		if (! isset($this->loadedProviders[$provider])) {
			$this->registerDeferredProvider($provider, $service);
		}
	}

	/**
	 * Register a deffered provider and service.
	 *
	 * @param  string  $provider
	 * @param  string  $service
	 * @return void
	 */
	public function registerDeferredProvider($provider, $service = null)
	{
		if (! is_null($service)) {
			unset($this->deferredServices[$service]);
		}

		$this->register($instance = new $provider($this));

		if (! $this->booted) {
			$this->booting(function() use ($instance)
			{
				$this->bootProvider($instance);
			});
		}
	}

	/**
	 * Resolve the given type from the container.
	 *
	 * (Overriding \Mini\Container\Container::make)
	 *
	 * @param  string  $abstract
	 * @param  array  $parameters
	 * @return mixed
	 */
	public function make($abstract)
	{
		$abstract = $this->getAlias($abstract);

		if (isset($this->deferredServices[$abstract])) {
			$this->loadDeferredProvider($abstract);
		}

		return parent::make($abstract);
	}

	/**
	 * Determine if the given abstract type has been bound.
	 *
	 * (Overriding Container::bound)
	 *
	 * @param  string  $abstract
	 * @return bool
	 */
	public function bound($abstract)
	{
		return isset($this->deferredServices[$abstract]) || parent::bound($abstract);
	}

	/**
	 * "Extend" an abstract type in the container.
	 *
	 * (Overriding Container::extend)
	 *
	 * @param  string   $abstract
	 * @param  \Closure  $closure
	 * @return void
	 *
	 * @throws \InvalidArgumentException
	 */
	public function extend($abstract, Closure $closure)
	{
		$abstract = $this->getAlias($abstract);

		if (isset($this->deferredServices[$abstract])) {
			$this->loadDeferredProvider($abstract);
		}

		return parent::extend($abstract, $closure);
	}

	/**
	 * Register a "finish" application filter.
	 *
	 * @param  \Closure|string  $callback
	 * @return void
	 */
	public function finish($callback)
	{
		$this->finishCallbacks[] = $callback;
	}

	/**
	 * Determine if the application has booted.
	 *
	 * @return bool
	 */
	public function isBooted()
	{
		return $this->booted;
	}

	/**
	 * Boot the application's service providers.
	 *
	 * @return void
	 */
	public function boot()
	{
		if ($this->booted) {
			return;
		}

		array_walk($this->serviceProviders, function($provider)
		{
			$this->bootProvider($provider);
		});

		// Boot the Application.
		$this->fireAppCallbacks($this->bootingCallbacks);

		$this->booted = true;

		$this->fireAppCallbacks($this->bootedCallbacks);
	}

	/**
	 * Boot the given service provider.
	 *
	 * @param  \Mini\Support\ServiceProvider  $provider
	 * @return mixed
	 */
	protected function bootProvider(ServiceProvider $provider)
	{
		if (method_exists($provider, 'boot')) {
			return $this->call(array($provider, 'boot'));
		}
	}

	/**
	 * Register a new boot listener.
	 *
	 * @param  mixed  $callback
	 * @return void
	 */
	public function booting($callback)
	{
		$this->bootingCallbacks[] = $callback;
	}

	/**
	 * Register a new "booted" listener.
	 *
	 * @param  mixed  $callback
	 * @return void
	 */
	public function booted($callback)
	{
		$this->bootedCallbacks[] = $callback;

		if ($this->booted) {
			$this->fireAppCallbacks(array($callback));
		}
	}

	/**
	 * Determine if the application is currently down for maintenance.
	 *
	 * @return bool
	 */
	public function isDownForMaintenance()
	{
		return file_exists($this['path.storage'] .DS .'down');
	}

	/**
	 * Throw an HttpException with the given data.
	 *
	 * @param  int	 $code
	 * @param  string  $message
	 * @param  array   $headers
	 * @return void
	 *
	 * @throws \Symfony\Component\HttpKernel\Exception\HttpException
	 */
	public function abort($code, $message = '', array $headers = array())
	{
		if ($code == 404) {
			throw new NotFoundHttpException($message);
		}

		throw new HttpException($code, $message, null, $headers);
	}

	/**
	 * Register a terminating callback with the application.
	 *
	 * @param  \Closure  $callback
	 * @return $this
	 */
	public function terminating(Closure $callback)
	{
		$this->terminatingCallbacks[] = $callback;

		return $this;
	}

	/**
	 * Call the "terminating" callbacks assigned to the application.
	*
	 * @return void
	 */
	public function terminate()
	{
		foreach ($this->terminatingCallbacks as $callback) {
			call_user_func($callback);
		}
	}

	/**
	 * Call the booting callbacks for the application.
	 *
	 * @param  array  $callbacks
	 * @return void
	 */
	protected function fireAppCallbacks(array $callbacks)
	{
		foreach ($callbacks as $callback) {
			call_user_func($callback, $this);
		}
	}

	/**
	 * Set the application's deferred services.
	 *
	 * @param  array  $services
	 * @return void
	 */
	public function setDeferredServices(array $services)
	{
		$this->deferredServices = $services;
	}

	/**
	 * Get the current application locale.
	 *
	 * @return string
	 */
	public function getLocale()
	{
		return $this['config']->get('app.locale');
	}

	/**
	 * Set the current application locale.
	 *
	 * @param  string  $locale
	 * @return void
	 */
	public function setLocale($locale)
	{
		$this['config']->set('app.locale', $locale);

		$this['language']->setLocale($locale);

		$this['events']->fire('locale.changed', array($locale));
	}

	/**
	 * Register the core class aliases in the container.
	 *
	 * @return void
	 */
	public function registerCoreContainerAliases()
	{
		$aliases = array(
			'app'			=> array('Mini\Foundation\Application', 'Mini\Container\Container'),
			'asset.router'	=> 'Mini\Routing\Assets\Router',
			'cache'			=> 'Mini\Cache\CacheManager',
			'cache.store'	=> 'Mini\Cache\Repository',
			'gate'			=> 'Mini\Auth\Contracts\Access\GateInterface',
			'log'			=> array('Mini\Log\Writter', 'Psr\Log\LoggerInterface'),
			'config'		=> 'Mini\Config\Repository',
			'cookie'		=> 'Mini\Cookie\CookieJar',
			'encrypter'	 	=> 'Mini\Encryption\Encrypter',
			'events'		=> 'Mini\Events\Dispatcher',
			'files'			=> 'Mini\Filesystem\Filesystem',
			'redirect'		=> 'Mini\Routing\Redirector',
			'request'		=> 'Mini\Http\Request',
			'router'		=> 'Mini\Routing\Router',
			'session'		=> 'Mini\Session\SessionManager',
			'session.store'	=> 'Mini\Session\Store',
			'url'			=> 'Mini\Routing\UrlGenerator',
			'validator'		=> 'Mini\Validation\Factory',
			'view'			=> 'Mini\View\Factory',
		);

		foreach ($aliases as $key => $aliases) {
			foreach ((array) $aliases as $alias) {
				$this->alias($key, $alias);
			}
		}
	}

	/**
	 * Get the configuration loader instance.
	 *
	 * @return \Mini\Config\LoaderInterface
	 */
	public function getConfigLoader()
	{
		return new FileLoader(new Filesystem, $this['path'] .DS .'Config');
	}

	/**
	 * Get the used kernel object.
	 *
	 * @return \Nova\Console\Contracts\KernelInterface|\Nova\Http\Contracts\KernelInterface
	 */
	protected function getKernel()
	{
		$kernelInterface = $this->runningInConsole()
			? 'Nova\Console\Contracts\KernelInterface'
			: 'Nova\Http\Contracts\KernelInterface';

		return $this->make($kernelInterface);
	}
}
