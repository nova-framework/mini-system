<?php

namespace Mini\Database\Migrations;

use Mini\Database\Contracts\Migrations\MigrationRepositoryInterface;
use Mini\Database\Contracts\ConnectionResolverInterface as Resolver;


class DatabaseMigrationRepository implements MigrationRepositoryInterface
{
    /**
     * The database connection resolver instance.
     *
     * @var \Mini\Database\Contracts\ConnectionResolverInterface
     */
    protected $resolver;

    /**
     * The name of the migration table.
     *
     * @var string
     */
    protected $table;

    /**
     * The name of the database connection to use.
     *
     * @var string
     */
    protected $connection;

    /**
     * Create a new database migration repository instance.
     *
     * @param  \Mini\Database\Contracts\ConnectionResolverInterface  $resolver
     * @param  string  $table
     * @return void
     */
    public function __construct(Resolver $resolver, $table)
    {
        $this->table = $table;
        $this->resolver = $resolver;
    }

    /**
     * Get the ran migrations.
     *
     * @return array
     */
    public function getRan()
    {
        return $this->table()->lists('migration');
    }

    /**
     * Get the last migration batch.
     *
     * @param  string|null  $group
     *
     * @return array
     */
    public function getLast($group)
    {
        $query = $this->table()
            ->where('batch', $this->getLastBatchNumber($group))
            ->where('group', $group);

        return $query->orderBy('migration', 'desc')->get();
    }

    /**
     * Log that a migration was run.
     *
     * @param  string  $file
     * @param  int     $batch
     * @param  string  $group
     * @return void
     */
    public function log($file, $batch, $group)
    {
        $record = array('migration' => $file, 'batch' => $batch, 'group' => $group);

        $this->table()->insert($record);
    }

    /**
     * Remove a migration from the log.
     *
     * @param  object  $migration
     * @return void
     */
    public function delete($migration)
    {
        $this->table()->where('migration', $migration->migration)->delete();
    }

    /**
     * Get the next migration batch number.
     *
     * @param  string  $group
     *
     * @return int
     */
    public function getNextBatchNumber($group)
    {
        return $this->getLastBatchNumber($group) + 1;
    }

    /**
     * Get the last migration batch number.
     *
     * @param  string  $group
     *
     * @return int
     */
    public function getLastBatchNumber($group)
    {
        $query = $this->table()->where('group', $group);

        return $query->max('batch');
    }

    /**
     * Create the migration repository data store.
     *
     * @return void
     */
    public function createRepository()
    {
        $schema = $this->getConnection()->getSchemaBuilder();

        $schema->create($this->table, function($table)
        {
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
            $table->string('group');
        });
    }

    /**
     * Determine if the migration repository exists.
     *
     * @return bool
     */
    public function repositoryExists()
    {
        $schema = $this->getConnection()->getSchemaBuilder();

        return $schema->hasTable($this->table);
    }

    /**
     * Get a query builder for the migration table.
     *
     * @return \Mini\Database\Query\Builder
     */
    protected function table()
    {
        return $this->getConnection()->table($this->table);
    }

    /**
     * Get the connection resolver instance.
     *
     * @return \Mini\Database\Contracts\ConnectionResolverInterface
     */
    public function getConnectionResolver()
    {
        return $this->resolver;
    }

    /**
     * Resolve the database connection instance.
     *
     * @return \Mini\Database\Connection
     */
    public function getConnection()
    {
        return $this->resolver->connection($this->connection);
    }

    /**
     * Set the information source to gather data.
     *
     * @param  string  $name
     * @return void
     */
    public function setSource($name)
    {
        $this->connection = $name;
    }

}
