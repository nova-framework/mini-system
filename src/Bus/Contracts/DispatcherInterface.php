<?php

namespace Mini\Bus\Contracts;


interface Dispatcher
{
	/**
	 * Dispatch a command to its appropriate handler.
	 *
	 * @param  mixed  $command
	 * @return mixed
	 */
	public function dispatch($command);

	/**
	 * Set the pipes commands should be piped through before dispatching.
	 *
	 * @param  array  $pipes
	 * @return $this
	 */
	public function pipeThrough(array $pipes);
}
