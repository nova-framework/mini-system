<?php

namespace Mini\Bus\Contracts;


interface Dispatcher
{

	/**
	 * Dispatch a command to its appropriate handler.
	 *
	 * @param  mixed  $command
	 * @param  mixed  $handler
	 * @return mixed
	 */
	public function dispatch($command, $handler = null);

	/**
	 * Set the pipes commands should be piped through before dispatching.
	 *
	 * @param  array  $pipes
	 * @return $this
	 */
	public function pipeThrough(array $pipes);

	/**
	 * Register command to handler mappings.
	 *
	 * @param  array  $commands
	 * @return void
	 */
	public function map(array $commands);

}
