<?php namespace Mini\Database\Schema;

use Mini\Database\Connection;

use Closure;


class Builder
{
	/**
	 * The database connection instance.
	 *
	 * @var \Mini\Database\Connection
	 */
	protected $connection;

	/**
	 * The schema grammar instance.
	 *
	 * @var \Mini\Database\Schema\Grammars\Grammar
	 */
	protected $grammar;

	/**
	 * The Blueprint resolver callback.
	 *
	 * @var \Closure
	 */
	protected $resolver;

	/**
	 * Create a new database Schema manager.
	 *
	 * @param  \Mini\Database\Connection  $connection
	 * @return void
	 */
	public function __construct(Connection $connection)
	{
		$this->connection = $connection;

		$this->grammar = $connection->getSchemaGrammar();
	}

	/**
	 * Determine if the given table exists.
	 *
	 * @param  string  $table
	 * @return bool
	 */
	public function hasTable($table)
	{
		$sql = $this->grammar->compileTableExists();

		$database = $this->connection->getDatabaseName();

		$table = $this->connection->getTablePrefix() .$table;

		return count($this->connection->select($sql, array($database, $table))) > 0;
	}

	/**
	 * Determine if the given table has a given column.
	 *
	 * @param  string  $table
	 * @param  string  $column
	 * @return bool
	 */
	public function hasColumn($table, $column)
	{
		$column = strtolower($column);

		return in_array($column, array_map('strtolower', $this->getColumnListing($table)));
	}

	/**
	 * Get the column listing for a given table.
	 *
	 * @param  string  $table
	 * @return array
	 */
	public function getColumnListing($table)
	{
		$sql = $this->grammar->compileColumnExists();

		$database = $this->connection->getDatabaseName();

		$table = $this->connection->getTablePrefix() .$table;

		$results = $this->connection->select($sql, array($database, $table));

		return array_map(function($result)
		{
			$result = (object) $result;

			return $result->column_name;

		}, $results);
	}

	/**
	 * Modify a table on the schema.
	 *
	 * @param  string	$table
	 * @param  \Closure  $callback
	 * @return \Mini\Database\Schema\Blueprint
	 */
	public function table($table, Closure $callback)
	{
		$this->build($this->createBlueprint($table, $callback));
	}

	/**
	 * Create a new table on the schema.
	 *
	 * @param  string	$table
	 * @param  \Closure  $callback
	 * @return \Mini\Database\Schema\Blueprint
	 */
	public function create($table, Closure $callback)
	{
		$blueprint = $this->createBlueprint($table);

		$blueprint->create();

		$callback($blueprint);

		$this->build($blueprint);
	}

	/**
	 * Drop a table from the schema.
	 *
	 * @param  string  $table
	 * @return \Mini\Database\Schema\Blueprint
	 */
	public function drop($table)
	{
		$blueprint = $this->createBlueprint($table);

		$blueprint->drop();

		$this->build($blueprint);
	}

	/**
	 * Drop a table from the schema if it exists.
	 *
	 * @param  string  $table
	 * @return \Mini\Database\Schema\Blueprint
	 */
	public function dropIfExists($table)
	{
		$blueprint = $this->createBlueprint($table);

		$blueprint->dropIfExists();

		$this->build($blueprint);
	}

	/**
	 * Rename a table on the schema.
	 *
	 * @param  string  $from
	 * @param  string  $to
	 * @return \Mini\Database\Schema\Blueprint
	 */
	public function rename($from, $to)
	{
		$blueprint = $this->createBlueprint($from);

		$blueprint->rename($to);

		$this->build($blueprint);
	}

	/**
	 * Execute the blueprint to build / modify the table.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @return void
	 */
	protected function build(Blueprint $blueprint)
	{
		$blueprint->build($this->connection, $this->grammar);
	}

	/**
	 * Create a new command set with a Closure.
	 *
	 * @param  string	$table
	 * @param  \Closure  $callback
	 * @return \Mini\Database\Schema\Blueprint
	 */
	protected function createBlueprint($table, Closure $callback = null)
	{
		if (isset($this->resolver))
		{
			return call_user_func($this->resolver, $table, $callback);
		}

		return new Blueprint($table, $callback);
	}

	/**
	 * Get the database connection instance.
	 *
	 * @return \Mini\Database\Connection
	 */
	public function getConnection()
	{
		return $this->connection;
	}

	/**
	 * Set the database connection instance.
	 *
	 * @param  \Mini\Database\Connection
	 * @return $this
	 */
	public function setConnection(Connection $connection)
	{
		$this->connection = $connection;

		return $this;
	}

	/**
	 * Set the Schema Blueprint resolver callback.
	 *
	 * @param  \Closure  $resolver
	 * @return void
	 */
	public function blueprintResolver(Closure $resolver)
	{
		$this->resolver = $resolver;
	}

}
