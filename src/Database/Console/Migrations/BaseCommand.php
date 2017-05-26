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

		// First, we will check to see if a path option has been defined. If it has
		// we will use the path relative to the root of this installation folder
		// so that migrations may be run for any path within the applications.
		if (! is_null($path)) {
			return $this->nova['path.base'] .DS .$path;
		}

		$package = $this->input->getOption('package');

		// If the package is in the list of migration paths we received we will put
		// the migrations in that path. Otherwise, we will assume the package is
		// is in the package directories and will place them in that location.
		if (! is_null($package)) {
			return $this->packagePath .DS .$package .DS .'src' .DS .'Migrations';
		}

		return $this->nova['path'] .DS .'Database' .DS .'Migrations';
	}

}
