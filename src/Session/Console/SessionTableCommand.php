<?php

namespace Mini\Session\Console;

use Mini\Console\Command;
use Mini\Filesystem\Filesystem;

class SessionTableCommand extends Command
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'session:table';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Create a migration for the session database table';

	/**
	 * The filesystem instance.
	 *
	 * @var \Mini\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * Create a new session table command instance.
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
		$fullPath = $this->createBaseMigration();

		$this->files->put($fullPath, $this->files->get(__DIR__ .DS .'stubs' .DS .'database.stub'));

		$this->info('Migration created successfully!');

		$this->call('optimize');
	}

	/**
	 * Create a base migration file for the session.
	 *
	 * @return string
	 */
	protected function createBaseMigration()
	{
		$name = 'create_session_table';

		$path = $this->container['path'] .DS .'Database' .DS .'Migrations';

		return $this->container['migration.creator']->create($name, $path);
	}

}
