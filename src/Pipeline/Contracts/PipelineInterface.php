<?php

namespace Mini\Pipeline\Contracts;

use Closure;


interface PipelineInterface
{
	/**
	 * Set the method to call on the stops.
	 *
	 * @param  string  $method
	 * @return $this
	 */
	public function via($method);

	/**
	 * Run the pipeline with a final destination callback.
	 *
	 * @param  mixed  $passable
	 * @param  array|mixed  $pipes
	 * @param  \Closure  $destination
	 * @return mixed
	 */
	public function dispatch($passable, $pipes, Closure $destination);
}
