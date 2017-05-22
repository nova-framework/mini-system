<?php

namespace Mini\Routing\Assets;

use Mini\Config\Repository as ConfigRepository;
use Mini\Container\Container;
use Mini\Filesystem\Filesystem;
use Mini\Http\Request;
use Mini\Support\Facades\Response;
use Mini\Support\Arr;
use Mini\Support\Str;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;

use Carbon\Carbon;

use Closure;
use LogicException;


class Router
{
	/**
	 * The Nova Filesystem instance.
	 *
	 * @var \Mini\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * All of the registered Asset Routes.
	 *
	 * @var array
	 */
	protected $routes = array();

	/**
	 * All of the named path hints.
	 *
	 * @var array
	 */
	protected $hints = array();

	/**
	 * Whether or not the CSS and JS files are automatically compressed.
	 * @var bool
	 */
	protected $compressFiles = true;

	/**
	 * The cache control options.
	 * @var int
	 */
	protected $cacheControl = array();

	/**
	 * The currently accepted encodings for Response content compression.
	 *
	 * @var array
	 */
	protected static $algorithms = array('gzip', 'deflate');


	/**
	 * Create a new Assets Router instance.
	 *
	 * @return void
	 */
	public function __construct(Filesystem $files, ConfigRepository $config)
	{
		$this->files = $files;

		//
		$this->compressFiles = $config->get('assets.compress', true);
		$this->cacheControl  = $config->get('assets.cache', array());

		// Add the Asset Route for Plugins.
		$this->route('plugins/([^/]+)/(.*)', function (Request $request, $package, $path)
		{
			if (! is_null($namedPath = $this->findNamedPath($package))) {
				return $namedPath .DS .str_replace('/', DS, $path);
			}

			return Response::make('File Not Found', 404);
		});
	}

	/**
	 * Register a new Asset Route with the manager.
	 *
	 * @param  string  $pattern
	 * @param  \Closure  $callback
	 * @return void
	 */
	public function route($pattern, $callback)
	{
		$this->routes[$pattern] = $callback;
	}

	/**
	 * Dispatch a Assets File Response.
	 *
	 * For proper Assets serving, the file URI should be either of the following:
	 *
	 * /assets/css/style.css
	 * /plugins/blog/assets/css/style.css
	 *
	 * @return \Symfony\Component\HttpFoundation\Response|null
	 */
	public function dispatch(SymfonyRequest $request)
	{
		$response = $this->match($request);

		if ($response instanceof SymfonyResponse) {
			return $response;
		} else if (! is_null($response)) {
			return $this->serve($response, $request);
		}
	}

	/**
	 * Dispatch an URI and return the associated file path.
	 *
	 * @param  string  $uri
	 * @return string|null
	 */
	public function match(Request $request)
	{
		if (! in_array($request->method(), array('GET', 'HEAD'))) {
			return;
		}

		$uri = $request->path();

		foreach ($this->routes as $pattern => $callback) {
			if (preg_match('#^' .$pattern .'$#i', $uri, $matches)) {
				$parameters = array_slice($matches, 1);

				array_unshift($parameters, $request);

				return call_user_func_array($callback, $parameters);
			}
		}
	}

	/**
	 * Serve a File.
	 *
	 * @param string $path
	 *
	 * @return \Symfony\Component\HttpFoundation\Response
	 */
	public function serve($path, SymfonyRequest $request)
	{
		if (! file_exists($path)) {
			return Response::make('File Not Found', 404);
		} else if (! is_readable($path)) {
			return Response::make('Unauthorized Access', 403);
		}

		// Retrieve the file content type.
		$mimeType = $this->getMimeType($path);

		// Calculate the available compression algorithms.
		$algorithms = $this->getEncodingAlgorithms($request);

		// Determine if the file could be compressed.
		$compressable = (($mimeType == 'application/javascript') || Str::is('text/*', $mimeType));

		if ($this->compressFiles && ! empty($algorithms) && $compressable) {
			// Get the (first) encoding algorithm.
			$algorithm = array_shift($algorithms);

			// Retrieve the file content.
			$content = file_get_contents($path);

			// Encode the content using the specified algorithm.
			$content = $this->encodeContent($content, $algorithm);

			// Retrieve the Last-Modified information.
			$timestamp = filemtime($path);

			$modifyTime = Carbon::createFromTimestampUTC($timestamp);

			$lastModified = $modifyTime->format('D, j M Y H:i:s') .' GMT';

			// Create the custom Response instance.
			$response = Response::make($content, 200, array(
				'Content-Type'	 	=> $mimeType,
				'Content-Encoding'	=> $algorithm,
				'Last-Modified'		=> $lastModified,
			));
		} else {
			// Create a Binary File Response instance.
			$response = new BinaryFileResponse($path, 200, array(), true, 'inline', true, false);

			// Set the Content type.
			$response->headers->set('Content-Type', $mimeType);
		}

		// Setup the (browser) Cache Control.
		$ttl	= Arr::get($this->cacheControl, 'ttl', 600);
		$maxAge = Arr::get($this->cacheControl, 'maxAge', 10800);

		$sharedMaxAge = Arr::get($this->cacheControl, 'sharedMaxAge', 600);

		//
		$response->setTtl($ttl);
		$response->setMaxAge($maxAge);
		$response->setSharedMaxAge($sharedMaxAge);

		// Prepare against the Request instance.
		$response->isNotModified($request);

		return $response->prepare($request);
	}

	protected function encodeContent($content, $algorithm)
	{
		if ($algorithm == 'gzip') {
			return gzencode($content, -1, FORCE_GZIP);
		} else if ($algorithm == 'deflate') {
			return gzencode($content, -1, FORCE_DEFLATE);
		}

		throw new LogicException('Unknow encoding algorithm: ' .$algorithm);
	}

	protected function getEncodingAlgorithms(SymfonyRequest $request)
	{
		// Get the accepted encodings from the Request instance.
		$acceptEncoding = $request->headers->get('Accept-Encoding');

		if (is_null($acceptEncoding)) {
			// No encoding accepted?
			return array();
		}

		// Retrieve the accepted encoding values.
		$values = explode(',', $acceptEncoding);

		// Filter the meaningful values.
		$values = array_filter($values, function($value)
		{
			$value = trim($value);

			return ! empty($value);
		});

		return array_values(array_intersect($values, static::$algorithms));
	}

	protected function getMimeType($path)
	{
		// Even the Symfony's HTTP Foundation have troubles with the CSS and JS files?
		//
		// Hard coding the correct mime types for presently needed file extensions.

		switch ($fileExt = pathinfo($path, PATHINFO_EXTENSION)) {
			case 'css':
				return 'text/css';

			case 'js':
				return 'application/javascript';

			case 'svg':
				return 'image/svg+xml';

			default:
				break;
		}

		// Guess the path's Mime Type.
		$guesser = MimeTypeGuesser::getInstance();

		return $guesser->guess($path);
	}

	/**
	 * Get the path for a registered namespace.
	 *
	 * @param  string  $namespace
	 * @return string|null
	 */
	public function findNamedPath($namespace)
	{
		return Arr::get($this->hints, $namespace);
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

		$this->addNamespace($namespace, $hint);
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

			return Str::snake($namespace);
		}

		return $namespace;
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
		$namespace = str_replace('_', '-', $namespace);

		$this->hints[$namespace] = $hint;
	}

	/**
	 * Get a namespace hint from the router.
	 *
	 * @param  string  $namespace
	 * @param  string  $hint
	 * @return void
	 */
	public function getNamespace($namespace)
	{
		return $this->findNamedPath($namespace);
	}

	/**
	 * Returns all registered namespaces with the router.
	 *
	 * @return array
	 */
	public function getNamespaces()
	{
		return $this->hints;
	}
}
