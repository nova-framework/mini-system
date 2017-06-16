<?php

namespace Mini\Routing;

use Mini\Http\Request;
use Mini\Http\Response;
use Mini\Routing\Route;
use Mini\Routing\RouteCompiler;
use Mini\Routing\Router;
use Mini\Support\Arr;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use LogicException;


class RouteCollection implements Countable, IteratorAggregate
{
	/**
	 * All of the routes that have been registered.
	 *
	 * @var array
	 */
	protected $routes = array();

	/**
	 * An flattened array of all of the routes.
	 *
	 * @var array
	 */
	protected $allRoutes = array();

	/**
	 * The fallback Route, if any is registered.
	 *
	 * @var \Mini\Routing\Route
	 */
	protected $fallback;

	/**
	 * A look-up table of routes by their names.
	 *
	 * @var array
	 */
	protected $nameList = array();

	/**
	 * A look-up table of routes by controller action.
	 *
	 * @var array
	 */
	protected $actionList = array();


	/**
	 * Add a route to the router.
	 *
	 * @param  \Mini\Routing\Route  $route
	 * @return void
	 */
	public function addRoute($route)
	{
		$uri = $route->getUri();

		foreach ($route->getMethods() as $method) {
			$this->routes[$method][$uri] = $route;
		}

		$this->allRoutes[] = $route;

		// Add the Route instance to lookup lists.
		$this->addLookups($route);

		return $route;
	}

	/**
	 * Set a fallback route to the router.
	 *
	 * @param  \Mini\Routing\Route  $route
	 * @return void
	 * @throws \LogicException
	 */
	public function setFallback($route)
	{
		if (isset($this->fallback)) {
			throw new LogicException('The fallback Route is already set.');
		}

		$this->fallback = $route;

		$this->allRoutes[] = $route;

		// Add the Route instance to lookup lists.
		$this->addLookups($route);

		return $route;
	}

	/**
	 * Add the route to any look-up tables if necessary.
	 *
	 * @param  \Mini\Routing\Route  $route
	 * @return void
	 */
	protected function addLookups($route)
	{
		$action = $route->getAction();

		if (isset($action['as'])) {
			$name = $action['as'];

			$this->nameList[$name] = $route;
		}

		if (isset($action['controller'])) {
			$key = $action['controller'];

			if (! isset($this->actionList[$key])) {
				$this->actionList[$key] = $route;
			}
		}
	}

	/**
	 * Iterate through every route to find a matching route.
	 *
	 * @param \Mini\Http\Request $request
	 *
	 * @return \Mini\Routing\Route|null
	 */
	public function match(Request $request)
	{
		$routes = $this->get($request->getMethod());

		if (! is_null($route = $this->matchAgainstRoutes($routes, $request))) {
			return $route;
		}

		// No Route match found; check for the alternate HTTP Methods.
		$others = $this->checkForAlternateMethods($request);

		if (count($others) > 0) {
			return $this->getRouteForMethods($request, $others);
		}

		throw new NotFoundHttpException();
	}

	/**
	 * Determine if a route in the array matches the request.
	 *
	 * @param  array  $routes
	 * @param  \Mini\Http\Request  $request
	 * @param  bool  $includingMethod
	 * @return \Mini\Routing\Route|null
	 */
	protected function matchAgainstRoutes(array $routes, Request $request, $includingMethod = true)
	{
		return Arr::first($routes, function($uri, $route) use ($request, $includingMethod)
		{
			return $route->matches($request, $includingMethod);
		});
	}

	/**
	 * Determine if any routes match on another HTTP verb.
	 *
	 * @param  \Mini\Http\Request  $request
	 * @return array
	 */
	protected function checkForAlternateMethods(Request $request)
	{
		$methods = array_diff(Router::$methods, (array) $request->getMethod());

		$others = array();

		foreach ($methods as $method) {
			$routes = $this->get($method);

			if (! is_null($route = $this->matchAgainstRoutes($routes, $request, false))) {
				$others[] = $method;
			}
		}

		return $others;
	}

	/**
	 * Get a route (if necessary) that responds when other available methods are present.
	 *
	 * @param  \Mini\Http\Request  $request
	 * @param  array  $others
	 * @return \Mini\Routing\Route
	 *
	 * @throws \Symfony\Component\Routing\Exception\MethodNotAllowedHttpException
	 */
	protected function getRouteForMethods(Request $request, array $others)
	{
		if ($request->getMethod() !== 'OPTIONS') {
			throw new MethodNotAllowedHttpException($others);
		}

		$route = new Route('OPTIONS', $request->path(), function() use ($others)
		{
			return new Response('', 200, array('Allow' => implode(',', $others)));
		});

		return $route->bind($request);
	}

	/**
	 * Get all of the routes in the collection.
	 *
	 * @param  string|null  $method
	 * @return array
	 */
	protected function get($method = null)
	{
		if (is_null($method)) {
			return $this->getRoutes();
		}

		$routes = Arr::get($this->routes, $method, array());

		if (($method !== 'OPTIONS') && isset($this->fallback)) {
			// A fallback Route responds to any method excluding OPTIONS.
			$uri = $this->fallback->getUri();

			$routes[$uri] = $this->fallback;
		}

		return $routes;
	}

	/**
	 * Determine if the route collection contains a given named route.
	 *
	 * @param  string  $name
	 * @return bool
	 */
	public function hasNamedRoute($name)
	{
		return ! is_null($this->getByName($name));
	}

	/**
	 * Get a route instance by its name.
	 *
	 * @param  string  $name
	 * @return \Mini\Routing\Route|null
	 */
	public function getByName($name)
	{
		return isset($this->nameList[$name]) ? $this->nameList[$name] : null;
	}

	/**
	 * Get a route instance by its controller action.
	 *
	 * @param  string  $action
	 * @return \Mini\Routing\Route|null
	 */
	public function getByAction($action)
	{
		return isset($this->actionList[$action]) ? $this->actionList[$action] : null;
	}

	/**
	 * Get all of the registered routes.
	 *
	 * @return array
	 */
	public function getRoutes()
	{
		return $this->allRoutes;
	}

	/**
	 * Get an iterator for the items.
	 *
	 * @return \ArrayIterator
	 */
	public function getIterator()
	{
		return new ArrayIterator($this->getRoutes());
	}

	/**
	 * Count the number of items in the collection.
	 *
	 * @return int
	 */
	public function count()
	{
		return count($this->getRoutes());
	}
}
