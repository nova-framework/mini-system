<?php

namespace Mini\Routing;

use Mini\Container\Container;
use Mini\Routing\RouteDependencyResolverTrait;
use Mini\Support\Str;


class ControllerDispatcher
{
    use RouteDependencyResolverTrait;

    /**
     * The container instance.
     *
     * @var \Mini\Container\Container
     */
    protected $container;

    /**
     * Create a new controller dispatcher instance.
     *
     * @param  \Mini\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Dispatch a request to a given controller and method.
     *
     * @param  \Mini\Routing\Route  $route
     * @param  mixed  $controller
     * @param  string  $method
     * @return mixed
     */
    public function dispatch(Route $route, $controller, $method)
    {
        $parameters = $this->resolveClassMethodDependencies(
            $route->parameters(), $controller, $method
        );

        if (method_exists($controller, 'callAction')) {
            return $controller->callAction($method, $parameters);
        }

        return call_user_func_array(array($controller, $method), $parameters);
    }

    /**
     * Get the middleware for the controller instance.
     *
     * @param  \Mini\Routing\Controller  $controller
     * @param  string  $method
     * @return array
     */
    public static function getMiddleware($controller, $method)
    {
        if (! method_exists($controller, 'getMiddleware')) {
            return array();
        }

        $results = array();

        foreach ($controller->getMiddleware() as $name => $options) {
            if (! static::methodExcludedByOptions($method, $options)) {
                $results[] = $name;
            }
        }

        return $results;
    }

    /**
     * Determine if the given options exclude a particular method.
     *
     * @param  string  $method
     * @param  array  $options
     * @return bool
     */
    protected static function methodExcludedByOptions($method, array $options)
    {
        return (isset($options['only']) && ! in_array($method, (array) $options['only'])) ||
            (! empty($options['except']) && in_array($method, (array) $options['except']));
    }
}
