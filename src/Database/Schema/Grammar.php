<?php

namespace Mini\Database\Schema;

use Mini\Database\Query\Expression;
use Mini\Database\Schema\Blueprint;
use Mini\Database\Connection;
use Mini\Database\Grammar as BaseGrammar;
use Mini\Support\Fluent;


class Grammar extends BaseGrammar
{
	/**
	 * The possible column modifiers.
	 *
	 * @var array
	 */
	protected $modifiers = array('Unsigned', 'Nullable', 'Default', 'Increment', 'Comment', 'After');

	/**
	 * The possible column serials
	 *
	 * @var array
	 */
	protected $serials = array('bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger');

	/**
	 * Compile the query to determine the list of tables.
	 *
	 * @return string
	 */
	public function compileTableExists()
	{
		return 'select * from information_schema.tables where table_schema = ? and table_name = ?';
	}

	/**
	 * Compile the query to determine the list of columns.
	 *
	 * @return string
	 */
	public function compileColumnExists()
	{
		return "select column_name from information_schema.columns where table_schema = ? and table_name = ?";
	}


	/**
	 * Compile a foreign key command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileForeign(Blueprint $blueprint, Fluent $command)
	{
		$table = $this->wrapTable($blueprint);

		$on = $this->wrapTable($command->on);

		//
		$columns = $this->columnize($command->columns);

		$onColumns = $this->columnize((array) $command->references);

		$sql = "alter table {$table} add constraint {$command->index} ";

		$sql .= "foreign key ({$columns}) references {$on} ({$onColumns})";

		if (! is_null($command->onDelete)) {
			$sql .= " on delete {$command->onDelete}";
		}

		if (! is_null($command->onUpdate)) {
			$sql .= " on update {$command->onUpdate}";
		}

		return $sql;
	}

	/**
	 * Compile a create table command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @param  \Mini\Database\Connection  $connection
	 * @return string
	 */
	public function compileCreate(Blueprint $blueprint, Fluent $command, Connection $connection)
	{
		$columns = implode(', ', $this->getColumns($blueprint));

		$sql = 'create table '.$this->wrapTable($blueprint)." ($columns)";

		//
		$sql = $this->compileCreateEncoding($sql, $connection);

		if (isset($blueprint->engine)) {
			$sql .= ' engine = '.$blueprint->engine;
		}

		return $sql;
	}

	/**
	 * Append the character set specifications to a command.
	 *
	 * @param  string  $sql
	 * @param  \Mini\Database\Connection  $connection
	 * @return string
	 */
	protected function compileCreateEncoding($sql, Connection $connection)
	{
		if (! is_null($charset = $connection->getConfig('charset'))) {
			$sql .= ' default character set ' .$charset;
		}

		if (! is_null($collation = $connection->getConfig('collation'))) {
			$sql .= ' collate ' .$collation;
		}

		return $sql;
	}

	/**
	 * Compile an add column command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileAdd(Blueprint $blueprint, Fluent $command)
	{
		$table = $this->wrapTable($blueprint);

		$columns = $this->prefixArray('add', $this->getColumns($blueprint));

		return 'alter table '.$table.' '.implode(', ', $columns);
	}

	/**
	 * Compile a primary key command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compilePrimary(Blueprint $blueprint, Fluent $command)
	{
		$command->name(null);

		return $this->compileKey($blueprint, $command, 'primary key');
	}

	/**
	 * Compile a unique key command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileUnique(Blueprint $blueprint, Fluent $command)
	{
		return $this->compileKey($blueprint, $command, 'unique');
	}

	/**
	 * Compile a plain index key command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileIndex(Blueprint $blueprint, Fluent $command)
	{
		return $this->compileKey($blueprint, $command, 'index');
	}

	/**
	 * Compile an index creation command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @param  string  $type
	 * @return string
	 */
	protected function compileKey(Blueprint $blueprint, Fluent $command, $type)
	{
		$columns = $this->columnize($command->columns);

		$table = $this->wrapTable($blueprint);

		return "alter table {$table} add {$type} {$command->index}($columns)";
	}

	/**
	 * Compile a drop table command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileDrop(Blueprint $blueprint, Fluent $command)
	{
		return 'drop table '.$this->wrapTable($blueprint);
	}

	/**
	 * Compile a drop table (if exists) command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileDropIfExists(Blueprint $blueprint, Fluent $command)
	{
		return 'drop table if exists '.$this->wrapTable($blueprint);
	}

	/**
	 * Compile a drop column command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileDropColumn(Blueprint $blueprint, Fluent $command)
	{
		$columns = $this->prefixArray('drop', $this->wrapArray($command->columns));

		$table = $this->wrapTable($blueprint);

		return 'alter table '.$table.' '.implode(', ', $columns);
	}

	/**
	 * Compile a drop primary key command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileDropPrimary(Blueprint $blueprint, Fluent $command)
	{
		return 'alter table '.$this->wrapTable($blueprint).' drop primary key';
	}

	/**
	 * Compile a drop unique key command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileDropUnique(Blueprint $blueprint, Fluent $command)
	{
		$table = $this->wrapTable($blueprint);

		return "alter table {$table} drop index {$command->index}";
	}

	/**
	 * Compile a drop index command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileDropIndex(Blueprint $blueprint, Fluent $command)
	{
		$table = $this->wrapTable($blueprint);

		return "alter table {$table} drop index {$command->index}";
	}

	/**
	 * Compile a drop foreign key command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileDropForeign(Blueprint $blueprint, Fluent $command)
	{
		$table = $this->wrapTable($blueprint);

		return "alter table {$table} drop foreign key {$command->index}";
	}

	/**
	 * Compile a rename table command.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $command
	 * @return string
	 */
	public function compileRename(Blueprint $blueprint, Fluent $command)
	{
		$from = $this->wrapTable($blueprint);

		return "rename table {$from} to ".$this->wrapTable($command->to);
	}

	/**
	 * Create the column definition for a char type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeChar(Fluent $column)
	{
		return "char({$column->length})";
	}

	/**
	 * Create the column definition for a string type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeString(Fluent $column)
	{
		return "varchar({$column->length})";
	}

	/**
	 * Create the column definition for a text type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeText(Fluent $column)
	{
		return 'text';
	}

	/**
	 * Create the column definition for a medium text type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeMediumText(Fluent $column)
	{
		return 'mediumtext';
	}

	/**
	 * Create the column definition for a long text type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeLongText(Fluent $column)
	{
		return 'longtext';
	}

	/**
	 * Create the column definition for a big integer type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeBigInteger(Fluent $column)
	{
		return 'bigint';
	}

	/**
	 * Create the column definition for a integer type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeInteger(Fluent $column)
	{
		return 'int';
	}

	/**
	 * Create the column definition for a medium integer type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeMediumInteger(Fluent $column)
	{
		return 'mediumint';
	}

	/**
	 * Create the column definition for a tiny integer type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeTinyInteger(Fluent $column)
	{
		return 'tinyint';
	}

	/**
	 * Create the column definition for a small integer type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeSmallInteger(Fluent $column)
	{
		return 'smallint';
	}

	/**
	 * Create the column definition for a float type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeFloat(Fluent $column)
	{
		return "float({$column->total}, {$column->places})";
	}

	/**
	 * Create the column definition for a double type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeDouble(Fluent $column)
	{
		if ($column->total && $column->places)
		{
			return "double({$column->total}, {$column->places})";
		}

		return 'double';
	}

	/**
	 * Create the column definition for a decimal type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeDecimal(Fluent $column)
	{
		return "decimal({$column->total}, {$column->places})";
	}

	/**
	 * Create the column definition for a boolean type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeBoolean(Fluent $column)
	{
		return 'tinyint(1)';
	}

	/**
	 * Create the column definition for an enum type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeEnum(Fluent $column)
	{
		return "enum('".implode("', '", $column->allowed)."')";
	}

	/**
	 * Create the column definition for a date type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeDate(Fluent $column)
	{
		return 'date';
	}

	/**
	 * Create the column definition for a date-time type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeDateTime(Fluent $column)
	{
		return 'datetime';
	}

	/**
	 * Create the column definition for a time type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeTime(Fluent $column)
	{
		return 'time';
	}

	/**
	 * Create the column definition for a timestamp type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeTimestamp(Fluent $column)
	{
		if (! $column->nullable) return 'timestamp default CURRENT_TIMESTAMP';

		return 'timestamp';
	}

	/**
	 * Create the column definition for a binary type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function typeBinary(Fluent $column)
	{
		return 'blob';
	}

	/**
	 * Get the SQL for an unsigned column modifier.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $column
	 * @return string|null
	 */
	protected function modifyUnsigned(Blueprint $blueprint, Fluent $column)
	{
		if ($column->unsigned) return ' unsigned';
	}

	/**
	 * Get the SQL for a nullable column modifier.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $column
	 * @return string|null
	 */
	protected function modifyNullable(Blueprint $blueprint, Fluent $column)
	{
		return $column->nullable ? ' null' : ' not null';
	}

	/**
	 * Get the SQL for a default column modifier.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $column
	 * @return string|null
	 */
	protected function modifyDefault(Blueprint $blueprint, Fluent $column)
	{
		if (! is_null($column->default))  {
			return " default ".$this->getDefaultValue($column->default);
		}
	}

	/**
	 * Get the SQL for an auto-increment column modifier.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $column
	 * @return string|null
	 */
	protected function modifyIncrement(Blueprint $blueprint, Fluent $column)
	{
		if (in_array($column->type, $this->serials) && $column->autoIncrement) {
			return ' auto_increment primary key';
		}
	}

	/**
	 * Get the SQL for an "after" column modifier.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $column
	 * @return string|null
	 */
	protected function modifyAfter(Blueprint $blueprint, Fluent $column)
	{
		if (! is_null($column->after)) {
			return ' after '.$this->wrap($column->after);
		}
	}

	/**
	 * Get the SQL for an "comment" column modifier.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $column
	 * @return string|null
	 */
	protected function modifyComment(Blueprint $blueprint, Fluent $column)
	{
		if (! is_null($column->comment)) {
			return ' comment "'.$column->comment.'"';
		}
	}

	/**
	 * Compile the blueprint's column definitions.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @return array
	 */
	protected function getColumns(Blueprint $blueprint)
	{
		$columns = array();

		foreach ($blueprint->getColumns() as $column) {
			$sql = $this->wrap($column).' '.$this->getType($column);

			$columns[] = $this->addModifiers($sql, $blueprint, $column);
		}

		return $columns;
	}

	/**
	 * Add the column modifiers to the definition.
	 *
	 * @param  string  $sql
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function addModifiers($sql, Blueprint $blueprint, Fluent $column)
	{
		foreach ($this->modifiers as $modifier) {
			if (method_exists($this, $method = "modify{$modifier}")) {
				$sql .= $this->{$method}($blueprint, $column);
			}
		}

		return $sql;
	}

	/**
	 * Get the primary key command if it exists on the blueprint.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  string  $name
	 * @return \Mini\Support\Fluent|null
	 */
	protected function getCommandByName(Blueprint $blueprint, $name)
	{
		$commands = $this->getCommandsByName($blueprint, $name);

		if (count($commands) > 0) {
			return reset($commands);
		}
	}

	/**
	 * Get all of the commands with a given name.
	 *
	 * @param  \Mini\Database\Schema\Blueprint  $blueprint
	 * @param  string  $name
	 * @return array
	 */
	protected function getCommandsByName(Blueprint $blueprint, $name)
	{
		return array_filter($blueprint->getCommands(), function($value) use ($name)
		{
			return $value->name == $name;
		});
	}

	/**
	 * Get the SQL for the column data type.
	 *
	 * @param  \Mini\Support\Fluent  $column
	 * @return string
	 */
	protected function getType(Fluent $column)
	{
		return $this->{"type".ucfirst($column->type)}($column);
	}

	/**
	 * Add a prefix to an array of values.
	 *
	 * @param  string  $prefix
	 * @param  array   $values
	 * @return array
	 */
	public function prefixArray($prefix, array $values)
	{
		return array_map(function($value) use ($prefix)
		{
			return $prefix.' '.$value;

		}, $values);
	}

	/**
	 * Wrap a table in keyword identifiers.
	 *
	 * @param  mixed   $table
	 * @return string
	 */
	public function wrapTable($table)
	{
		if ($table instanceof Blueprint) $table = $table->getTable();

		return parent::wrapTable($table);
	}

	/**
	 * Wrap a single string in keyword identifiers.
	 *
	 * @param  string  $value
	 * @return string
	 */
	protected function wrapValue($value)
	{
		if ($value === '*') return $value;

		return '`'.str_replace('`', '``', $value).'`';
	}

	/**
	 * Wrap a value in keyword identifiers.
	 *
	 * @param  string  $value
	 * @return string
	 */
	public function wrap($value)
	{
		if ($value instanceof Fluent) $value = $value->name;

		return parent::wrap($value);
	}

	/**
	 * Format a value so that it can be used in "default" clauses.
	 *
	 * @param  mixed   $value
	 * @return string
	 */
	protected function getDefaultValue($value)
	{
		if ($value instanceof Expression) {
			return $value;
		}

		if (is_bool($value)) {
			return "'".(int) $value."'";
		}

		return "'".strval($value)."'";
	}
}
