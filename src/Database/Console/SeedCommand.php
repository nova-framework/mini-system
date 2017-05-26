<?php

namespace Mini\Database\Console;

use Mini\Console\Command;
use Mini\Console\ConfirmableTrait;
use Mini\Database\Contracts\ConnectionResolverInterface as Resolver;

use Symfony\Component\Console\Input\InputOption;


class SeedCommand extends Command
{
	use ConfirmableTrait;

	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'db:seed';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Seed the database with records';

	/**
	 * The connection resolver instance.
	 *
	 * @var \Mini\Database\Contracts\ConnectionResolverInterface
	 */
	protected $resolver;

	/**
	 * Create a new database seed command instance.
	 *
	 * @param  \Mini\Database\Contracts\ConnectionResolverInterface  $resolver
	 * @return void
	 */
	public function __construct(Resolver $resolver)
	{
		parent::__construct();

		$this->resolver = $resolver;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		if (! $this->confirmToProceed()) return;

		$this->resolver->setDefaultConnection($this->getDatabase());

		$this->getSeeder()->run();
	}

	/**
	 * Get a seeder instance from the container.
	 *
	 * @return \Mini\Database\Seeder
	 */
	protected function getSeeder()
	{
		$class = $this->container->make($this->input->getOption('class'));

		return $class->setContainer($this->container)->setCommand($this);
	}

	/**
	 * Get the name of the database connection to use.
	 *
	 * @return string
	 */
	protected function getDatabase()
	{
		$database = $this->input->getOption('database');

		return $database ?: $this->container['config']['database.default'];
	}

	/**
	 * Get the console command options.
	 *
	 * @return array
	 */
	protected function getOptions()
	{
		return array(
			array('class', null, InputOption::VALUE_OPTIONAL, 'The class name of the root seeder', 'DatabaseSeeder'),
			array('database', null, InputOption::VALUE_OPTIONAL, 'The database connection to seed'),
			array('force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production.'),
		);
	}

}
