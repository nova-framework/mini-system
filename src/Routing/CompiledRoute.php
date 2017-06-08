<?php

namespace Mini\Routing;


class CompiledRoute
{
	/**
	 * The regex pattern the route responds to.
	 *
	 * @var string
	 */
	protected $regex;

	/**
	 * The regex pattern the route responds to.
	 *
	 * @var string
	 */
	protected $hostRegex;

	/**
	 * The parameter names of the route.
	 *
	 * @var array|null
	 */
	protected $variables;


	public function __construct($regex, $hostRegex, array $variables)
	{
		$this->regex		= $regex;
		$this->hostRegex	= $hostRegex;
		$this->variables	= $variables;
	}

	public function getRegex()
	{
		return $this->regex;
	}

	public function getHostRegex()
	{
		return $this->hostRegex;
	}

	public function getVariables()
	{
		return $this->variables;
	}
}
