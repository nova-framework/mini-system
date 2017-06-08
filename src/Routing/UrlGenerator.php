<?php

namespace Mini\Routing;

use Mini\Http\Request;
use Mini\Support\Arr;
use Mini\Support\Str;

use InvalidArgumentException;


class UrlGenerator
{
	/**
	 * The route collection.
	 *
	 * @var \Mini\Routing\RouteCollection
	 */
	protected $routes;

	/**
	 * The request instance.
	 *
	 * @var \Mini\Http\Request
	 */
	protected $request;

	/**
	 * The force URL root.
	 *
	 * @var string
	 */
	protected $forcedRoot;

	/**
	 * The forced schema for URLs.
	 *
	 * @var string
	 */
	protected $forceSchema;

	/**
	 * Characters that should not be URL encoded.
	 *
	 * @var array
	 */
	protected $dontEncode = array(
		'%2F' => '/',
		'%40' => '@',
		'%3A' => ':',
		'%3B' => ';',
		'%2C' => ',',
		'%3D' => '=',
		'%2B' => '+',
		'%21' => '!',
		'%2A' => '*',
		'%7C' => '|',
	);

	/**
	 * Create a new URL Generator instance.
	 *
	 * @param  \Mini\Routing\RouteCollection  $routes
	 * @param  \Symfony\Component\HttpFoundation\Request   $request
	 * @return void
	 */
	public function __construct(RouteCollection $routes, Request $request)
	{
		$this->routes = $routes;

		$this->setRequest($request);
	}

	/**
	 * Get the full URL for the current request.
	 *
	 * @return string
	 */
	public function full()
	{
		return $this->request->fullUrl();
	}

	/**
	 * Get the current URL for the request.
	 *
	 * @return string
	 */
	public function current()
	{
		return $this->to($this->request->getPathInfo());
	}

	/**
	 * Get the URL for the previous request.
	 *
	 * @return string
	 */
	public function previous()
	{
		$referrer = $this->request->headers->get('referer');

		$url = $referrer ? $this->to($referrer) : $this->getPreviousUrlFromSession();

		return $url ?: $this->to('/');
	}

	/**
	 * Generate a absolute URL to the given path.
	 *
	 * @param  string  $path
	 * @param  mixed  $extra
	 * @param  bool|null  $secure
	 * @return string
	 */
	public function to($path, $extra = array(), $secure = null)
	{
		if ($this->isValidUrl($path)) {
			return $path;
		}

		$scheme = $this->getScheme($secure);

		$tail = implode('/', array_map('rawurlencode', (array) $extra));

		$root = $this->getRootUrl($scheme);

		return $this->trimUrl($root, $path, $tail);
	}

	/**
	 * Generate a secure, absolute URL to the given path.
	 *
	 * @param  string  $path
	 * @param  array   $parameters
	 * @return string
	 */
	public function secure($path, $parameters = array())
	{
		return $this->to($path, $parameters, true);
	}

	/**
	 * Generate a URL to an application asset.
	 *
	 * @param  string  $path
	 * @param  bool|null  $secure
	 * @return string
	 */
	public function asset($path, $secure = null)
	{
		if ($this->isValidUrl($path)) {
			return $path;
		}

		$root = $this->getRootUrl($this->getScheme($secure));

		return $this->removeIndex($root) .'/' .trim($path, '/');
	}

	/**
	 * Remove the index.php file from a path.
	 *
	 * @param  string  $root
	 * @return string
	 */
	protected function removeIndex($root)
	{
		$index = 'index.php';

		return Str::contains($root, $index) ? str_replace('/' .$index, '', $root) : $root;
	}

	/**
	 * Generate a URL to a secure asset.
	 *
	 * @param  string  $path
	 * @return string
	 */
	public function secureAsset($path)
	{
		return $this->asset($path, true);
	}

	/**
	 * Get the scheme for a raw URL.
	 *
	 * @param  bool|null  $secure
	 * @return string
	 */
	protected function getScheme($secure)
	{
		if (is_null($secure)) {
			return $this->forceSchema ?: $this->request->getScheme() .'://';
		}

		return $secure ? 'https://' : 'http://';
	}

	/**
	 * Force the schema for URLs.
	 *
	 * @param  string  $schema
	 * @return void
	 */
	public function forceSchema($schema)
	{
		$this->forceSchema = $schema .'://';
	}

	/**
	 * Get the URL to a named route.
	 *
	 * @param  string  $name
	 * @param  mixed   $parameters
	 * @param  bool  $absolute
	 * @param  \Nova\Routing\Route  $route
	 * @return string
	 *
	 * @throws \InvalidArgumentException
	 */
	public function route($name, $parameters = array(), $absolute = true, $route = null)
	{
		$route = $route ?: $this->routes->getByName($name);

		$parameters = (array) $parameters;

		if (! is_null($route)) {
			return $this->toRoute($route, $parameters, $absolute);
		}

		throw new InvalidArgumentException("Route [{$name}] not defined.");
	}

	/**
	 * Get the URL for a given route instance.
	 *
	 * @param  \Nova\Routing\Route  $route
	 * @param  array  $parameters
	 * @param  bool  $absolute
	 * @return string
	 *
	 * @throws \BadMethodCallException
	 */
	protected function toRoute($route, array $parameters, $absolute)
	{
		$domain = $this->getRouteDomain($route);

		$uri = strtr(rawurlencode($this->trimUrl(
			$root = $this->replaceRoot($route, $domain, $parameters),
			$this->replaceRouteParameters($route->uri(), $parameters)
		)), $this->dontEncode) .$this->getRouteQueryString($parameters);

		return $absolute ? $uri : '/' .ltrim(str_replace($root, '', $uri), '/');
	}

	/**
	 * Replace the parameters on the root path.
	 *
	 * @param  \Nova\Routing\Route  $route
	 * @param  string  $domain
	 * @param  array  $parameters
	 * @return string
	 */
	protected function replaceRoot($route, $domain, &$parameters)
	{
		$root = $this->getRouteRoot($route, $domain);

		return $this->replaceRouteParameters($root, $parameters);
	}

	/**
	 * Replace all of the wildcard parameters for a route path.
	 *
	 * @param  string  $path
	 * @param  array  $parameters
	 * @return string
	 */
	protected function replaceRouteParameters($path, array &$parameters)
	{
		if (count($parameters) > 0) {
			$path = preg_replace_callback('/\{.*?\}/', function($matches) use (&$parameters)
			{
				return array_shift($parameters);

			}, $this->replaceNamedParameters($path, $parameters));
		}

		return trim(preg_replace('/\{.*?\?\}/', '', $path), '/');
	}

	/**
	 * Replace all of the named parameters in the path.
	 *
	 * @param  string  $path
	 * @param  array  $parameters
	 * @return string
	 */
	protected function replaceNamedParameters($path, &$parameters)
	{
		return preg_replace_callback('/\{(.*?)\??\}/', function($matches) use (&$parameters)
		{
			$name = $matches[1];

			return isset($parameters[$name]) ? Arr::pull($parameters, $name) : $matches[0];

		}, $path);
	}

	/**
	 * Get the query string for a given route.
	 *
	 * @param  array  $parameters
	 * @return string
	 */
	protected function getRouteQueryString(array $parameters)
	{
		if (count($parameters) == 0) {
			return '';
		}

		$query = http_build_query(
			$keyed = $this->getStringParameters($parameters)
		);

		if (count($keyed) < count($parameters)) {
			$query .= '&' .implode('&', $this->getNumericParameters($parameters));
		}

		return '?' .trim($query, '&');
	}

	/**
	 * Get the string parameters from a given list.
	 *
	 * @param  array  $parameters
	 * @return array
	 */
	protected function getStringParameters(array $parameters)
	{
		return Arr::where($parameters, function($key, $value)
		{
			return is_string($key);
		});
	}

	/**
	 * Get the numeric parameters from a given list.
	 *
	 * @param  array  $parameters
	 * @return array
	 */
	protected function getNumericParameters(array $parameters)
	{
		return Arr::where($parameters, function($key, $value)
		{
			return is_numeric($key);
		});
	}

	/**
	 * Get the formatted domain for a given route.
	 *
	 * @param  \Nova\Routing\Route  $route
	 * @return string
	 */
	protected function getRouteDomain($route)
	{
		if (! is_null($domain = $route->domain())) {
			return $this->formatDomain($route);
		}
	}

	/**
	 * Format the domain and port for the route and request.
	 *
	 * @param  \Nova\Routing\Route  $route
	 * @return string
	 */
	protected function formatDomain($route)
	{
		$domain = $this->getDomainAndScheme($route);

		return $this->addPortToDomain($domain);
	}

	/**
	 * Get the domain and scheme for the route.
	 *
	 * @param  \Nova\Routing\Route  $route
	 * @return string
	 */
	protected function getDomainAndScheme($route)
	{
		return $this->getRouteScheme($route) .$route->domain();
	}

	/**
	 * Add the port to the domain if necessary.
	 *
	 * @param  string  $domain
	 * @return string
	 */
	protected function addPortToDomain($domain)
	{
		if (in_array($this->request->getPort(), array('80', '443'))) {
			return $domain;
		}

		return $domain .':' .$this->request->getPort();
	}

	/**
	 * Get the root of the route URL.
	 *
	 * @param  \Nova\Routing\Route  $route
	 * @param  string  $domain
	 * @return string
	 */
	protected function getRouteRoot($route, $domain)
	{
		$scheme = $this->getRouteScheme($route);

		return $this->getRootUrl($scheme, $domain);
	}

	/**
	 * Get the scheme for the given route.
	 *
	 * @param  \Nova\Routing\Route  $route
	 * @return string
	 */
	protected function getRouteScheme($route)
	{
		if ($route->httpOnly()) {
			return $this->getScheme(false);
		} else if ($route->httpsOnly()) {
			return $this->getScheme(true);
		}

		return $this->getScheme(null);
	}

	/**
	 * Get the URL to a controller action.
	 *
	 * @param  string  $action
	 * @param  mixed   $parameters
	 * @param  bool	$absolute
	 * @return string
	 */
	public function action($action, $parameters = array(), $absolute = true)
	{
		return $this->route($action, $parameters, $absolute, $this->routes->getByAction($action));
	}

	/**
	 * Get the base URL for the request.
	 *
	 * @param  string  $scheme
	 * @param  string  $root
	 * @return string
	 */
	protected function getRootUrl($scheme, $root = null)
	{
		if (is_null($root)) {
			$root = $this->forcedRoot ?: $this->request->root();
		}

		$start = Str::startsWith($root, 'http://') ? 'http://' : 'https://';

		return preg_replace('~' .$start .'~', $scheme, $root, 1);
	}

	/**
	 * Set the forced root URL.
	 *
	 * @param  string  $root
	 * @return void
	 */
	public function forceRootUrl($root)
	{
		$this->forcedRoot = $root;
	}

	/**
	 * Determine if the given path is a valid URL.
	 *
	 * @param  string  $path
	 * @return bool
	 */
	public function isValidUrl($path)
	{
		if (Str::startsWith($path, array('#', '//', 'mailto:', 'tel:', 'http://', 'https://'))) {
			return true;
		}

		return (filter_var($path, FILTER_VALIDATE_URL) !== false);
	}

	/**
	 * Format the given URL segments into a single URL.
	 *
	 * @param  string  $root
	 * @param  string  $path
	 * @param  string  $tail
	 * @return string
	 */
	protected function trimUrl($root, $path, $tail = '')
	{
		return trim($root .'/'.trim($path .'/' .$tail, '/'), '/');
	}

	/**
	 * Get the request instance.
	 *
	 * @return \Symfony\Component\HttpFoundation\Request
	 */
	public function getRequest()
	{
		return $this->request;
	}

	/**
	 * Set the current request instance.
	 *
	 * @param  \Mini\Http\Request  $request
	 * @return void
	 */
	public function setRequest(Request $request)
	{
		$this->request = $request;
	}

	/**
	 * Get the previous URL from the session if possible.
	 *
	 * @return string|null
	 */
	protected function getPreviousUrlFromSession()
	{
		$session = $this->getSession();

		if (! is_null($session)) {
			return $session->previousUrl();
		}
	}

	/**
	 * Get the session implementation from the resolver.
	 *
	 * @return \Mini\Session\Store|null
	 */
	protected function getSession()
	{
		if ($this->sessionResolver) {
			return call_user_func($this->sessionResolver);
		}
	}

	/**
	 * Set the session resolver for the generator.
	 *
	 * @param  callable  $sessionResolver
	 * @return $this
	 */
	public function setSessionResolver(callable $sessionResolver)
	{
		$this->sessionResolver = $sessionResolver;

		return $this;
	}
}
