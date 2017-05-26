<?php

namespace Mini\Foundation\Console;

use Mini\Console\GeneratorCommand;


class ProviderMakeCommand extends GeneratorCommand
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'make:provider';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new Service Provider class';

	/**
	 * The type of class being generated.
	 *
	 * @var string
	 */
	protected $type = 'Provider';


	/**
	 * Get the stub file for the generator.
	 *
	 * @return string
	 */
	protected function getStub()
	{
		return realpath(__DIR__) .str_replace('/', DS, '/stubs/provider.stub');
	}

	/**
	 * Get the default namespace for the class.
	 *
	 * @param  string  $rootNamespace
	 * @return string
	 */
	protected function getDefaultNamespace($rootNamespace)
	{
		return $rootNamespace .'\Providers';
	}
}
