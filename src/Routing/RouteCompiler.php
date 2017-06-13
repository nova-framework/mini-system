<?php

namespace Mini\Routing;

use Mini\Routing\CompiledRoute;
use Mini\Routing\Route;

use LogicException;


class RouteCompiler
{
	const REGEX_DELIMITER = '#';

	/**
	 * The default regex pattern used for the named parameters.
	 *
	 */
	const DEFAULT_PATTERN = '[^/]+';


	/**
	 * Compile an URI pattern to a valid regexp.
	 *
	 * @param  \Mini\Routing\Route  $route
	 * @return string
	 *
	 * @throw \LogicException
	 */
	public static function compile(Route $route)
	{
		$hostRegex = null;

		if (! is_null($domain = $route->domain())) {
			list ($hostRegex, $hostVariables) = static::compilePattern($domain, $route->getWheres(), true);
		}

		list ($regex, $variables) = static::compilePattern($route->getUri(), $route->getWheres(), false);

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
	protected static function compilePattern($pattern, $conditions, $isHost)
	{
		$variables = array();

		$optionals = 0;

		$separator = preg_quote($isHost ? '.' : '/', self::REGEX_DELIMITER);

		$callback = function ($matches) use ($pattern, $conditions, $separator, &$optionals, &$variables)
		{
			@list(, $name, $optional) = $matches;

			if (in_array($name, $variables)) {
				throw new LogicException("Route pattern [$pattern] cannot reference variable name [$name] more than once.");
			}

			$pattern = isset($conditions[$name]) ? $conditions[$name] : self::DEFAULT_PATTERN;

			$regexp = sprintf('%s(?P<%s>%s)', $separator, $name, $pattern);

			if (isset($optional)) {
				$regexp = "(?:$regexp";

				$optionals++;
			} else if ($optionals > 0) {
				throw new LogicException("Route pattern [$pattern] cannot reference variable [$name] after one or more optionals.");
			}

			$variables[] = $name;

			return $regexp;
		};

		$result = preg_replace_callback('#' .$separator .'\{(.*?)(\?)?\}#', $callback, $pattern);

		if ($optionals > 0) {
			$result .= str_repeat(')?', $optionals);
		}

		$regexp = self::REGEX_DELIMITER .'^' .$result .'$' .self::REGEX_DELIMITER .'s' .($isHost ? 'i' : '');

		return array($regexp, $variables);
	}
}
