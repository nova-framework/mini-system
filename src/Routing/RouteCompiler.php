<?php

namespace Mini\Routing;

use Mini\Routing\CompiledRoute;
use Mini\Routing\Route;

use DomainException;
use LogicException;


class RouteCompiler
{
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

		//
		$patterns = $route->getWheres();

		// If the Route has a domain defined, compile the host's regex and variables.
		if (! is_null($domain = $route->domain())) {
			list ($hostRegex, $hostVariables) = static::compilePattern($domain, $patterns, true);
		}

		// Compile the path's regex and variables.
		list ($regex, $variables) = static::compilePattern($route->getUri(), $patterns, false);

		if (! empty($hostVariables)) {
			$variables = array_merge($hostVariables, $variables);
		}

		return new CompiledRoute(
			$regex, $hostRegex, array_unique($variables)
		);
	}

	/**
	 * Compile an host or path pattern to a valid regexp.
	 *
	 * @param  string	$regex
	 * @param  array	$patterns
	 * @param  bool		$isHost
	 * @return string
	 *
	 * @throw \LogicException
	 */
	protected static function compilePattern($regex, $patterns, $isHost)
	{
		$optionals = 0;

		//
		$separator = $isHost ? '\.' : '/';

		$variables = array();

		$result = preg_replace_callback('#' .$separator .'\{(.*?)(\?)?\}#', function ($matches) use ($regex, $patterns, $separator, &$optionals, &$variables)
		{
			@list(, $name, $optional) = $matches;

			// Check if the parameter name is unique.
			if (in_array($name, $variables)) {
				$message = sprintf('Route pattern [%s] cannot reference variable name [%s] more than once.', $regex, $name);

				throw new LogicException($message);
			}

			array_push($variables, $name);

			// Process for the optional parameters.
			$prefix = '';

			if (! is_null($optional)) {
				$prefix = '(?:';

				$optionals++;
			} else if ($optionals > 0) {
				$message = sprintf('Route pattern [%s] cannot reference variable [%s] after one or more optionals.', $regex, $name);

				throw new LogicException($message);
			}

			// Compute the parameter's pattern.
			$pattern = isset($patterns[$name]) ? $patterns[$name] : self::DEFAULT_PATTERN;

			return sprintf('%s%s(?P<%s>%s)', $prefix, $separator, $name, $pattern);

		}, $regex);

		// Adjust the pattern when we have optional parameters.
		if ($optionals > 0) {
			$result .= str_repeat(')?', $optionals);
		}

		return array(
			'#^' .$result .'$#s' .($isHost ? 'i' : ''), $variables
		);
	}
}
