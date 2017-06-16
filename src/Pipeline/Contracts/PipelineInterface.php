<?php

namespace Mini\Pipeline\Contracts;

use Closure;


interface PipelineInterface
{

	/**
	 * Set the stops of the pipeline.
	 *
	 * @param  array|array  $pipes
	 * @return $this
	 */
	public function through($pipes);

	/**
	 * Run the pipeline with a final destination callback.
	 *
	 * @param  mixed  $passable
	 * @param  \Closure  $destination
	 * @return mixed
	 */
	public function dispatch($passable, Closure $destination);

}
