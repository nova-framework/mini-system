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
	 * The object being passed through the pipeline.
	 *
	 * @var mixed
	 */
	protected $passable;

	/**
	 * The array of class pipes.
	 *
	 * @var array
	 */
	protected $pipes = array();

	/**
	 * The method to call on each pipe.
	 *
	 * @var string
	 */
	protected $method = 'handle';


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
	 * Set the object being sent through the pipeline.
	 *
	 * @param  mixed  $passable
	 * @return $this
	 */
	public function send($passable)
	{
		$this->passable = $passable;

		return $this;
	}

	/**
	 * Set the array of pipes.
	 *
	 * @param  array|mixed  $pipes
	 * @return $this
	 */
	public function through($pipes)
	{
		$this->pipes = is_array($pipes) ? $pipes : func_get_args();

		return $this;
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
	 * @param  \Closure  $destination
	 * @return mixed
	 */
	public function then(Closure $destination)
	{
		$slice = array_reduce(
			array_reverse($this->pipes), $this->carry(), $this->prepareDestination($destination)
		);

		return call_user_func($slice, $this->passable);
	}

	/**
	 * Get the initial slice to begin the stack call.
	 *
	 * @param  \Closure  $destination
	 * @return \Closure
	 */
	protected function prepareDestination(Closure $destination)
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
	protected function carry()
	{
		return function ($stack, $pipe)
		{
			return $this->getSlice($stack, $pipe);
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
			// If the pipe is an instance of a Closure, we will just call it directly.
			if ($pipe instanceof Closure) {
				return call_user_func($pipe, $passable, $stack);
			}

			// If the pipe is a string, we'll parse resolve it to callable and parameters.
			else if (! is_object($pipe)) {
				list($name, $parameters) = $this->parsePipeString($pipe);

				$pipe = $this->container->make($name);

				$parameters = array_merge(array($passable, $stack), $parameters);
			}

			// If the pipe is already an object, we'll just make a callable and pass it.
			else {
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
	protected function parsePipeString($pipe)
	{
		list($name, $parameters) = array_pad(
			array_map('trim', explode(':', $pipe, 2)), 2, array()
		);

		if (is_string($parameters)) {
			$parameters = array_map('trim',explode(',', $parameters));
		}

		return array($name, $parameters);
	}
}
