<?php

namespace Mini\View\Compilers;

use Mini\Filesystem\Filesystem;


abstract class Compiler
{

    /**
    * The Filesystem instance.
    *
    * @var \Mini\Filesystem\Filesystem
    */
    protected $files;

    /**
    * Get the cache path for the compiled views.
    *
    * @var string
    */
    protected $cachePath;


    /**
    * Create a new compiler instance.
    *
    * @param  \Mini\Filesystem\Filesystem  $files
    * @param  string  $cachePath
    * @return void
    */
    public function __construct(Filesystem $files, $cachePath)
    {
        $this->files = $files;

        $this->cachePath = $cachePath;
    }

    /**
    * Get the path to the compiled version of a view.
    *
    * @param  string  $path
    * @return string
    */
    public function getCompiledPath($path)
    {
        return $this->cachePath .DS .sha1($path) .'.php';
    }

    /**
    * Determine if the view at the given path is expired.
    *
    * @param  string  $path
    * @return bool
    */
    public function isExpired($path)
    {
        $compiled = $this->getCompiledPath($path);

        if (is_null($this->cachePath) || ! $this->files->exists($compiled)) {
            return true;
        }

        $lastModified = $this->files->lastModified($path);

        if ($lastModified >= $this->files->lastModified($compiled)) {
            return true;
        }

        return false;
    }

}
