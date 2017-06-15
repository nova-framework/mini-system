<?php

namespace Mini\Routing;

use Mini\Container\Container;
use Mini\Events\DispatcherInterface;
use Mini\Pipeline\Pipeline;
use Mini\Http\Exception\HttpResponseException;
use Mini\Http\Request;
use Mini\Http\Response;
use Mini\Routing\Controller;
use Mini\Routing\ResourceRegistrar;
use Mini\Routing\Route;
use Mini\Routing\RouteCollection;
use Mini\Support\Arr;
use Mini\Support\Str;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

use BadMethodCallException;
use Closure;
use ReflectionFunction;
use ReflectionMethod;


class Router
{
	/**
	 * The event dispatcher instance.
	 *
	 * @var \Mini\Events\Dispatcher
	 */
	protected $events;

	/**
	 * The IoC container instance.
	 *
	 * @var \Mini\Container\Container
	 */
	protected $container;

	/**
	 * The currently dispatched Route instance.
	 *
	 * @var \Mini\Routing\Route
	 */
	protected $currentRoute;

	/**
	 * The request currently being dispatched.
	 *
	 * @var \Mini\Http\Request
	 */
	protected $currentRequest;

	/**
	 * All of the short-hand keys for middlewares.
	 *
	 * @var array
	 */
	protected $middleware = array();

	/**
	 * All of the middleware groups.
	 *
	 * @var array
	 */
	protected $middlewareGroups = array();

	/**
	 * The instance of RouteCollection.
	 *
	 * @var \Mini\Routing\RouteCollection;
	 */
	protected $routes;

	/**
	 * All of the wheres that have been registered.
	 *
	 * @var array
	 */
	protected $patterns = array();

	/**
	 * Array of Route Groups.
	 */
	protected $groupStack = array();

	/**
	 * An array of HTTP request methods.
	 *
	 * @var array
	 */
	public static $methods = array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS');

	/**
	 * The resource registrar instance.
	 *
	 * @var \Mini\Routing\ResourceRegistrar
	 */
	protected $registrar;


	/**
	 * Construct a new Router instance.
	 *
	 * @return void
	 */
	public function __construct(DispatcherInterface $events = null, Container $container = null)
	{
		$this->events = $events;

		$this->container = $container ?: new Container();

		$this->routes = new RouteCollection();
	}

	/**
	 * Register a new GET route with the router.
	 *
	 * @param  string  $uri
	 * @param  \Closure|array|string  $action
	 * @return \Mini\Routing\Route
	 */
	public function get($route, $action)
	{
		return $this->addRoute(array('GET', 'HEAD'), $route, $action);
	}

	/**
	 * Register a new POST route with the router.
	 *
	 * @param  string  $uri
	 * @param  \Closure|array|string  $action
	 * @return \Mini\Routing\Route
	 */
	public function post($route, $action)
	{
		return $this->addRoute('POST', $route, $action);
	}

	/**
	 * Register a new PUT route with the router.
	 *
	 * @param  string  $uri
	 * @param  \Closure|array|string  $action
	 * @return \Mini\Routing\Route
	 */
	public function put($route, $action)
	{
		return $this->addRoute('PUT', $route, $action);
	}

	/**
	 * Register a new PATCH route with the router.
	 *
	 * @param  string  $uri
	 * @param  \Closure|array|string  $action
	 * @return \Mini\Routing\Route
	 */
	public function patch($route, $action)
	{
		return $this->addRoute('PATCH', $route, $action);
	}

	/**
	 * Register a new DELETE route with the router.
	 *
	 * @param  string  $uri
	 * @param  \Closure|array|string  $action
	 * @return \Mini\Routing\Route
	 */
	public function delete($route, $action)
	{
		return $this->addRoute('DELETE', $route, $action);
	}

	/**
	 * Register a new OPTIONS route with the router.
	 *
	 * @param  string  $uri
	 * @param  \Closure|array|string  $action
	 * @return \Mini\Routing\Route
	 */
	public function options($route, $action)
	{
		return $this->addRoute('OPTIONS', $route, $action);
	}

	/**
	 * Register a new route responding to all verbs.
	 *
	 * @param  string  $uri
	 * @param  \Closure|array|string  $action
	 * @return \Mini\Routing\Route
	 */
	public function any($route, $action)
	{
		$methods = array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE');

		return $this->addRoute($methods, $route, $action);
	}

	/**
	 * Register a new route with the given verbs.
	 *
	 * @param  array|string  $methods
	 * @param  string  $uri
	 * @param  \Closure|array|string  $action
	 * @return \Mini\Routing\Route
	 */
	public function match($methods, $route, $action)
	{
		return $this->addRoute(array_map('strtoupper', (array) $methods), $uri, $action);
	}

	/**
	 * Register a new fallback route responding to all verbs and any URI.
	 *
	 * @param  \Closure|array|string  $action
	 * @return \Mini\Routing\Route
	 */
	public function fallback($action)
	{
		$methods = array('GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE');

		$route = $this->createRoute($methods, '/{slug?}', $action)->where('slug', '(.*)');

		return $this->routes->setFallback($route);
	}

	/**
	 * Route a resource to a controller.
	 *
	 * @param  string  $name
	 * @param  string  $controller
	 * @param  array   $options
	 * @return void
	 */
	public function resource($name, $controller, array $options = array())
	{
		$registrar = $this->getRegistrar();

		$registrar->register($name, $controller, $options);
	}

	/**
	 * Register a group of routes that share attributes.
	 *
	 * @param  array	$attributes
	 * @param  Closure  $callback
	 * @return void
	 */
	public function group($attributes, Closure $callback)
	{
		if (isset($attributes['middleware']) && is_string($attributes['middleware'])) {
			$attributes['middleware'] = explode('|', $attributes['middleware']);
		}

		$this->updateGroupStack($attributes);

		call_user_func($callback, $this);

		array_pop($this->groupStack);
	}

	/**
	 * Update the group stack with the given attributes.
	 *
	 * @param  array  $attributes
	 * @return void
	 */
	protected function updateGroupStack(array $attributes)
	{
		if ( ! empty($this->groupStack)) {
			$attributes = $this->mergeGroup($attributes, end($this->groupStack));
		}

		$this->groupStack[] = $attributes;
	}

	/**
	 * Merge the given array with the last group stack.
	 *
	 * @param  array  $new
	 * @return array
	 */
	public function mergeWithLastGroup($new)
	{
		return $this->mergeGroup($new, end($this->groupStack));
	}

	/**
	 * Merge the given group attributes.
	 *
	 * @param  array  $new
	 * @param  array  $old
	 * @return array
	 */
	protected function mergeGroup($new, $old)
	{
		$new['namespace'] = static::formatUsesPrefix($new, $old);

		$new['prefix'] = static::formatGroupPrefix($new, $old);

		if (isset($new['domain'])) {
			unset($old['domain']);
		}

		$new['where'] = array_merge(
			isset($old['where']) ? $old['where'] : array(),
			isset($new['where']) ? $new['where'] : array()
		);

		if (isset($old['as'])) {
			$new['as'] = $old['as'] .(isset($new['as']) ? '.' .$new['as'] : '');
		}

		return array_merge_recursive(
			Arr::except($old, array('namespace', 'prefix', 'where', 'as')), $new
		);
	}

	/**
	 * Format the uses prefix for the new group attributes.
	 *
	 * @param  array  $new
	 * @param  array  $old
	 * @return string
	 */
	protected static function formatUsesPrefix($new, $old)
	{
		if (isset($new['namespace'])) {
			return isset($old['namespace']) && (strpos($new['namespace'], '\\') !== 0)
				? trim($old['namespace'], '\\') .'\\' .trim($new['namespace'], '\\')
				: trim($new['namespace'], '\\');
		}

		return isset($old['namespace']) ? $old['namespace'] : null;
	}

	/**
	 * Format the prefix for the new group attributes.
	 *
	 * @param  array  $new
	 * @param  array  $old
	 * @return string
	 */
	protected static function formatGroupPrefix($new, $old)
	{
		$oldPrefix = isset($old['prefix']) ? $old['prefix'] : null;

		if (isset($new['prefix'])) {
			return trim($oldPrefix, '/') .'/' .trim($new['prefix'], '/');
		}

		return $oldPrefix;
	}

	/**
	 * Add a route to the underlying route collection.
	 *
	 * @param  array|string  $methods
	 * @param  string  $uri
	 * @param  \Closure|array|string  $action
	 * @return \Mini\Routing\Route
	 */
	public function addRoute($methods, $route, $action)
	{
		return $this->routes->addRoute($this->createRoute($methods, $route, $action));
	}

	/**
	 * Create a new route instance.
	 *
	 * @param  array|string  $methods
	 * @param  string  $uri
	 * @param  mixed   $action
	 * @return \Mini\Routing\Route
	 */
	protected function createRoute($methods, $uri, $action)
	{
		$action = $this->parseAction($action);

		if ($this->hasGroupStack()) {
			$action = $this->mergeWithLastGroup($action);
		}

		return $this->newRoute($methods, $uri, $action);
	}

	/**
	 * Parse the action into an array format.
	 *
	 * @param  mixed  $action
	 * @return array
	 */
	protected function parseAction($action)
	{
		if ($action instanceof Closure) {
			return array('uses' => $action);
		}

		if ($this->actionReferencesController($action)) {
			$action = $this->convertToControllerAction($action);
		}

		// If no uses is defined, we will look for the inner Closure.
		else if (! isset($action['uses'])) {
			$action['uses'] = $this->findCallable($action);
		}

		if (isset($action['middleware']) && is_string($action['middleware'])) {
			$action['middleware'] = explode('|', $action['middleware']);
		}

		return $action;
	}

	/**
	 * Find the Closure in an action array.
	 *
	 * @param  array  $action
	 * @return \Closure
	 */
	protected function findCallable(array $action)
	{
		return Arr::first($action, function($key, $value)
		{
			return is_callable($value) && is_numeric($key);
		});
	}

	/**
	 * Create a new Route object.
	 *
	 * @param  array|string  $methods
	 * @param  string  $uri
	 * @param  mixed  $action
	 * @return \Mini\Routing\Route
	 */
	protected function newRoute($methods, $uri, $action)
	{
		$patterns = array_merge(
			$this->patterns, isset($action['where']) ? $action['where'] : array()
		);

		return (new Route($methods, $uri, $action, $patterns))
			->setContainer($this->container);
	}

	/**
	 * Prefix the given URI with the last prefix.
	 *
	 * @param  string  $uri
	 * @return string
	 */
	protected function prefix($uri)
	{
		$prefix = '';

		if (! empty($this->groupStack)) {
			$last = end($this->groupStack);

			$prefix = isset($last['prefix']) ? $last['prefix'] : '';
		}

		return trim(trim($prefix, '/') .'/' .trim($uri, '/'), '/') ?: '/';
	}

	/**
	 * Determine if the action is routing to a controller.
	 *
	 * @param  array  $action
	 * @return bool
	 */
	protected function actionReferencesController($action)
	{
		if ($action instanceof Closure) {
			return false;
		} else if (is_string($action)) {
			return true;
		}

		return isset($action['uses']) && is_string($action['uses']);
	}

	/**
	 * Add a controller based route action to the action array.
	 *
	 * @param  array|string  $action
	 * @return array
	 */
	protected function convertToControllerAction($action)
	{
		if (is_string($action)) {
			$action = array('uses' => $action);
		}

		if (! empty($this->groupStack)) {
			$action['uses'] = $this->prependGroupUses($action['uses']);
		}

		$action['controller'] = $action['uses'];

		return $action;
	}

	/**
	 * Prepend the last group uses onto the use clause.
	 *
	 * @param  string  $uses
	 * @return string
	 */
	protected function prependGroupUses($uses)
	{
		$group = end($this->groupStack);

		return isset($group['namespace']) && (strpos($uses, '\\') !== 0) ? $group['namespace'] .'\\' .$uses : $uses;
	}

	/**
	 * Dispatch the request and return the response.
	 *
	 * @param  \Mini\Http\Request  $request
	 *
	 * @return mixed
	 */
	public function dispatch(Request $request)
	{
		$this->currentRequest = $request;

		$response = $this->dispatchToRoute($request);

		return $this->prepareResponse($request, $response);
	}

	/**
	 * Dispatch the request to a route and return the response.
	 *
	 * @param  \Mini\Http\Request  $request
	 *
	 * @return mixed
	 */
	public function dispatchToRoute(Request $request)
	{
		$route = $this->findRoute($request);

		$request->setRouteResolver(function () use ($route)
		{
			return $route;
		});

		$this->events->fire('router.matched', array($route, $request));

		$response = $this->runRouteWithinStack($route, $request);

		return $this->prepareResponse($request, $response);
	}

	/**
	 * Search the routes for the route matching a request.
	 *
	 * @param  \Mini\Http\Request  $request
	 *
	 * @return \Mini\Routing\Route|null
	 */
	protected function findRoute(Request $request)
	{
		return $this->currentRoute = $this->routes->match($request);
	}

	/**
	 * Run the given Route within a Pipeline instance stack.
	 *
	 * @param  \Mini\Routing\Route	$route
	 * @param  \Mini\Http\Request	$request
	 * @return mixed
	 */
	protected function runRouteWithinStack(Route $route, Request $request)
	{
		$middleware = $this->gatherRouteMiddlewares($route);

		return $this->sendThroughPipeline($middleware, $request, function ($request) use ($route)
		{
			return $this->prepareResponse(
				$request, $route->run()
			);
		});
	}

	/**
	 * Gather the middleware for the given route.
	 *
	 * @param  \Mini\Routing\Route  $route
	 * @return array
	 */
	public function gatherRouteMiddlewares(Route $route)
	{
		$middleware = array_map(function ($name)
		{
			return $this->resolveMiddleware($name);

		}, $route->gatherMiddleware());

		return Arr::flatten($middleware);
	}

	/**
	 * Resolve the middleware name to class name preserving passed parameters.
	 *
	 * @param  string $name
	 * @return array
	 */
	public function resolveMiddleware($name)
	{
		if (isset($this->middlewareGroups[$name])) {
			return $this->parseMiddlewareGroup($name);
		}

		return $this->parseMiddleware($name);
	}

	/**
	 * Parse the middleware and format it for usage.
	 *
	 * @param  string  $name
	 * @return array
	 */
	protected function parseMiddleware($name)
	{
		list($name, $parameters) = array_pad(
			array_map('trim', explode(':', $name, 2)), 2, null
		);

		//
		$callable = isset($this->middleware[$name]) ? $this->middleware[$name] : $name;

		// When no parameters are defined, we will just return the callable.
		if (empty($parameters)) {
			return $callable;
		}

		// If the callable is a string, we will append the parameters, then return it.
		else if (is_string($callable)) {
			return $callable .':' .$parameters;
		}

		// For a callback with parameters, we will create a proper middleware closure.
		$parameters = explode(',', $parameters);

		return function ($passable, $stack) use ($callable, $parameters)
		{
			$parameters = array_merge(array($passable, $stack), $parameters);

			return call_user_func_array($callable, $parameters);
		};
	}

	/**
	 * Parse the middleware group and format it for usage.
	 *
	 * @param  string  $name
	 * @return array
	 */
	protected function parseMiddlewareGroup($name)
	{
		$results = array();

		foreach ($this->middlewareGroups[$name] as $middleware) {
			if (isset($this->middlewareGroups[$middleware])) {
				$results = array_merge(
					$results, $this->parseMiddlewareGroup($middleware)
				);

				continue;
			}

			$results[] = $this->parseMiddleware($middleware);
		}

		return $results;
	}

	/**
	 * Send the Request through the pipeline with the given callback.
	 *
	 * @param  array  $middleware
	 * @param  \Mini\Http\Request  $request
	 * @param  \Closure  $destination
	 * @return mixed
	 */
	protected function sendThroughPipeline(array $middleware, Request $request, Closure $destination)
	{
		if (! empty($middleware) && ! $this->shouldSkipMiddleware()) {
			$pipeline = new Pipeline($this->container);

			return $pipeline->send($request)->through($middleware)->then($destination);
		}

		return call_user_func($destination, $request);
	}

	/**
	 * Determines whether middleware should be skipped during request.
	 *
	 * @return bool
	 */
	protected function shouldSkipMiddleware()
	{
		return $this->container->bound('middleware.disable') && ($this->container->make('middleware.disable') === true);
	}

	/**
	 * Create a response instance from the given value.
	 *
	 * @param  \Symfony\Component\HttpFoundation\Request  $request
	 * @param  mixed  $response
	 * @return \Mini\Http\Response
	 */
	public function prepareResponse($request, $response)
	{
		if (! $response instanceof SymfonyResponse) {
			$response = new Response($response);
		}

		return $response->prepare($request);
	}

	/**
	 * Register a route matched event listener.
	 *
	 * @param  string|callable  $callback
	 * @return void
	 */
	public function matched($callback)
	{
		$this->events->listen('router.matched', $callback);
	}

	/**
	 * Get all of the defined middleware short-hand names.
	 *
	 * @return array
	 */
	public function getMiddleware()
	{
		return $this->middleware;
	}

	/**
	 * Register a short-hand name for a middleware.
	 *
	 * @param  string  $name
	 * @param  string|\Closure  $middleware
	 * @return $this
	 */
	public function middleware($name, $middleware)
	{
		$this->middleware[$name] = $middleware;

		return $this;
	}

	/**
	 * Register a group of middleware.
	 *
	 * @param  string  $name
	 * @param  array  $middleware
	 * @return $this
	 */
	public function middlewareGroup($name, array $middleware)
	{
		$this->middlewareGroups[$name] = $middleware;

		return $this;
	}

	/**
	 * Add a middleware to the beginning of a middleware group.
	 *
	 * If the middleware is already in the group, it will not be added again.
	 *
	 * @param  string  $group
	 * @param  string  $middleware
	 * @return $this
	 */
	public function prependMiddlewareToGroup($group, $middleware)
	{
		if (isset($this->middlewareGroups[$group]) && ! in_array($middleware, $this->middlewareGroups[$group])) {
			array_unshift($this->middlewareGroups[$group], $middleware);
		}

		return $this;
	}

	/**
	 * Add a middleware to the end of a middleware group.
	 *
	 * If the middleware is already in the group, it will not be added again.
	 *
	 * @param  string  $group
	 * @param  string  $middleware
	 * @return $this
	 */
	public function pushMiddlewareToGroup($group, $middleware)
	{
		if (! array_key_exists($group, $this->middlewareGroups)) {
			$this->middlewareGroups[$group] = array();
		}

		if (! in_array($middleware, $this->middlewareGroups[$group])) {
			$this->middlewareGroups[$group][] = $middleware;
		}

		return $this;
	}

	/**
	 * Return the current Matched Route, if there are any.
	 *
	 * @return null|Route
	 */
	public function getCurrentRoute()
	{
		return $this->current();
	}

	/**
	 * Get the currently dispatched route instance.
	 *
	 * @return \Mini\Routing\Route
	 */
	public function current()
	{
		return $this->currentRoute;
	}

	/**
	 * Check if a Route with the given name exists.
	 *
	 * @param  string  $name
	 * @return bool
	 */
	public function has($name)
	{
		return $this->routes->hasNamedRoute($name);
	}

	/**
	 * Get the current route name.
	 *
	 * @return string|null
	 */
	public function currentRouteName()
	{
		if (! is_null($route = $this->current())) {
			return $route->getName();
		}
	}

	/**
	 * Get a route parameter for the current route.
	 *
	 * @param  string  $key
	 * @param  string  $default
	 * @return mixed
	 */
	public function input($key, $default = null)
	{
		return $this->current()->parameter($key, $default);
	}

	/**
	 * Set/get a global where pattern on all routes.
	 *
	 * @param  string  $key
	 * @param  string  $pattern
	 * @return void
	 */
	public function pattern($key, $pattern = null)
	{
		if (is_null($pattern)) {
			return Arr::get($this->patterns, $key);
		}

		$this->patterns[$key] = $pattern;
	}

	/**
	 * Set/get a group of global where patterns on all routes.
	 *
	 * @param  array  $patterns
	 * @return void
	 */
	public function patterns($patterns = null)
	{
		if (is_null($patterns)) {
			return $this->patterns;
		}

		foreach ($patterns as $key => $pattern) {
			$this->patterns[$key] = $pattern;
		}
	}

	/**
	 * Determine if the router currently has a group defined.
	 *
	 * @return bool
	 */
	public function hasGroupStack()
	{
		return ! empty($this->groupStack);
	}

	/**
	 * Get the request currently being dispatched.
	 *
	 * @return \Mini\Http\Request
	 */
	public function getCurrentRequest()
	{
		return $this->currentRequest;
	}

	/**
	 * Return the available Routes.
	 *
	 * @return \Mini\Routing\RouteCollection
	 */
	public function getRoutes()
	{
		return $this->routes;
	}

	/**
	 * Get a Resource Registrar instance.
	 *
	 * @return \Mini\Routing\ResourceRegistrar
	 */
	public function getRegistrar()
	{
		return $this->registrar ?: $this->registrar = new ResourceRegistrar($this);
	}
}
