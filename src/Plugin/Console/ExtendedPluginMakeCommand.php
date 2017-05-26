<?php

namespace Mini\Plugin\Console;

use Mini\Plugin\Console\PluginMakeCommand;


class ExtendedPluginMakeCommand extends PluginMakeCommand
{
	/**
	 * The name of the console command.
	 *
	 * @var string
	 */
	protected $name = 'make:plugin:extended';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a new Extended Plugin';

	/**
	 * Plugin folders to be created.
	 *
	 * @var array
	 */
	protected $pluginFolders = array(
		'src/',
		'src/Config/',
		'src/Controllers/',
		'src/Database/',
		'src/Database/Migrations/',
		'src/Database/Seeds/',
		'src/Language/',
		'src/Models/',
		'src/Policies/',
		'src/Providers/',
		'src/Views/',
		'webroot/'
	);

	/**
	 * Plugin files to be created.
	 *
	 * @var array
	 */
	protected $pluginFiles = array(
		'src/Config/Config.php',
		'src/Database/Seeds/DatabaseSeeder.php',
		'src/Providers/PluginServiceProvider.php',
		'src/Bootstrap.php',
		'src/Routes.php',
		'README.md',
		'composer.json'
	);

	/**
	 * Plugin stubs used to populate defined files.
	 *
	 * @var array
	 */
	protected $pluginStubs = array(
		'config',
		'seeder',
		'extended-service-provider',
		'bootstrap',
		'routes',
		'readme',
		'composer'
	);

}
