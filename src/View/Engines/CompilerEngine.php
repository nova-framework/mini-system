<?php

namespace Mini\View\Engines;

use Mini\View\Contracts\CompilerInterface;
use Mini\View\Engines\PhpEngine;
use Mini\Support\Str;

use Symfony\Component\Debug\Exception\FatalThrowableError;

use ErrorException;


class CompilerEngine extends PhpEngine
{
	/**
	 * The Compiler implementation.
	 *
	 * @var \Mini\View\Contracts\CompilerInterface
	 */
	protected $compiler;

	/**
	 * A stack of the last compiled files.
	 *
	 * @var array
	 */
	protected $lastCompiled = array();


	/**
	 * Create a new Compiler Engine instance.
	 *
	 * @param  \Mini\View\Contracts\CompilerInterface  $compiler
	 * @return void
	 */
	public function __construct(CompilerInterface $compiler)
	{
		$this->compiler = $compiler;
	}

	/**
	 * Get the evaluated contents of the View.
	 *
	 * @param  string  $path
	 * @param  array   $data
	 * @return string
	 */
	public function get($path, array $data = array())
	{
		$this->lastCompiled[] = $path;

		if ($this->compiler->isExpired($path)) {
			$this->compiler->compile($path);
		}

		$compiled = $this->compiler->getCompiledPath($path);

		$results = $this->evaluatePath($compiled, $data);

		array_pop($this->lastCompiled);

		return $results;
	}

	/**
	 * Handle a view exception.
	 *
	 * @param  \Exception  $e
	 * @param  int  $obLevel
	 * @return void
	 *
	 * @throws $e
	 */
	protected function handleViewException($e, $obLevel)
	{
		if (! $e instanceof \Exception) {
			$e = new FatalThrowableError($e);
		}

		$e = new \ErrorException($this->getMessage($e), 0, 1, $e->getFile(), $e->getLine(), $e);

		parent::handleViewException($e, $obLevel);
	}

	/**
	 * Get the exception message for an exception.
	 *
	 * @param  \Exception  $e
	 * @return string
	 */
	protected function getMessage($e)
	{
		$path = last($this->lastCompiled);

		return $e->getMessage() .' (View: ' .realpath($path) .')';
	}

	/**
	 * Get the Template implementation.
	 *
	 * @return \Mini\View\Template
	 */
	public function getCompiler()
	{
		return $this->compiler;
	}
}
