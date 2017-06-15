<?php

namespace Mini\Routing;

use Mini\Container\Container;
use Mini\Http\Request;
use Mini\Routing\CompiledRoute;
use Mini\Routing\RouteCompiler;
use Mini\Support\Arr;

use Closure;


class Route
{
	/**
	 * The URI pattern the route responds to.
	 *
	 * @var string
	 */
	protected $uri;

	/**
	 * Supported HTTP methods.
	 *
	 * @var array
	 */
	private $methods = array();

	/**
	 * The action that is assigned to the route.
	 *
	 * @var mixed
	 */
	protected $action;

	/**
	 * The regular expression requirements.
	 *
	 * @var array
	 */
	protected $wheres = array();

	/**
	 * The parameters that will be passed to the route callback.
	 *
	 * @var array
	 */
	protected $parameters = array();

	/**
	 * The compiled route.
	 *
	 * @var \Mini\Routing\CompiledRoute
	 */
	protected $compiled;


	/**
	 * Create a new Route instance.
	 *
	 * @param  array|string  $methods
	 * @param  string  $uri
	 * @param  array  $action
	 * @param  array  $wheres
	 */
	public function __construct($methods, $uri, array $action, array $wheres = array())
	{
		$this->methods = (array) $methods;

		$this->uri = '/' .ltrim(trim($uri), '/');

		$this->action = $action;
		$this->wheres = $wheres;

		if (in_array('GET', $this->methods) && ! in_array('HEAD', $this->methods)) {
			$this->methods[] = 'HEAD';
		}

		if (isset($this->action['prefix'])) {
			$this->prefix($this->action['prefix']);
		}
	}

	/**
	 * Checks if the Request matches the Route.
	 *
	 * @param \Mini\Http\Request  $request
	 * @param bool  $includingMethod
	 * @return bool
	 */
	public function matches(Request $request, $includingMethod = true)
	{
		// Match the Request method if required.
		if ($includingMethod && ! in_array($request->getMethod(), $this->methods)) {
			return false;
		}

		// Match the Request scheme.
		$secure = $request->secure();

		if (($this->httpOnly() && $secure) || ($this->secure() && ! $secure)) {
			return false;
		}

		$compiled = $this->compile();

		// Match the Request host.
		$pattern = $compiled->getHostRegex();

		if (! is_null($pattern) && ! $this->match($request->getHost(), $pattern)) {
			return false;
		}

		// Match the Request path.
		$path = ($request->path() == '/') ? '/' : '/' .$request->path();

		return $this->match($path, $compiled->getRegex());
	}

	/**
	 * Checks if a path matches the pattern and capture the matched parameters.
	 *
	 * @param string  $path
	 * @param string  $pattern
	 * @return bool
	 */
	protected function match($path, $pattern)
	{
		if (preg_match($pattern, $path, $matches) === 1) {
			$parameters = array_filter($matches, function($key)
			{
				return is_string($key);

			}, ARRAY_FILTER_USE_KEY);

			$this->parameters = array_merge($this->parameters, $parameters);

			return true;
		}

		return false;
	}

	/**
	 * Compile the Route pattern for matching.
	 *
	 * @return string
	 * @throws \LogicException
	 */
	public function compile()
	{
		if (isset($this->compiled)) {
			return $this->compiled;
		}

		return $this->compiled = RouteCompiler::compile($this);
	}

	/**
	 * Get or set the middlewares attached to the route.
	 *
	 * @param  array|string|null $middleware
	 * @return $this|array
	 */
	public function middleware($middleware = null)
	{
		$availMiddleware = Arr::get($this->action, 'middleware', array());

		if (is_null($middleware)) {
			return $availMiddleware;
		}

		if (is_string($middleware)) {
			$middleware = array($middleware);
		}

		$this->action['middleware'] = array_merge(
			$availMiddleware, $middleware
		);

		return $this;
	}

	/**
	 * Get a given parameter from the route.
	 *
	 * @param  string  $name
	 * @param  mixed   $default
	 * @return string
	 */
	public function parameter($name, $default = null)
	{
		$parameters = $this->parameters();

		return Arr::get($parameters, $name, $default);
	}

	/**
	 * Get the key / value list of parameters for the route.
	 *
	 * @return array
	 */
	public function parameters()
	{
		return array_map(function($value)
		{
			return is_string($value) ? rawurldecode($value) : $value;

		}, $this->parameters);
	}

	/**
	 * Get all of the parameter names for the route.
	 *
	 * @return array
	 */
	public function parameterNames()
	{
		$compiled = $this->compile();

		return $compiled->getVariables();
	}

	/**
	 * Set a regular expression requirement on the route.
	 *
	 * @param  array|string  $name
	 * @param  string  $expression
	 * @return $this
	 * @throws \BadMethodCallException
	 */
	public function where($name, $expression = null)
	{
		foreach ($this->parseWhere($name, $expression) as $name => $expression) {
			$this->wheres[$name] = $expression;
		}

		return $this;
	}

	/**
	 * Parse arguments to the where method into an array.
	 *
	 * @param  array|string  $name
	 * @param  string  $expression
	 * @return array
	 */
	protected function parseWhere($name, $expression)
	{
		return is_array($name) ? $name : array($name => $expression);
	}

	/**
	 * Add a prefix to the route URI.
	 *
	 * @param  string  $prefix
	 * @return $this
	 */
	public function prefix($prefix)
	{
		$prefix = trim($prefix, '/');

		if (! empty($prefix)) {
			$this->uri = '/' .trim($prefix .'/' .trim($this->uri, '/'), '/');
		}

		return $this;
	}

	/**
	 * Determine if the route only responds to HTTP requests.
	 *
	 * @return bool
	 */
	public function httpOnly()
	{
		return in_array('http', $this->action, true);
	}

	/**
	 * Determine if the route only responds to HTTPS requests.
	 *
	 * @return bool
	 */
	public function httpsOnly()
	{
		return $this->secure();
	}

	/**
	 * Determine if the route only responds to HTTPS requests.
	 *
	 * @return bool
	 */
	public function secure()
	{
		return in_array('https', $this->action, true);
	}

	/**
	 * Get the domain defined for the Route.
	 *
	 * @return string|null
	 */
	public function domain()
	{
		return isset($this->action['domain']) ? $this->action['domain'] : null;
	}

	/**
	 * Get the compiled route.
	 *
	 * @return \Mini\Routing\CompiledRoute
	 */
	public function getCompiled()
	{
		return $this->compile();
	}

	/**
	 * Get the regular expression requirements on the route.
	 *
	 * @return array
	 */
	public function getWheres()
	{
		return $this->wheres;
	}

	/**
	 * @return array
	 */
	public function getMethods()
	{
		return $this->methods;
	}

	/**
	 * Get the URI associated with the route.
	 *
	 * @return string
	 */
	public function getPath()
	{
		return $this->uri;
	}

	/**
	 * Get the uri of the route instance.
	 *
	 * @return string|null
	 */
	public function getUri()
	{
		return $this->uri;
	}

	/**
	 * Get the name of the route instance.
	 *
	 * @return string
	 */
	public function getName()
	{
		return Arr::get($this->action, 'as');
	}

	/**
	 * Get the action name for the route.
	 *
	 * @return string
	 */
	public function getActionName()
	{
		return Arr::get($this->action, 'controller', 'Closure');
	}

	/**
	 * Return the Action array.
	 *
	 * @return array
	 */
	public function getAction()
	{
		return $this->action;
	}
}
