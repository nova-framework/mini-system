<?php

namespace Mini\Routing;

use Mini\Container\Container;
use Mini\Http\Exception\HttpResponseException;
use Mini\Http\Request;
use Mini\Routing\CompiledRoute;
use Mini\Routing\ControllerDispatcher;
use Mini\Routing\RouteCompiler;
use Mini\Routing\RouteDependencyResolverTrait;
use Mini\Support\Arr;
use Mini\Support\Str;

use Closure;
use LogicException;
use ReflectionFunction;
use ReflectionMethod;


class Route
{
	use RouteDependencyResolverTrait;

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
	 * The Controller method.
	 *
	 * @var mixed
	 */
	public $method;

	/**
	 * The Controller instance.
	 *
	 * @var mixed
	 */
	public $controller;

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
	protected $parameters;

	/**
	 * The parameter names for the route.
	 *
	 * @var array|null
	 */
	protected $parameterNames;

	/**
	 * The compiled route.
	 *
	 * @var \Mini\Routing\CompiledRoute
	 */
	protected $compiled;

	/**
	 * The computed gathered middleware.
	 *
	 * @var array|null
	 */
	public $computedMiddleware;

	/**
	 * The Container instance used by the Route.
	 *
	 * @var \Mini\Container\Container
	 */
	protected $container;


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
	 * Run the route action and return the response.
	 *
	 * @return mixed
	 */
	public function run()
	{
		$this->container = $this->container ?: new Container();

		try {
			if ($this->isControllerAction()) {
				return $this->runController();
			}

			return $this->runCallable();
		}
		catch (HttpResponseException $e) {
			return $e->getResponse();
		}
	}

	/**
	 * Checks whether the route's action is a controller.
	 *
	 * @return bool
	 */
	protected function isControllerAction()
	{
		return is_string($this->action['uses']);
	}

	/**
	 * Run the route action and return the response.
	 *
	 * @return mixed
	 */
	protected function runCallable()
	{
		$callable = $this->action['uses'];

		$parameters = $this->resolveMethodDependencies(
			$this->parameters(), new ReflectionFunction($callable)
		);

		return call_user_func_array($callable, $parameters);
	}

	/**
	 * Run the route action and return the response.
	 *
	 * @return mixed
	 *
	 * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
	 */
	protected function runController()
	{
		return (new ControllerDispatcher($this->container))->dispatch(
			$this, $this->getController(), $this->getControllerMethod()
		);
	}

	/**
	 * Get the controller instance for the route.
	 *
	 * @return mixed
	 */
	public function getController()
	{
		if (! isset($this->controller)) {
			list ($controller, $this->method) = $this->parseControllerCallback();

			$this->controller = $this->container->make($controller);
		}

		return $this->controller;
	}

	/**
	 * Get the controller method used for the route.
	 *
	 * @return string
	 */
	public function getControllerMethod()
	{
		if (! isset($this->method)) {
			list (, $this->method) = $this->parseControllerCallback();
		}

		return $this->method;
	}

	/**
	 * Parse the controller.
	 *
	 * @return array
	 */
	protected function parseControllerCallback()
	{
		return Str::parseCallback($this->action['uses']);
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

		$compiled = $this->compileRoute();

		// Setup the parameter names.
		$this->parameterNames = $compiled->getVariables();

		// Match the Request host.
		$pattern = $compiled->getHostRegex();

		if (! is_null($pattern) && ! $this->matchPattern($pattern, $request->getHost())) {
			return false;
		}

		// Match the Request path.
		$path = '/' .ltrim($request->decodedPath(), '/');

		return $this->matchPattern($compiled->getRegex(), $path);
	}

	/**
	 * Checks if a subject matches the pattern and capture the matched parameters.
	 *
	 * @param string  $pattern
	 * @param string  $subject
	 * @return bool
	 */
	protected function matchPattern($pattern, $subject)
	{
		if (preg_match($pattern, $subject, $matches) === 1) {
			$parameters = $this->matchToKeys($matches);

			$this->parameters = array_merge(
				isset($this->parameters) ? $this->parameters : array(), $parameters
			);

			return true;
		}

		return false;
	}

	/**
	 * Combine a set of parameter matches with the route's keys.
	 *
	 * @param  array  $matches
	 * @return array
	 */
	protected function matchToKeys(array $matches)
	{
		if (empty($parameterNames = $this->parameterNames())) {
			return array();
		}

		$parameters = array_intersect_key($matches, array_flip($parameterNames));

		return array_filter($parameters, function($value)
		{
			return is_string($value) && (strlen($value) > 0);
		});
	}

	/**
	 * Compile the Route pattern for matching.
	 *
	 * @return string
	 * @throws \LogicException
	 */
	public function compileRoute()
	{
		if (! isset($this->compiled)) {
			$this->compiled = (new RouteCompiler($this))->compile();
		}

		return $this->compiled;
	}

	/**
	 * Get all middleware, including the ones from the controller.
	 *
	 * @return array
	 */
	public function gatherMiddleware()
	{
		if (! is_null($this->computedMiddleware)) {
			return $this->computedMiddleware;
		}

		$this->computedMiddleware = array();

		return $this->computedMiddleware = array_unique(array_merge(
			$this->middleware(), $this->controllerMiddleware()

		), SORT_REGULAR);
	}

	/**
	 * Get or set the middlewares attached to the route.
	 *
	 * @param  array|string|null $middleware
	 * @return $this|array
	 */
	public function middleware($middleware = null)
	{
		$routeMiddleware = (array) Arr::get($this->action, 'middleware', array());

		if (is_null($middleware)) {
			return $routeMiddleware;
		}

		if (is_string($middleware)) {
			$middleware = func_get_args();
		}

		$this->action['middleware'] = array_merge($routeMiddleware, $middleware);

		return $this;
	}

	/**
	 * Get the middleware for the route's controller.
	 *
	 * @return array
	 */
	public function controllerMiddleware()
	{
		if (! $this->isControllerAction()) {
			return array();
		}

		return ControllerDispatcher::getMiddleware(
			$this->getController(), $this->getControllerMethod()
		);
	}

	/**
	 * Get a given parameter from the route.
	 *
	 * @param  string  $name
	 * @param  mixed   $default
	 * @return string
	 */
	public function getParameter($name, $default = null)
	{
		return $this->parameter($name, $default);
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
	 * Set a parameter to the given value.
	 *
	 * @param  string  $name
	 * @param  mixed   $value
	 * @return void
	 */
	public function setParameter($name, $value)
	{
		$this->parameters();

		$this->parameters[$name] = $value;
	}

	/**
	 * Unset a parameter on the route if it is set.
	 *
	 * @param  string  $name
	 * @return void
	 */
	public function forgetParameter($name)
	{
		$this->parameters();

		unset($this->parameters[$name]);
	}

	/**
	 * Get the key / value list of parameters for the route.
	 *
	 * @return array
	 */
	public function parameters()
	{
		if (isset($this->parameters)) {
			return $this->parameters;
		}

		throw new LogicException("Route is not bound.");
	}

	/**
	 * Get all of the parameter names for the route.
	 *
	 * @return array
	 */
	public function parameterNames()
	{
		if (isset($this->parameterNames)) {
			return $this->parameterNames;
		}

		return $this->parameterNames = $this->compileParameterNames();
	}

	/**
	 * Get the parameter names for the route.
	 *
	 * @return array
	 */
	protected function compileParameterNames()
	{
		preg_match_all('/\{(.*?)\}/', $this->domain() .$this->uri, $matches);

		return array_map(function ($value)
		{
			return trim($value, '?');

		}, $matches[1]);
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
		return isset($this->action['domain'])
			? str_replace(array('http://', 'https://'), '', $this->action['domain']) : null;
	}

	/**
	 * Get the compiled route.
	 *
	 * @return \Mini\Routing\CompiledRoute
	 */
	public function getCompiled()
	{
		return $this->compileRoute();
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

	/**
	 * Set the container instance on the route.
	 *
	 * @param  \Mini\Container\Container  $container
	 * @return $this
	 */
	public function setContainer(Container $container)
	{
		$this->container = $container;

		return $this;
	}

	/**
	 * Dynamically access route parameters.
	 *
	 * @param  string  $key
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this->parameter($key);
	}
}
