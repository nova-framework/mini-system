<?php

namespace Mini\Routing;

use Mini\Routing\CompiledRoute;
use Mini\Routing\Route;

use LogicException;


class RouteCompiler
{
	/**
	 * The Route instance.
	 *
	 * @var \Mini\Routing\Route
	 */
	protected $route;

	/**
	 * Create a new Route Compiler instance.
	 *
	 * @param  \Mini\Routing\Route  $route
	 * @return void
	 */
	public function __construct(Route $route)
	{
		$this->route = $route;
	}

	/**
	 * Compile an URI pattern to a valid regexp.
	 *
	 * @param  \Mini\Routing\Route  $route
	 * @return string
	 *
	 * @throw \LogicException
	 */
	public function compile()
	{
		$hostRegex = null;

		if (! is_null($domain = $this->route->domain())) {
			list ($hostRegex, $hostVariables) = $this->compilePattern($domain, $this->route->getWheres(), true);
		}

		list ($regex, $variables) = $this->compilePattern($this->route->getUri(), $this->route->getWheres(), false);

		if (! empty($hostVariables)) {
			$variables = array_unique(
				array_merge($variables, $hostVariables)
			);
		}

		return new CompiledRoute($regex, $hostRegex, $variables);
	}

	/**
	 * Compile an host or URI pattern to a valid regexp.
	 *
	 * @param  string	$pattern
	 * @param  array	$conditions
	 * @param  bool		$isHost
	 * @return string
	 *
	 * @throw \LogicException
	 */
	protected function compilePattern($pattern, $conditions, $isHost)
	{
		$optionals = 0;

		$variables = array();

		$separator = preg_quote($isHost ? '.' : '/', '#');

		$callback = function ($matches) use ($pattern, $conditions, $isHost, $separator, &$optionals, &$variables)
		{
			@list(, $name, $optional) = $matches;

			if (in_array($name, $variables)) {
				throw new LogicException("Route pattern [$pattern] cannot reference variable name [$name] more than once.");
			}

			$variables[] = $name;

			if (isset($conditions[$name])) {
				$condition = $conditions[$name];
			} else {
				$condition = sprintf('[^%s]', $separator);
			}

			if ($isHost) {
				return sprintf('(?P<%s>%s)', $name, $condition);
			}

			$regexp = sprintf('%s(?P<%s>%s)', $separator, $name, $condition);

			if ($optional) {
				$regexp = "(?:$regexp";

				$optionals++;
			} else if ($optionals > 0) {
				throw new LogicException("Route pattern [$pattern] cannot reference variable [$name] after one or more optionals.");
			}

			return $regexp;
		};

		// No optional parameters in a host pattern.
		$regexp = $isHost ? '#\{(.*?)\}#' : '#/\{(.*?)(\?)?\}#';

		$result = preg_replace_callback($regexp, $callback, $pattern);

		//
		$regexp = '#^' .$result .str_repeat(')?', $optionals) .'$#s' .($isHost ? 'i' : '');

		return array($regexp, $variables);
	}
}
