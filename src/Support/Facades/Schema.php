<?php

namespace Mini\Support\Facades;


/**
 * @see \Mini\Database\Schema\Builder
 */
class Schema extends Facade
{
	/**
	 * Get a schema builder instance for a connection.
	 *
	 * @param  string  $name
	 * @return \Mini\Database\Schema\Builder
	 */
	public static function connection($name)
	{
		return static::$app['db']->connection($name)->getSchemaBuilder();
	}

	/**
	 * Get the registered name of the component.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return static::$app['db']->connection()->getSchemaBuilder();
	}

}
