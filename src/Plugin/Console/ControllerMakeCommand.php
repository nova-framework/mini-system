<?php

namespace Mini\Plugin\Console;

use Mini\Plugin\Console\MakeCommand;

use Symfony\Component\Console\Input\InputArgument;


class ControllerMakeCommand extends MakeCommand
{
	/**
	 * The name of the console command.
	 *
	 * @var string
	 */
	protected $name = 'make:plugin:controller';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new Plugin Controller class';

	/**
	 * String to store the command type.
	 *
	 * @var string
	 */
	protected $type = 'Controller';

	/**
	 * Plugin folders to be created.
	 *
	 * @var array
	 */
	protected $listFolders = array(
		'Controllers/',
	);

	/**
	 * Plugin files to be created.
	 *
	 * @var array
	 */
	protected $listFiles = array(
		'{{filename}}.php',
	);

	/**
	 * Plugin signature option.
	 *
	 * @var array
	 */
	protected $signOption = array(
		'resource',
	);

	/**
	 * Plugin stubs used to populate defined files.
	 *
	 * @var array
	 */
	protected $listStubs = array(
		'default' => array(
			'controller.stub',
		),
	);

	/**
	 * Resolve Container after getting file path.
	 *
	 * @param string $FilePath
	 *
	 * @return array
	 */
	protected function resolveByPath($filePath)
	{
		$this->data['filename']  = $this->makeFileName($filePath);
		$this->data['namespace'] = $this->getNamespace($filePath);

		$this->data['className'] = basename($filePath);
	}

	/**
	 * Replace placeholder text with correct values.
	 *
	 * @return string
	 */
	protected function formatContent($content)
	{
		$searches = array(
			'{{filename}}',
			'{{namespace}}',
			'{{className}}',
		);

		$replaces = array(
			$this->data['filename'],
			$this->data['namespace'],
			$this->data['className'],
		);

		return str_replace($searches, $replaces, $content);
	}

	/**
	 * Get the console command arguments.
	 *
	 * @return array
	 */
	protected function getArguments()
	{
		return array(
			array('slug', InputArgument::REQUIRED, 'The slug of the Plugin.'),
			array('name', InputArgument::REQUIRED, 'The name of the Controller class.'),
		);
	}
}
