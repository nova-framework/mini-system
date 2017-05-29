<?php

namespace Mini\Database\Console\Migrations;

use Mini\Console\Command;
use Mini\Console\ConfirmableTrait;
use Symfony\Component\Console\Input\InputOption;

class RefreshCommand extends Command
{
	use ConfirmableTrait;

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'migrate:refresh';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Reset and re-run all migrations';

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		if (! $this->confirmToProceed()) return;

		$database = $this->input->getOption('database');

		$force = $this->input->getOption('force');

		$this->call('migrate:reset', array(
			'--database' => $database, '--force' => $force
		));

		$this->call('migrate', array(
			'--database' => $database, '--force' => $force
		));

		if ($this->needsSeeding()) {
			$this->runSeeder($database);
		}
	}

	/**
	 * Determine if the developer has requested database seeding.
	 *
	 * @return bool
	 */
	protected function needsSeeding()
	{
		return $this->option('seed') || $this->option('seeder');
	}

	/**
	 * Run the database seeder command.
	 *
	 * @param  string  $database
	 * @return void
	 */
	protected function runSeeder($database)
	{
		$class = $this->option('seeder') ?: 'DatabaseSeeder';

		$this->call('db:seed', array('--database' => $database, '--class' => $class));
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('database', null, InputOption::VALUE_OPTIONAL, 'The database connection to use.'),
			array('force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production.'),
			array('seed', null, InputOption::VALUE_NONE, 'Indicates if the seed task should be re-run.'),
			array('seeder', null, InputOption::VALUE_OPTIONAL, 'The class name of the root seeder.'),
		);
	}

}
