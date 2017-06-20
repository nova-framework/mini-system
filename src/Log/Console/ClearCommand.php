<?php

namespace Mini\Log\Console;

use Mini\Console\Command;
use Mini\Filesystem\Filesystem;


class ClearCommand extends Command
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'log:clear';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Flush the Application logs";

	/**
	 * The File System instance.
	 *
	 * @var \Mini\Filesystem\Filesystem
	 */
	protected $files;

	/**
	 * Create a new Cache Clear Command instance.
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
		$path = $this->container['path.storage'] .DS .'logs' .DS .'framework.log';

		$this->files->delete($path);

		//
		$this->info('The Application logs was cleared!');
	}

}
