<?php

namespace Mini\Bus;

use Mini\Bus\Contracts\DispatcherInterface;
use Mini\Pipeline\Pipeline;
use Mini\Container\Container;

use Closure;
use ReflectionMethod;
use RuntimeException;


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
		if (empty($this->pipes)) {
			return $this->callHandler($command);
		}

		return $this->pipeline->send($command)->through($this->pipes)->then(function ($command)
		{
			return $this->callHandler($command);
		});
	}

	/**
	 * Execute the given command handler with method's type-hinted dependencies.
	 *
	 * @param mixed  $command
	 * @param string  $method
	 * @return mixed
	 */
	protected function callHandler($command, $method = 'handle')
	{
		$parameters = array();

		//
		$reflector = new ReflectionMethod($command, $method);

		foreach ($reflector->getParameters() as $parameter) {
			if (! is_null($class = $parameter->getClass())) {
				$parameters[] = $this->container->make($class->name);
			} else if ($parameter->isDefaultValueAvailable()) {
				$parameters[] = $parameter->getDefaultValue();
			} else {
				$parameters[] = null;
			}
		}

		return call_user_func_array(array($command, $method), $parameters);
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
