<?php

namespace Mini\Routing;

use ReflectionFunctionAbstract;
use ReflectionMethod;

trait RouteDependencyResolverTrait
{
	/**
	 * Resolve the object method's type-hinted dependencies.
	 *
	 * @param  array  $parameters
	 * @param  object  $instance
	 * @param  string  $method
	 * @return array
	 */
	protected function resolveClassMethodDependencies(array $parameters, $instance, $method)
	{
		if (! method_exists($instance, $method)) {
			return $parameters;
		}

		return $this->resolveMethodDependencies(
			$parameters, new ReflectionMethod($instance, $method)
		);
	}

	/**
	 * Resolve the given method's type-hinted dependencies.
	 *
	 * @param  array  $parameters
	 * @param  \ReflectionFunctionAbstract  $reflector
	 * @return array
	 */
	protected function resolveMethodDependencies(array $parameters, ReflectionFunctionAbstract $reflector)
	{
		$instanceCount = 0;

		$values = array_values($parameters);

		foreach ($reflector->getParameters() as $key => $parameter) {
			if (! is_null($class = $parameter->getClass())) {
				$instance = $this->container->make($class->getName());

				$instanceCount++;

				$this->spliceIntoParameters($parameters, $key, $instance);
			} else if (! isset($values[$key - $instanceCount]) && $parameter->isDefaultValueAvailable()) {
				$this->spliceIntoParameters($parameters, $key, $parameter->getDefaultValue());
			}
		}

		return array_values($parameters);
	}

	/**
	 * Splice the given value into the parameter list.
	 *
	 * @param  array  $parameters
	 * @param  string  $offset
	 * @param  mixed  $value
	 * @return void
	 */
	protected function spliceIntoParameters(array &$parameters, $offset, $value)
	{
		array_splice($parameters, $offset, 0, array($value));
	}
}
