<?php

namespace Mini\Foundation\Console;

use Mini\Console\Command;
use Mini\Foundation\Publishers\ViewPublisher;
use Mini\Plugins\PluginManager;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


class ViewPublishCommand extends Command
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'view:publish';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Publish a package's views to the application";

	/**
	 * The plugins manager instance.
	 *
	 * @var \Mini\Plugins\PluginManager
	 */
	protected $plugins;

	/**
	 * The view publisher instance.
	 *
	 * @var \Mini\Foundation\ViewPublisher
	 */
	protected $publisher;


	/**
	 * Create a new view publish command instance.
	 *
	 * @param  \Mini\Foundation\ViewPublisher  $view
	 * @return void
	 */
	public function __construct(PluginManager $plugins, ViewPublisher $publisher)
	{
		parent::__construct();

		$this->plugins = $plugins;

		$this->publisher = $publisher;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		$package = $this->input->getArgument('package');

		// Direct specification of the package and Views path.
		if (! is_null($path = $this->getPath())) {
			$this->publisher->publish($package, $path);
		}

		// For the packages which are registered as plugins.
		else if ($this->plugins->exists($package)) {
			if (Str::length($package) > 3) {
				$slug = Str::snake($package);
			} else {
				$slug = Str::lower($package);
			}

			$properties = $this->plugins->where('slug', $slug);

			//
			$package = $properties['name'];

			$path = $properties['path'] .str_replace('/', DS, '/src/Views');

			$this->publisher->publish($package, $path);
		}

		// For other packages located in vendor.
		else {
			$this->publisher->publishPackage($package);
		}

		$this->output->writeln('<info>Views published for package:</info> '.$package);
	}

	/**
	 * Get the specified path to the files.
	 *
	 * @return string
	 */
	protected function getPath()
	{
		$path = $this->input->getOption('path');

		if (! is_null($path)) {
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
			array('package', InputArgument::REQUIRED, 'The name of the package being published.'),
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
			array('path', null, InputOption::VALUE_OPTIONAL, 'The path to the source view files.', null),
		);
	}

}
