<?php

namespace Mini\Foundation\Console;

use Mini\Console\GeneratorCommand;

use Symfony\Component\Console\Input\InputOption;


class CommandMakeCommand extends GeneratorCommand
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'make:command';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new Command class';

	/**
	 * The type of class being generated.
	 *
	 * @var string
	 */
	protected $type = 'Command';


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

		if ($this->option('handler')) {
			$this->call('handler:command', array(
				'name' => $this->argument('name') .'Handler',
				'--command' => $this->parseName($this->argument('name')),
			));
		}
	}

	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
		$handler = $this->option('handler');

		if ($handler) {
			return realpath(__DIR__) .str_replace('/', DS, '/stubs/command-with-handler.stub');
		} else {
			return realpath(__DIR__) .str_replace('/', DS, '/stubs/command.stub');
		}
	}

	/**
	 * Get the default namespace for the class.
	 *
	 * @param  string  $rootNamespace
	 * @return string
	 */
	protected function getDefaultNamespace($rootNamespace)
	{
		return $rootNamespace .'\Commands';
	}

	/**
	 * Ensure that exists the base class \App\Commands\Command
	 *
	 * @return void
	 */
	protected function ensureExistsBaseClass()
	{
		$path = $this->getPath('Commands\Command');

		if ($this->files->exists($path)) {
			return;
		}

		$rootNamespace = $this->container->getNamespace();

		$content = "<?php
namespace {$rootNamespace}Commands;


abstract class Command
{
	//
}
";

		$this->files->put($path, $content);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('handler', null, InputOption::VALUE_NONE, 'Indicates that Handler class should be generated.'),
		);
	}
}
