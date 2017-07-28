<?php

namespace Mini\Container;

use Mini\Container\BindingResolutionException;

use ArrayAccess;
use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;


class Container implements ArrayAccess
{
    /**
     * The current globally available container (if any).
     *
     * @var static
     */
    protected static $instance;

    /**
     * An array of the types that have been resolved.
     *
     * @var array
     */
    protected $resolved = array();

    /**
     * The container's bindings.
     *
     * @var array
     */
    protected $bindings = array();

    /**
     * The container's shared instances.
     *
     * @var array
     */
    protected $instances = array();

    /**
     * The registered type aliases.
     *
     * @var array
     */
    protected $aliases = array();

    /**
     * All of the registered rebound callbacks.
     *
     * @var array
     */
    protected $reboundCallbacks = array();


    /**
     * Determine if a given string is resolvable.
     *
     * @param  string  $abstract
     * @return bool
     */
    protected function resolvable($abstract)
    {
        return $this->bound($abstract) || $this->isAlias($abstract);
    }

    /**
     * Determine if the given abstract type has been bound.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function bound($abstract)
    {
        return isset($this->bindings[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Determine if the given abstract type has been resolved.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function resolved($abstract)
    {
        return isset($this->resolved[$abstract]) || isset($this->instances[$abstract]);
    }

    /**
     * Determine if a given string is an alias.
     *
     * @param  string  $name
     * @return bool
     */
    public function isAlias($name)
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Register a binding with the container.
     *
     * @param  string   $abstract
     * @param  mixed    $concrete
     * @param  bool     $shared
     * @return void
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);

            $this->alias($abstract, $alias);
        }

        $this->dropStaleInstances($abstract);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        if (! $concrete instanceof Closure) {
            $concrete = $this->getClosure($abstract, $concrete);
        }

        $bound = $this->bound($abstract);

        $this->bindings[$abstract] = compact('concrete', 'shared');

        if ($bound) {
            $this->rebound($abstract);
        }
    }

    /**
     * Get the Closure to be used when building a type.
     *
     * @param  string  $abstract
     * @param  string  $concrete
     * @return \Closure
     */
    protected function getClosure($abstract, $concrete)
    {
        return function($container) use ($abstract, $concrete)
        {
            $method = ($abstract == $concrete) ? 'build' : 'make';

            return call_user_func(array($container, $method), $concrete);
        };
    }

    /**
     * Register a shared binding in the container.
     *
     * @param  string   $abstract
     * @param  Closure  $concrete
     * @return void
     */
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Wrap a Closure such that it is shared.
     *
     * @param  Closure  $closure
     * @return Closure
     */
    public function share(Closure $closure)
    {
        return function($container) use ($closure)
        {
            static $instance;

            if (is_null($instance)) {
                $instance = call_user_func($closure, $container);
            }

            return $instance;
        };
    }

    /**
     * Bind a shared Closure into the container.
     *
     * @param  string  $abstract
     * @param  \Closure  $closure
     * @return void
     */
    public function bindShared($abstract, Closure $closure)
    {
        return $this->bind($abstract, $this->share($closure), true);
    }

    /**
     * "Extend" an abstract type in the container.
     *
     * @param  string    $abstract
     * @param  \Closure  $closure
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function extend($abstract, Closure $closure)
    {
        if (! isset($this->bindings[$abstract])) {
            throw new InvalidArgumentException("Type {$abstract} is not bound.");
        }

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);

            $this->rebound($abstract);
        } else {
            $extender = $this->getExtender($abstract, $closure);

            $this->bind($abstract, $extender, $this->isShared($abstract));
        }
    }

    /**
     * Get an extender Closure for resolving a type.
     *
     * @param  string    $abstract
     * @param  \Closure  $closure
     * @return \Closure
     */
    protected function getExtender($abstract, Closure $closure)
    {
        $resolver = $this->bindings[$abstract]['concrete'];

        return function($container) use ($resolver, $closure)
        {
            return $closure($resolver($container), $container);
        };
    }

    /**
     * Register an existing instance as a singleton.
     *
     * @param  string  $abstract
     * @param  mixed   $instance
     * @return void
     */
    public function instance($abstract, $instance)
    {
        if (is_array($abstract)) {
            list($abstract, $alias) = $this->extractAlias($abstract);

            $this->alias($abstract, $alias);
        }

        $this->instances[$abstract] = $instance;
    }

    /**
     * Alias a type to a shorter name.
     *
     * @param  string  $abstract
     * @param  string  $alias
     * @return void
     */
    public function alias($abstract, $alias)
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Extract the type and alias from a given definition.
     *
     * @param  array  $definition
     * @return array
     */
    protected function extractAlias(array $definition)
    {
        return array(key($definition), current($definition));
    }

    /**
     * Bind a new callback to an abstract's rebind event.
     *
     * @param  string    $abstract
     * @param  \Closure  $callback
     * @return mixed
     */
    public function rebinding($abstract, Closure $callback)
    {
        $this->reboundCallbacks[$abstract][] = $callback;

        if ($this->bound($abstract)) return $this->make($abstract);
    }

    /**
     * Refresh an instance on the given target and method.
     *
     * @param  string  $abstract
     * @param  mixed   $target
     * @param  string  $method
     * @return mixed
     */
    public function refresh($abstract, $target, $method)
    {
        return $this->rebinding($abstract, function($app, $instance) use ($target, $method)
        {
            call_user_func(array($target, $method), $instance);
        });
    }

    /**
     * Fire the "rebound" callbacks for the given abstract type.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function rebound($abstract)
    {
        $instance = $this->make($abstract);

        foreach ($this->getReboundCallbacks($abstract) as $callback) {
            call_user_func($callback, $this, $instance);
        }
    }

    /**
     * Get the rebound callbacks for a given type.
     *
     * @param  string  $abstract
     * @return array
     */
    protected function getReboundCallbacks($abstract)
    {
        if (isset($this->reboundCallbacks[$abstract])) {
            return $this->reboundCallbacks[$abstract];
        }

        return array();
    }

    /**
     * Call the given Closure / class@method and inject its dependencies.
     *
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     */
    public function call($callback, array $parameters = array(), $defaultMethod = null)
    {
        if ($this->isCallableWithAtSign($callback) || $defaultMethod) {
            return $this->callClass($callback, $parameters, $defaultMethod);
        }

        $dependencies = $this->getMethodDependencies($callback, $parameters);

        return call_user_func_array($callback, $dependencies);
    }

    /**
     * Determine if the given string is in Class@method syntax.
     *
     * @param  mixed  $callback
     * @return bool
     */
    protected function isCallableWithAtSign($callback)
    {
        if (! is_string($callback)) {
            return false;
        }

        return (strpos($callback, '@') !== false);
    }

    /**
     * Get all dependencies for a given method.
     *
     * @param  callable|string  $callback
     * @param  array  $parameters
     * @return array
     */
    protected function getMethodDependencies($callback, array $parameters = array())
    {
        $dependencies = array();

        foreach ($this->getCallReflector($callback)->getParameters() as $key => $parameter) {
            $this->addDependencyForCallParameter($parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, $parameters);
    }

    /**
     * Get the proper reflection instance for the given callback.
     *
     * @param  callable|string  $callback
     * @return \ReflectionFunctionAbstract
     */
    protected function getCallReflector($callback)
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = explode('::', $callback);
        }

        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        }

        return new ReflectionFunction($callback);
    }

    /**
     * Get the dependency for the given call parameter.
     *
     * @param  \ReflectionParameter  $parameter
     * @param  array  $parameters
     * @param  array  $dependencies
     * @return mixed
     */
    protected function addDependencyForCallParameter(ReflectionParameter $parameter, array &$parameters, &$dependencies)
    {
        $name = $parameter->getName();

        if (array_key_exists($name, $parameters)) {
            $dependencies[] = $parameters[$name];

            unset($parameters[$name]);
        } else if (! is_null($class = $parameter->getClass())) {
            $dependencies[] = $this->make($class->getName());
        } else if ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        }
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     *
     * @param  string  $target
     * @param  array  $parameters
     * @param  string|null  $defaultMethod
     * @return mixed
     */
    protected function callClass($target, array $parameters = array(), $defaultMethod = null)
    {
        list ($className, $method) = array_pad(explode('@', $target, 2), 2, $defaultMethod);

        if (is_null($method)) {
            throw new InvalidArgumentException('Method not provided.');
        }

        return $this->call(array($this->make($className), $method), $parameters);
    }

    /**
     * Resolve a given type to an instance.
     *
     * @param  string  $abstract
     * @return mixed
     */
    public function make($abstract)
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        $concrete = $this->getConcrete($abstract);

        if ($this->isBuildable($concrete, $abstract)) {
            $instance = $this->build($concrete);
        } else {
            $instance = $this->make($concrete);
        }

        if ($this->isShared($abstract)) {
            $this->instances[$abstract] = $instance;
        }

        $this->resolved[$abstract] = true;

        return $instance;
    }

    /**
     * Get the concrete type for a given abstract.
     *
     * @param  string  $abstract
     * @return mixed   $concrete
     */
    protected function getConcrete($abstract)
    {
        if (! isset($this->bindings[$abstract])) {
            return $abstract;
        }

        return $this->bindings[$abstract]['concrete'];
    }

    /**
     * Instantiate an instance of the given type.
     *
     * @param  string  $concrete
     * @param  array   $parameters
     * @return mixed
     */
    protected function build($concrete)
    {
        if ($concrete instanceof Closure) {
            return call_user_func($concrete, $this);
        }

        $reflector = new ReflectionClass($concrete);

        if (! $reflector->isInstantiable()) {
            throw new BindingResolutionException("Resolution target [$concrete] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();

        $arguments = $this->getDependencies($dependencies);

        return $reflector->newInstanceArgs($arguments);
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     *
     * @param  array  $parameters
     * @return array
     */
    protected function getDependencies($parameters)
    {
        $dependencies = array();

        foreach ($parameters as $parameter) {
            if (is_null($dependency = $parameter->getClass())) {
                $dependencies[] = $this->resolveNonClass($parameter);
            } else {
                $dependencies[] = $this->resolveClass($parameter);
            }
        }

        return $dependencies;
    }

    /**
     * Resolves optional parameters for our dependency injection
     *
     * @param ReflectionParameter
     * @return default value
     */
    protected function resolveNonClass(ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new BindingResolutionException("Unresolvable dependency resolving [$parameter].");
    }

    /**
     * Resolve a class based dependency from the container.
     *
     * @param  \ReflectionParameter  $parameter
     * @return mixed
     *
     * @throws BindingResolutionException
     */
    protected function resolveClass(ReflectionParameter $parameter)
    {
        try {
            $concrete = $parameter->getClass()->name;

            return $this->make($concrete);
        }
        catch (BindingResolutionException $e) {
            if (! $parameter->isOptional()) {
                throw $e;
            }
        }

        return $parameter->getDefaultValue();
    }

    /**
     * Determine if a given type is shared.
     *
     * @param  string  $abstract
     * @return bool
     */
    public function isShared($abstract)
    {
        if (isset($this->bindings[$abstract]['shared'])) {
            $shared = $this->bindings[$abstract]['shared'];
        } else {
            $shared = false;
        }

        return isset($this->instances[$abstract]) || ($shared === true);
    }

    /**
     * Determine if the given concrete is buildable.
     *
     * @param  mixed   $concrete
     * @param  string  $abstract
     * @return bool
     */
    protected function isBuildable($concrete, $abstract)
    {
        return ($concrete === $abstract) || ($concrete instanceof Closure);
    }

    /**
     * Get the alias for an abstract if available.
     *
     * @param  string  $abstract
     * @return string
     */
    protected function getAlias($abstract)
    {
        return isset($this->aliases[$abstract]) ? $this->aliases[$abstract] : $abstract;
    }

    /**
     * Get the container's bindings.
     *
     * @return array
     */
    public function getBindings()
    {
        return $this->bindings;
    }

    /**
     * Drop all of the stale instances and aliases.
     *
     * @param  string  $abstract
     * @return void
     */
    protected function dropStaleInstances($abstract)
    {
        unset($this->instances[$abstract]);

        unset($this->aliases[$abstract]);
    }

    /**
     * Set the globally available instance of the container.
     *
     * @return static
     */
    public static function getInstance()
    {
        return static::$instance;
    }

    /**
     * Set the shared instance of the container.
     *
     * @param  \Mini\Container\Container  $container
     * @return void
     */
    public static function setInstance(Container $container)
    {
        static::$instance = $container;
    }

    /**
     * Sets a parameter or an object.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function offsetSet($key, $value)
    {
        if (! $value instanceof Closure) {
            $value = function() use ($value)
            {
                return $value;
            };
        }

        $this->bind($key, $value);
    }

    /**
     * Gets a parameter or an object.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function offsetGet($key)
    {
        return $this->make($key);
    }

    /**
     * Checks if a parameter or an object is set.
     *
     * @param string $key
     *
     * @return bool
     */
    public function offsetExists($key)
    {
        return isset($this->bindings[$key]);
    }

    /**
     * Unsets a parameter or an object.
     *
     * @param string $key
     */
    public function offsetUnset($key)
    {
        unset($this->bindings[$key]);

        unset($this->instances[$key]);
    }

}
