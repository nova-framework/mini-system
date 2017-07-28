<?php

namespace Mini\Database\Migrations;

use Mini\Filesystem\Filesystem;

use Closure;


class MigrationCreator
{
    /**
     * The filesystem instance.
     *
     * @var \Mini\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The registered post create hooks.
     *
     * @var array
     */
    protected $postCreate = array();

    /**
     * Create a new migration creator instance.
     *
     * @param  \Mini\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Create a new migration at the given path.
     *
     * @param  string  $name
     * @param  string  $path
     * @param  string  $table
     * @param  bool    $create
     * @return string
     */
    public function create($name, $path, $table = null, $create = false)
    {
        $path = $this->getPath($name, $path);

        //
        $stub = $this->getStub($table, $create);

        $this->files->put($path, $this->populateStub($name, $stub, $table));

        $this->firePostCreateHooks();

        return $path;
    }

    /**
     * Get the migration stub file.
     *
     * @param  string  $table
     * @param  bool    $create
     * @return string
     */
    protected function getStub($table, $create)
    {
        if (is_null($table)) {
            return $this->files->get($this->getStubPath() .DS .'blank.stub');
        } else {
            $stub = $create ? 'create.stub' : 'update.stub';

            return $this->files->get($this->getStubPath() .DS .$stub);
        }
    }

    /**
     * Populate the place-holders in the migration stub.
     *
     * @param  string  $name
     * @param  string  $stub
     * @param  string  $table
     * @return string
     */
    protected function populateStub($name, $stub, $table)
    {
        $stub = str_replace('{{class}}', $this->getClassName($name), $stub);

        if (! is_null($table)) {
            $stub = str_replace('{{table}}', $table, $stub);
        }

        return $stub;
    }

    /**
     * Get the class name of a migration name.
     *
     * @param  string  $name
     * @return string
     */
    protected function getClassName($name)
    {
        return studly_case($name);
    }

    /**
     * Fire the registered post create hooks.
     *
     * @return void
     */
    protected function firePostCreateHooks()
    {
        foreach ($this->postCreate as $callback) {
            call_user_func($callback);
        }
    }

    /**
     * Register a post migration create hook.
     *
     * @param  \Closure  $callback
     * @return void
     */
    public function afterCreate(Closure $callback)
    {
        $this->postCreate[] = $callback;
    }

    /**
     * Get the full path name to the migration.
     *
     * @param  string  $name
     * @param  string  $path
     * @return string
     */
    protected function getPath($name, $path)
    {
        return $path .DS .$this->getDatePrefix() .'_' .$name .'.php';
    }

    /**
     * Get the date prefix for the migration.
     *
     * @return string
     */
    protected function getDatePrefix()
    {
        return date('Y_m_d_His');
    }

    /**
     * Get the path to the stubs.
     *
     * @return string
     */
    public function getStubPath()
    {
        return __DIR__ .DS .'stubs';
    }

    /**
     * Get the filesystem instance.
     *
     * @return \Mini\Filesystem\Filesystem
     */
    public function getFilesystem()
    {
        return $this->files;
    }

}
