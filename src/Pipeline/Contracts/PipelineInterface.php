<?php

namespace Mini\Pipeline\Contracts;

use Closure;


interface PipelineInterface
{

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
