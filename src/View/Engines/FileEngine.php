<?php

namespace Mini\View\Engines;

use Mini\View\Contracts\EngineInterface;

use Exception;


class FileEngine implements EngineInterface
{
	/**
	 * Get the evaluated contents of the view.
	 *
	 * @param  string  $path
	 * @param  array   $data
	 * @return string
	 */
	public function get($path, array $data = array())
	{
		return file_get_contents($path);
	}
}
