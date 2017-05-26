<?php

namespace Mini\Database\Console\Migrations;

use Mini\Console\Command;


class BaseCommand extends Command
{
	/**
	 * Get the path to the migration directory.
	 *
	 * @return string
	 */
	protected function getMigrationPath()
	{
		$path = $this->input->getOption('path');

		if (! is_null($path)) {
			return $this->container['path.base'] .DS .$path;
		}

		$package = $this->input->getOption('package');

		if (! is_null($package)) {
			return $this->packagePath .DS .$package .DS .'src' .DS .'Migrations';
		}

		return $this->container['path'] .DS .'Database' .DS .'Migrations';
	}

}
