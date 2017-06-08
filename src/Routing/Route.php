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
	 * @param  array 	$method
	 * @param  string	$uri
	 * @param  array	 $action
	 * @param  array	 $wheres
	 */
	public function __construct(array $methods, $uri, array $action, array $wheres = array())
	{
		$this->uri		= $uri;
		$this->methods	= $methods;
		$this->action	= $action;
		$this->wheres	= $wheres;
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
			return;
		}

		return $this->compiled = RouteCompiler::compile($this);
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
		foreach (array('Method', 'Scheme', 'Host', 'Uri') as $type) {
			if (! $includingMethod && ($type === 'Method')) {
				continue;
			}

			if (! call_user_func(array($this, "match{$type}"), $request)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Checks if a request method matches one of the Route methods.
	 *
	 * @param \Mini\Http\Request  $request
	 * @return bool
	 */
	protected function matchMethod(Request $request)
	{
		return in_array($request->getMethod(), $this->getMethods());
	}

	/**
	 * Checks if a request scheme matches the Route scheme.
	 *
	 * @param \Mini\Http\Request  $request
	 * @return bool
	 */
	protected function matchScheme(Request $request)
	{
		$secure = $request->secure();

		if ($this->httpOnly()) {
			return ! $secure;
		} else if ($this->secure()) {
			return $secure;
		}

		return true;
	}

	/**
	 * Checks if a request host matches the Route host pattern.
	 *
	 * @param \Mini\Http\Request  $request
	 * @return bool
	 */
	protected function matchHost(Request $request)
	{
		if (is_null($domain = $this->domain())) {
			return true;
		}

		$pattern = '.' .$request->getHost();

		$compiled = $this->getCompiled();

		return $this->match($pattern, $compiled->getHostRegex());
	}

	/**
	 * Checks if a request path matches the Route uri.
	 *
	 * @param \Mini\Http\Request  $request
	 * @return bool
	 */
	protected function matchUri(Request $request)
	{
		$pattern = '/' .ltrim($request->path(), '/');

		$compiled = $this->getCompiled();

		return $this->match($pattern, $compiled->getRegex());
	}

	/**
	 * Checks if a string matches the regex and capture the parameters.
	 *
	 * @param string  $value
	 * @param string  $regex
	 * @return bool
	 */
	protected function match($value, $regex)
	{
		if ($value === $regex) {
			// We have a direct match, then no parameters to capture.
			return true;
		} else if (preg_match($regex, $value, $matches) === 1) {
			$this->parameters = array_merge(
				$this->parameters, $this->gatherParameters($matches)
			);

			return true;
		}

		return false;
	}

	/**
	 * Get the Route parameters from the matches.
	 *
	 * @param  array  $matches
	 * @return array
	 */
	protected function gatherParameters(array $matches)
	{
		return array_filter($matches, function($value, $key)
		{
			return is_string($key) && is_string($value) && (strlen($value) > 0);

		}, ARRAY_FILTER_USE_BOTH);
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
		$compiled = $this->getCompiled();

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

	public function getCompiled()
	{
		$this->compile();

		return $this->compiled;
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
