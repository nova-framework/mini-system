<?php

namespace Mini\Foundation\Console;

use Mini\Console\Command;
use Mini\Foundation\Publishers\AssetPublisher;
use Mini\Routing\Assets\Router as AssetRouter;
use Mini\Support\Str;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Finder\Finder;


class AssetPublishCommand extends Command
{
	/**
	 * The console command name.
	 *
	 * @var string
	 */
	protected $name = 'asset:publish';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = "Publish a package's assets to the public directory";

	/**
	 * The asset router instance.
	 *
	 * @var \Mini\Routing\Assets\Router
	 */
	protected $router;

	/**
	 * The asset publisher instance.
	 *
	 * @var \Mini\Foundation\AssetPublisher
	 */
	protected $assets;


	/**
	 * Create a new asset publish command instance.
	 *
	 * @param  \Mini\Foundation\AssetPublisher  $assets
	 * @return void
	 */
	public function __construct(AssetRouter $router, AssetPublisher $assets)
	{
		parent::__construct();

		$this->router = $router;

		$this->assets = $assets;
	}

	/**
	 * Execute the console command.
	 *
	 * @return void
	 */
	public function fire()
	{
		foreach ($this->getPackages() as $package) {
			$this->publishAssets($package);
		}
	}

	/**
	 * Publish the assets for a given package name.
	 *
	 * @param  string  $package
	 * @return void
	 */
	protected function publishAssets($package)
	{
		if ( ! is_null($path = $this->getPath())) {
			$this->assets->publish($package, $path);
		} else {
			$path = $this->router->getNamespace($package);

			$this->assets->publishPackage($package, $path);
		}

		$this->output->writeln('<info>Assets published for package:</info> '.$package);
	}

	/**
	 * Get the name of the package being published.
	 *
	 * @return array
	 */
	protected function getPackages()
	{
		if (! is_null($package = $this->input->getArgument('package'))) {
			if (Str::length($package) > 3) {
				$package = Str::snake($package, '-');
			} else {
				$package = Str::lower($package);
			}

			return array($package);
		}

		return $this->findAllAssetPackages();
	}

	/**
	 * Find all the asset hosting packages in the system.
	 *
	 * @return array
	 */
	protected function findAllAssetPackages()
	{
		$vendor = $this->container['path.base'] .DS .'vendor';

		$packages = array();

		//
		$namespaces = $this->router->getNamespaces();

		foreach ($namespaces as $name => $hint) {
			$packages[] = $name;
		}

		return $packages;
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
			array('package', InputArgument::OPTIONAL, 'The name of package being published.'),
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
			array('path', null, InputOption::VALUE_OPTIONAL, 'The path to the asset files.', null),
		);
	}
}
