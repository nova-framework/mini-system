<?php

namespace Mini\Foundation\Console;

use Mini\Console\GeneratorCommand;


class EventMakeCommand extends GeneratorCommand
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'make:event';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new Event class';

	/**
	 * The type of class being generated.
	 *
	 * @var string
	 */
	protected $type = 'Event';


	/**
	 * Execute the command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$this->ensureExistsBaseClass();

		//
		parent::fire();
	}

	/**
	 * Determine if the class already exists.
	 *
	 * @param  string  $rawName
	 * @return bool
	 */
	protected function alreadyExists($rawName)
	{
		return class_exists($rawName);
	}

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
		return realpath(__DIR__) .str_replace('/', DS, '/stubs/event.stub');
	}

	/**
	 * Get the default namespace for the class.
	 *
	 * @param  string  $rootNamespace
	 * @return string
	 */
	protected function getDefaultNamespace($rootNamespace)
	{
		return $rootNamespace .'\Events';
	}

	/**
	 * Ensure that exists the base class \App\Events\Event
	 *
	 * @return void
	 */
	protected function ensureExistsBaseClass()
	{
		$path = $this->getPath('Events\Event');

		if ($this->files->exists($path)) {
			return;
		}

		$rootNamespace = $this->container->getNamespace();

		$content = "<?php
namespace {$rootNamespace}Events;


abstract class Event
{
	//
}
";

		$this->files->put($path, $content);
	}
}
