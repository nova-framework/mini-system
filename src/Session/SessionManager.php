<?php

namespace Mini\Session;

use Mini\Session\DatabaseSessionHandler;
use Mini\Session\FileSessionHandler;
use Mini\Session\Store;
use Mini\Support\Manager;

use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;


class SessionManager extends Manager
{
    /**
     * Call a custom driver creator.
     *
     * @param  string  $driver
     * @return mixed
     */
    protected function callCustomCreator($driver)
    {
        return $this->buildSession(parent::callCustomCreator($driver));
    }

    /**
     * Create an instance of the "array" session driver.
     *
     * @return \Mini\Session\Store
     */
    protected function createArrayDriver()
    {
        return new Store($this->app['config']['session.cookie'], new NullSessionHandler);
    }

    /**
     * Create an instance of the file session driver.
     *
     * @return \Mini\Session\Store
     */
    protected function createFileDriver()
    {
        return $this->createNativeDriver();
    }

    /**
     * Create an instance of the file session driver.
     *
     * @return \Mini\Session\Store
     */
    protected function createNativeDriver()
    {
        $path = $this->app['config']['session.files'];

        return $this->buildSession(new FileSessionHandler($this->app['files'], $path));
    }

    /**
     * Create an instance of the database session driver.
     *
     * @return \Mini\Session\Store
     */
    protected function createDatabaseDriver()
    {
        $connection = $this->getDatabaseConnection();

        $table = $this->app['config']['session.table'];

        return $this->buildSession(new DatabaseSessionHandler($connection, $table));
    }

    /**
     * Get the database connection for the database driver.
     *
     * @return \Mini\Database\Connection
     */
    protected function getDatabaseConnection()
    {
        $connection = $this->app['config']['session.connection'];

        return $this->app['db']->connection($connection);
    }

    /**
     * Build the session instance.
     *
     * @param  \SessionHandlerInterface  $handler
     * @return \Mini\Session\Store
     */
    protected function buildSession($handler)
    {
        return new Store($this->app['config']['session.cookie'], $handler);
    }

    /**
     * Get the session configuration.
     *
     * @return array
     */
    public function getSessionConfig()
    {
        return $this->app['config']['session'];
    }

    /**
     * Get the default session driver name.
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->app['config']['session.driver'];
    }

    /**
     * Set the default session driver name.
     *
     * @param  string  $name
     * @return void
     */
    public function setDefaultDriver($name)
    {
        $this->app['config']['session.driver'] = $name;
    }

}
