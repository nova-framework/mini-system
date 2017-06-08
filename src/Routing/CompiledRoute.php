<?php

namespace Mini\Routing;


class CompiledRoute
{
	/**
	 * The uri pattern the route responds to.
	 *
	 * @var string
	 */
	protected $uri;

	/**
	 * The domain the route responds to.
	 *
	 * @var string
	 */
	protected $domain;

	/**
	 * The parameter patterns of the route.
	 *
	 * @var array
	 */
	protected $patterns = array();

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


	public function __construct($uri, $domain, $patterns, $regex, $hostRegex, array $variables)
	{
		$this->uri			= $uri;
		$this->domain		= $domain;
		$this->patterns		= $patterns;
		$this->regex		= $regex;
		$this->hostRegex	= $hostRegex;
		$this->variables	= $variables;
	}

	public function getUri()
	{
		return $this->uri;
	}

	public function getDomain()
	{
		return $this->domain;
	}

	public function getPatterns()
	{
		return $this->patterns;
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
