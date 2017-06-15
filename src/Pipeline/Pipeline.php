<?php

namespace Mini\Pipeline;

use Mini\Container\Container;
use Mini\Pipeline\Contracts\PipelineInterface;

use Closure;


class Pipeline implements PipelineInterface
{
	/**
	 * The container implementation.
	 *
	 * @var \Mini\Container\Container
	 */
	protected $container;


	/**
	 * Create a new class instance.
	 *
	 * @param  \Mini\Container\Container  $container
	 * @return void
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * Run the pipeline with a final destination callback.
	 *
	 * @param  mixed  $passable
	 * @param  array|mixed  $pipes
	 * @param  \Closure  $destination
	 * @return mixed
	 */
	public function dispatch($passable, $pipes, Closure $destination)
	{
		$pipes = is_array($pipes) ? $pipes : array($pipes);

		// Create the slices stack.
		$slice = $this->getInitialSlice($destination);

		foreach(array_reverse($pipes) as $pipe) {
			$slice = $this->getSlice($pipe, $slice);
		}

		return call_user_func($slice, $passable);
	}

	/**
	 * Get the initial slice to begin the stack call.
	 *
	 * @param  \Closure  $destination
	 * @return \Closure
	 */
	protected function getInitialSlice(Closure $destination)
	{
		return function ($passable) use ($destination)
		{
			return call_user_func($destination, $passable);
		};
	}

	/**
	 * Get a Closure that represents a slice of the application onion.
	 *
	 * @param  mixed  $pipe
	 * @param  \Closure  $next
	 * @return \Closure
	 */
	protected function getSlice($pipe, $next)
	{
		return function ($passable) use ($pipe, $next)
		{
			if ($pipe instanceof Closure) {
				return call_user_func($pipe, $passable, $next);
			}

			if (! is_object($pipe)) {
				list($name, $parameters) = $this->parsePipe($pipe);

				$pipe = $this->container->make($name);

				$parameters = array_merge(array($passable, $next), $parameters);
			} else {
				$parameters = array($passable, $next);
			}

			return call_user_func_array(array($pipe, 'handle'), $parameters);
		};
	}

	/**
	 * Parse full pipe string to get name and parameters.
	 *
	 * @param  string $pipe
	 * @return array
	 */
	protected function parsePipe($pipe)
	{
		list($name, $parameters) = array_pad(explode(':', $pipe, 2), 2, array());

		if (is_string($parameters)) {
			$parameters = explode(',', $parameters);
		}

		return array($name, $parameters);
	}
}
