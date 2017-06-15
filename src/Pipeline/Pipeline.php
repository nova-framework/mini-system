<?php

namespace Mini\Pipeline;

use Mini\Container\Container;
use Mini\Pipeline\Contracts\PipelineInterface;

use Closure;


class Pipeline implements PipelineInterface
{
	/**
	 * The method to call on each pipe.
	 *
	 * @var string
	 */
	protected $method = 'handle';

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
	 * Set the method to call on the pipes.
	 *
	 * @param  string  $method
	 * @return $this
	 */
	public function via($method)
	{
		$this->method = $method;

		return $this;
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
			$slice = $this->getSlice($slice, $pipe);
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
	 * @return \Closure
	 */
	protected function getSlice($stack, $pipe)
	{
		return function ($passable) use ($stack, $pipe)
		{
			if ($pipe instanceof Closure) {
				return call_user_func($pipe, $passable, $stack);
			}

			if (! is_object($pipe)) {
				list($name, $parameters) = $this->parsePipe($pipe);

				$pipe = $this->container->make($name);

				$parameters = array_merge(array($passable, $stack), $parameters);
			} else {
				$parameters = array($passable, $stack);
			}

			return call_user_func_array(array($pipe, $this->method), $parameters);
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
