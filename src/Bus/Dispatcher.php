<?php

namespace Mini\Bus;

use Mini\Bus\Contracts\DispatcherInterface;
use Mini\Container\Container;
use Mini\Pipeline\Pipeline;

use Closure;
use InvalidArgumentException;


class Dispatcher implements DispatcherInterface
{
	/**
	 * The container implementation.
	 *
	 * @var \Mini\Container\Container
	 */
	protected $container;

	/**
	 * The pipes to send commands through before dispatching.
	 *
	 * @var array
	 */
	protected $pipes = array();

	/**
	 * The command to handler mapping for non-self-handling events.
	 *
	 * @var array
	 */
	protected $handlers = array();


	/**
	 * Create a new command dispatcher instance.
	 *
	 * @param  \Mini\Contracts\Container\Container  $container
	 * @return void
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;
	}

	/**
	 * Dispatch a command to its appropriate handler.
	 *
	 * @param  mixed  $command
	 * @param  mixed  $handler
	 * @return mixed
	 */
	public function dispatch($command, $handler = null)
	{
		if (! is_null($handler) || $handler = $this->getCommandHandler($command)) {
			$callback = function ($command) use ($handler)
			{
				return $handler->handle($command);
			});
		} else {
			$callback = function ($command)
			{
				return $this->container->call(array($command, 'handle'));
			}
		}

		$pipeline = new Pipeline($this->container, $this->pipes);

		return $pipeline->dispatch($command, $callback);
	}

	/**
	 * Determine if the given command has a handler.
	 *
	 * @param  mixed  $command
	 * @return bool
	 */
	public function hasCommandHandler($command)
	{
		$name = get_class($command);

		return array_key_exists($name, $this->handlers);
	}

	/**
	 * Retrieve the handler for a command.
	 *
	 * @param  mixed  $command
	 * @return bool|mixed
	 */
	public function getCommandHandler($command)
	{
		$name = get_class($command);

		if (array_key_exists($name, $this->handlers)) {
			$handler = $this->handlers[$name];

			return $this->container->make($handler);
		}

		return false;
	}

	/**
	 * Set the pipes through which commands should be piped before dispatching.
	 *
	 * @param  array  $pipes
	 * @return $this
	 */
	public function pipeThrough(array $pipes)
	{
		$this->pipes = $pipes;

		return $this;
	}

	/**
	 * Map a command to a handler.
	 *
	 * @param  array  $map
	 * @return $this
	 */
	public function map(array $map)
	{
		$this->handlers = array_merge($this->handlers, $map);

		return $this;
	}
}
