<?php
/**
 * Connection - A PDO based Database Connection.
 *
 * @author Virgil-Adrian Teaca - virgil@giulianaeassociati.com
 * @version 3.0
 */

namespace Mini\Database;

use Mini\Database\Contracts\ConnectionInterface;
use Mini\Database\Contracts\ConnectorInterface;
use Mini\Database\Connector;
use Mini\Database\Query\Expression;
use Mini\Database\Query\Builder as QueryBuilder;
use Mini\Database\Query\Grammar as QueryGrammar;
use Mini\Database\Schema\Builder as SchemaBuilder;
use Mini\Database\Schema\Grammar as SchemaGrammar;
use Mini\Database\Grammar;
use Mini\Database\QueryException;
use Mini\Support\Arr;

use Closure;
use PDO;
use DateTimeInterface;


class Connection implements ConnectionInterface
{

	/**
	 * The Connector instance.
	 *
	 * @var \Mini\Database\Contracts\ConnectorInterface
	 */
	protected $connector;

	/**
	 * The active PDO Connection.
	 *
	 * @var PDO
	 */
	protected $pdo;

	/**
	 * The event dispatcher instance.
	 *
	 * @var \Events\Dispatcher
	 */
	protected $events;

	/**
	 * The query grammar implementation.
	 *
	 * @var \Mini\Database\Query\Grammar
	 */
	protected $queryGrammar;

	/**
	 * The schema grammar implementation.
	 *
	 * @var \Mini\Database\Schema\Grammar
	 */
	protected $schemaGrammar;

	/**
	 * The paginator environment instance.
	 *
	 * @var \Pagination\Paginator
	 */
	protected $paginator;

	/**
	 * The cache manager instance.
	 *
	 * @var \Cache\CacheManager
	 */
	protected $cache;

	/**
	 * The default fetch mode of the Connection.
	 *
	 * @var int
	 */
	protected $fetchMode = PDO::FETCH_OBJ;

	/**
	 * The number of active transactions.
	 *
	 * @var int
	 */
	protected $transactions = 0;

	/**
	 * All of the queries run against the connection.
	 *
	 * @var array
	 */
	protected $queryLog = array();

	/**
	 * Indicates whether queries are being logged.
	 *
	 * @var bool
	 */
	protected $loggingQueries = true;

	/**
	 * Indicates if the connection is in a "dry run".
	 *
	 * @var bool
	 */
	protected $pretending = false;

	/**
	 * The name of the connected Database.
	 *
	 * @var string
	 */
	protected $database;

	/**
	 * The table prefix for the Connection.
	 *
	 * @var string
	 */
	protected $tablePrefix = '';

	/**
	 * The database connection configuration options.
	 *
	 * @var array
	 */
	protected $config = array();

	/**
	 * Create a new Connection instance.
	 *
	 * @param  array  $config
	 * @return void
	 */
	public function __construct(array $config)
	{
		$this->config = $config;

		$this->database = $config['database'];

		$this->tablePrefix = $config['prefix'];

		//
		$this->queryGrammar = $this->withTablePrefix(new QueryGrammar());

		$this->connector = $this->createConnector($config);

		$this->pdo = $this->createConnection($config);
	}

	/**
	 * Create a connector instance based on the configuration.
	 *
	 * @param  array  $config
	 * @return \Mini\Database\Connectors\ConnectorInterface
	 *
	 * @throws \InvalidArgumentException
	 */
	public function createConnector(array $config)
	{
		return new Connector();
	}

	/**
	 * Create a new PDO connection.
	 *
	 * @param  array   $config
	 * @return PDO
	 */
	public function createConnection(array $config)
	{
		return $this->connector->connect($config);
	}

	/**
	 * Get a schema builder instance for the connection.
	 *
	 * @return \Nova\Database\Schema\Builder
	 */
	public function getSchemaBuilder()
	{
		if (! isset($this->schemaGrammar)) {
			$this->schemaGrammar = $this->withTablePrefix(new SchemaGrammar());
		}

		return new SchemaBuilder($this);
	}

	/**
	 * Begin a Fluent Query against a database table.
	 *
	 * @param  string  $table
	 * @return \Mini\Database\Query\Builder
	 */
	public function table($table)
	{
		$query = new QueryBuilder($this, $this->getQueryGrammar());

		return $query->from($table);
	}

	/**
	 * Get a new raw query expression.
	 *
	 * @param  mixed  $value
	 * @return \Mini\Database\Query\Expression
	 */
	public function raw($value)
	{
		return new Expression($value);
	}

	/**
	 * Run a select statement and return a single result.
	 *
	 * @param  string  $query
	 * @param  array   $bindings
	 * @return mixed
	 */
	public function selectOne($query, $bindings = array())
	{
		$records = $this->select($query, $bindings);

		return (count($records) > 0) ? reset($records) : null;
	}

	/**
	 * Run a select statement against the database.
	 *
	 * @param  string  $query
	 * @param  array   $bindings
	 * @return array
	 */
	public function select($query, $bindings = array())
	{
		return $this->run($query, $bindings, function($me, $query, $bindings)
		{
			if ($me->pretending()) return array();

			//
			$statement = $me->getPdo()->prepare($query);

			$bindings = $this->prepareBindings($bindings);

			$statement->execute($bindings);

			return $statement->fetchAll($me->getFetchMode());
		});
	}

	/**
	 * Run an insert statement against the database.
	 *
	 * @param  string  $query
	 * @param  array   $bindings
	 * @return bool
	 */
	public function insert($query, $bindings = array())
	{
		return $this->statement($query, $bindings);
	}

	/**
	 * Run an update statement against the database.
	 *
	 * @param  string  $query
	 * @param  array   $bindings
	 * @return int
	 */
	public function update($query, $bindings = array())
	{
		return $this->affectingStatement($query, $bindings);
	}

	/**
	 * Run a delete statement against the database.
	 *
	 * @param  string  $query
	 * @param  array   $bindings
	 * @return int
	 */
	public function delete($query, $bindings = array())
	{
		return $this->affectingStatement($query, $bindings);
	}

	/**
	 * Execute an SQL statement and return the boolean result.
	 *
	 * @param  string  $query
	 * @param  array   $bindings
	 * @return bool
	 */
	public function statement($query, $bindings = array())
	{
		return $this->run($query, $bindings, function($me, $query, $bindings)
		{
			if ($me->pretending()) return true;

			//
			$statement = $me->getPdo()->prepare($query);

			$bindings = $me->prepareBindings($bindings);

			return $statement->execute($bindings);
		});
	}

	/**
	 * Run an SQL statement and get the number of rows affected.
	 *
	 * @param  string  $query
	 * @param  array   $bindings
	 * @return int
	 */
	public function affectingStatement($query, $bindings = array())
	{
		return $this->run($query, $bindings, function($me, $query, $bindings)
		{
			if ($me->pretending()) return 0;

			//
			$statement = $me->getPdo()->prepare($query);

			$bindings = $me->prepareBindings($bindings);

			$statement->execute($bindings);

			return $statement->rowCount();
		});
	}

	/**
	 * Run a raw, unprepared query against the PDO connection.
	 *
	 * @param  string  $query
	 * @return bool
	 */
	public function unprepared($query)
	{
		return $this->run($query, array(), function($me, $query, $bindings)
		{
			if ($me->pretending()) return true;

			return (bool) $me->getPdo()->exec($query);
		});
	}

	/**
	 * Prepare the query bindings for execution.
	 *
	 * @param  array  $bindings
	 * @return array
	 */
	public function prepareBindings(array $bindings)
	{
		$grammar = $this->getQueryGrammar();

		foreach ($bindings as $key => $value) {
			if ($value instanceof DateTimeInterface) {
				// We need to transform all DateTime instances into an actual date string.
				$bindings[$key] = $value->format($grammar->getDateFormat());
			} else if ($value === false) {
				$bindings[$key] = 0;
			}
		}

		return $bindings;
	}

	/**
	 * Execute a Closure within a transaction.
	 *
	 * @param  Closure  $callback
	 * @return mixed
	 *
	 * @throws \Exception
	 */
	public function transaction(Closure $callback)
	{
		$this->beginTransaction();

		try {
			$result = $callback($this);

			$this->commit();
		} catch (\Exception $e) {
			$this->rollBack();

			throw $e;
		}

		return $result;
	}

	/**
	 * Start a new database transaction.
	 *
	 * @return void
	 */
	public function beginTransaction()
	{
		++$this->transactions;

		if ($this->transactions == 1) {
			$this->pdo->beginTransaction();
		}
	}

	/**
	 * Commit the active database transaction.
	 *
	 * @return void
	 */
	public function commit()
	{
		if ($this->transactions == 1) $this->pdo->commit();

		--$this->transactions;
	}

	/**
	 * Rollback the active database transaction.
	 *
	 * @return void
	 */
	public function rollBack()
	{
		if ($this->transactions == 1) {
			$this->transactions = 0;

			$this->pdo->rollBack();
		} else {
			--$this->transactions;
		}
	}

	/**
	 * Get the number of active transactions.
	 *
	 * @return int
	 */
	public function transactionLevel()
	{
		return $this->transactions;
	}

	/**
	 * Execute the given callback in "dry run" mode.
	 *
	 * @param  \Closure  $callback
	 * @return array
	 */
	public function pretend(Closure $callback)
	{
		$this->pretending = true;

		$this->queryLog = array();

		// Execute the Callback in the pretend mode.
		$callback($this);

		$this->pretending = false;

		return $this->queryLog;
	}

	/**
	 * Run a SQL statement and log its execution context.
	 *
	 * @param  string	$query
	 * @param  array	 $bindings
	 * @param  \Closure  $callback
	 * @return mixed
	 *
	 * @throws \Database\QueryException
	 */
	protected function run($query, $bindings, Closure $callback)
	{
		$start = microtime(true);

		$result = $this->runQueryCallback($query, $bindings, $callback);

		$time = $this->getElapsedTime($start);

		$this->logQuery($query, $bindings, $time);

		return $result;
	}

	/**
	 * Run a SQL statement.
	 *
	 * @param  string	$query
	 * @param  array	 $bindings
	 * @param  \Closure  $callback
	 * @return mixed
	 *
	 * @throws \Database\QueryException
	 */
	protected function runQueryCallback($query, $bindings, Closure $callback)
	{
		try {
			$result = $callback($this, $query, $bindings);
		} catch (\Exception $e) {
			throw new QueryException($query, $this->prepareBindings($bindings), $e);
		}

		return $result;
	}

	/**
	 * Log a query in the connection's query log.
	 *
	 * @param  string  $query
	 * @param  array   $bindings
	 * @param  float|null  $time
	 * @return void
	 */
	public function logQuery($query, $bindings, $time = null)
	{
		if (! $this->loggingQueries) return;

		$this->queryLog[] = compact('query', 'bindings', 'time');
	}

	/**
	 * Get the elapsed time since a given starting point.
	 *
	 * @param  int	$start
	 * @return float
	 */
	protected function getElapsedTime($start)
	{
		return round((microtime(true) - $start) * 1000, 2);
	}

	/**
	 * Get the current configuration for the Connection.
	 *
	 * @return array
	 */
	public function getConfig($option)
	{
		return Arr::get($this->config, $option);
	}

	/**
	 * Get the name of the connected Database.
	 *
	 * @return string
	 */
	public function getDatabaseName()
	{
		return $this->database;
	}

	/**
	 * Get the table prefix for the Connection.
	 *
	 * @return string
	 */
	public function getTablePrefix()
	{
		return $this->tablePrefix;
	}

	/**
	 * Set the table prefix in use by the connection.
	 *
	 * @param  string  $prefix
	 * @return void
	 */
	public function setTablePrefix($prefix)
	{
		$this->tablePrefix = $prefix;

		$this->getQueryGrammar()->setTablePrefix($prefix);
	}

	/**
	 * Set the table prefix and return the grammar.
	 *
	 * @param  \Nova\Database\Grammar  $grammar
	 * @return \Nova\Database\Grammar
	 */
	public function withTablePrefix(Grammar $grammar)
	{
		$grammar->setTablePrefix($this->tablePrefix);

		return $grammar;
	}

	/**
	 * Get the Connector instance.
	 *
	 * @return \Mini\Database\Connector
	 */
	public function getConnector()
	{
		return $this->connector;
	}

	/**
	 * Get the PDO instance.
	 *
	 * @return PDO
	 */
	public function getPdo()
	{
		return $this->pdo;
	}

	/**
	 * Set the PDO connection.
	 *
	 * @param  \PDO|null  $pdo
	 * @return $this
	 *
	 * @throws \RuntimeException
	 */
	public function setPdo($pdo)
	{
		if ($this->transactions >= 1) {
			throw new \RuntimeException("Can't swap PDO instance while within transaction.");
		}

		$this->pdo = $pdo;

		return $this;
	}

	/**
	 * Get the query grammar used by the connection.
	 *
	 * @return \Nova\Database\Query\Grammar
	 */
	public function getQueryGrammar()
	{
		return $this->queryGrammar;
	}

	/**
	 * Get the schema grammar used by the connection.
	 *
	 * @return \Nova\Database\Schema\Grammar
	 */
	public function getSchemaGrammar()
	{
		return $this->schemaGrammar;
	}

	/**
	 * Get the event dispatcher used by the connection.
	 *
	 * @return \Events\Dispatcher
	 */
	public function getEventDispatcher()
	{
		return $this->events;
	}

	/**
	 * Set the event dispatcher instance on the connection.
	 *
	 * @param  \Events\Dispatcher
	 * @return void
	 */
	public function setEventDispatcher(\Mini\Events\Dispatcher $events)
	{
		$this->events = $events;
	}

	/**
	 * Get the paginator environment instance.
	 *
	 * @return \Mini\Pagination\Environment
	 */
	public function getPaginator()
	{
		if ($this->paginator instanceof Closure) {
			$this->paginator = call_user_func($this->paginator);
		}

		return $this->paginator;
	}

	/**
	 * Set the pagination environment instance.
	 *
	 * @param  \Pagination\Environment|\Closure  $paginator
	 * @return void
	 */
	public function setPaginator($paginator)
	{
		$this->paginator = $paginator;
	}

	/**
	 * Get the cache manager instance.
	 *
	 * @return \Mini\Cache\CacheManager
	 */
	public function getCacheManager()
	{
		if ($this->cache instanceof Closure) {
			$this->cache = call_user_func($this->cache);
		}

		return $this->cache;
	}

	/**
	 * Set the cache manager instance on the connection.
	 *
	 * @param  \Cache\CacheManager|\Closure  $cache
	 * @return void
	 */
	public function setCacheManager($cache)
	{
		$this->cache = $cache;
	}

	/**
	 * Determine if the connection in a "dry run".
	 *
	 * @return bool
	 */
	public function pretending()
	{
		return ($this->pretending === true);
	}

	/**
	 * Get the default fetch mode for the Connection.
	 *
	 * @return int
	 */
	public function getFetchMode()
	{
		return $this->fetchMode;
	}

	/**
	 * Set the default fetch mode for the Connection.
	 *
	 * @param  int  $fetchMode
	 * @return \Database\Connection
	 */
	public function setFetchMode($fetchMode)
	{
		$this->fetchMode = $fetchMode;

		return $this;
	}

	/**
	 * Get the connection query log.
	 *
	 * @return array
	 */
	public function getQueryLog()
	{
		return $this->queryLog;
	}

	/**
	 * Clear the query log.
	 *
	 * @return void
	 */
	public function flushQueryLog()
	{
		$this->queryLog = array();
	}

	/**
	 * Enable the query log on the connection.
	 *
	 * @return void
	 */
	public function enableQueryLog()
	{
		$this->loggingQueries = true;
	}

	/**
	 * Disable the query log on the connection.
	 *
	 * @return void
	 */
	public function disableQueryLog()
	{
		$this->loggingQueries = false;
	}

	/**
	 * Determine whether we're logging queries.
	 *
	 * @return bool
	 */
	public function logging()
	{
		return $this->loggingQueries;
	}

}
