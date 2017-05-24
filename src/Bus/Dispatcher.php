<?php

namespace Mini\Bus;

use Mini\Bus\Contracts\DispatcherInterface;
use Mini\Bus\Contracts\SelfHandlingInterface;
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
	 * The pipeline instance for the bus.
	 *
	 * @var \Mini\Pipeline\Pipeline
	 */
	protected $pipeline;

	/**
	 * The pipes to send commands through before dispatching.
	 *
	 * @var array
	 */
	protected $pipes = array();

	/**
	 * All of the command-to-handler mappings.
	 *
	 * @var array
	 */
	protected $mappings = array();

	/**
	 * The fallback mapping Closure.
	 *
	 * @var \Closure
	 */
	protected $mapper;


	/**
	 * Create a new command dispatcher instance.
	 *
	 * @param  \Mini\Contracts\Container\Container  $container
	 * @return void
	 */
	public function __construct(Container $container)
	{
		$this->container = $container;

		$this->pipeline = new Pipeline($container);
	}

	/**
	 * Dispatch a command to its appropriate handler.
	 *
	 * @param  mixed  $command
	 * @return mixed
	 */
	public function dispatch($command)
	{
		return $this->pipeline->send($command)->through($this->pipes)->then(function ($command)
		{
			if ($command instanceof SelfHandlingInterface)) {
				return $this->container->call(array($command, 'handle'));
			}

			return $this->callHandler($command);
		});
	}

	/**
	 * Get the handler callback for the given command.
	 *
	 * @param mixed $command
	 *
	 * @return mixed
	 */
	protected function callHandler($command)
	{
		$name = get_class($command);

		if (isset($this->mappings[$name])) {
			$handler = $this->mappings[$name];
		} else if (isset($this->mapper)) {
			$handler = call_user_func($this->mapper, $command);
		} else {
			throw new InvalidArgumentException("No handler registered for command [{$name}]");
		}

		return $this->container->call($handler, array($command), 'handle');
	}

	/**
	 * Register command-to-handler mappings.
	 *
	 * @param array $commands
	 *
	 * @return void
	 */
	public function maps(array $commands)
	{
		$this->mappings = array_merge($this->mappings, $commands);
	}

	/**
	 * Register a fallback mapper callback.
	 *
	 * @param \Closure $mapper
	 *
	 * @return void
	 */
	public function mapUsing(Closure $mapper)
	{
		$this->mapper = $mapper;
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
}
