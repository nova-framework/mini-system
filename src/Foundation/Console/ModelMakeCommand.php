<?php

namespace Mini\Foundation\Console;

use Mini\Console\Command;
use Mini\Filesystem\Filesystem;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class ModelMakeCommand extends Command
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'make:model';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Create a new ORM Model";

	/**
	 * Create a new command creator command.
	 *
	 * @param  \Mini\Filesystem\Filesystem  $files
	 * @return void
	 */
	public function __construct(Filesystem $files)
	{
		parent::__construct();

		$this->files = $files;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$path = $this->getPath();

		$stub = $this->files->get(__DIR__ .DS .'stubs' .DS .'model.stub');

		//
		$file = $path .DS .$this->input->getArgument('name').'.php';

		$this->writeCommand($file, $stub);
	}

	/**
	 * Write the finished command file to disk.
	 *
	 * @param  string  $file
	 * @param  string  $stub
	 * @return void
	 */
	protected function writeCommand($file, $stub)
	{
		if (! $this->files->exists($file)) {
			$this->files->put($file, $this->formatStub($stub));

			$this->info('Model created successfully.');
		} else {
			$this->error('Model already exists!');
		}
	}

	/**
	 * Format the command class stub.
	 *
	 * @param  string  $stub
	 * @return string
	 */
	protected function formatStub($stub)
	{
		$stub = str_replace('{{className}}', $this->input->getArgument('name'), $stub);

		return $this->addNamespace($stub);
	}

	/**
	 * Add the proper namespace to the command.
	 *
	 * @param  string  $stub
	 * @return string
	 */
	protected function addNamespace($stub)
	{
		$namespace = $this->input->getOption('namespace');

		if (! is_null($namespace)) {
			return str_replace('{{namespace}}', 'App\Models\\' .$namespace, $stub);
		} else {
			return str_replace('{{namespace}}', 'App\Models', $stub);
		}
	}

	/**
	 * Get the path where the command should be stored.
	 *
	 * @return string
	 */
	protected function getPath()
	{
		$path = $this->input->getOption('path');

		if (is_null($path)) {
			return $this->container['path'] .DS .'Models';
		} else {
			return $this->container['path.base'] .DS .$path;
		}
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('name', InputArgument::REQUIRED, 'The name of the Model.'),
		);
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('path', null, InputOption::VALUE_OPTIONAL, 'The path where the Model should be stored.', null),
			array('namespace', null, InputOption::VALUE_OPTIONAL, 'The Model namespace.', null),
		);
	}

}
