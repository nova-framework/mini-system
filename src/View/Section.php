<?php

namespace Mini\View;

use Mini\View\Factory;


class Section
{
    /**
     * @var Mini\View\Factory
     */
    protected $factory;


    public function __construct(Factory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Start injecting content into a section.
     *
     * @param  string  $section
     * @param  string  $content
     * @return void
     */
    public function start($section, $content = '')
    {
        $this->factory->startSection($section, $content);
    }

    /**
     * Stop injecting content into a section and output its contents.
     *
     * @return string
     */
    public function show()
    {
        echo $this->factory->yieldSection();
    }

    /**
     * Stop injecting content into a section and return its contents.
     *
     * @return string
     */
    public function fetch()
    {
        return $this->factory->yieldSection();
    }

    /**
     * Stop injecting content into a section.
     *
     * @return string
     */
    public function end()
    {
        return $this->factory->stopSection();
    }

    /**
     * Stop injecting content into a section.
     *
     * @return string
     */
    public function stop()
    {
        return $this->factory->stopSection();
    }

    /**
     * Stop injecting content into a section.
     *
     * @return string
     */
    public function overwrite()
    {
        return $this->factory->stopSection(true);
    }

    /**
     * Stop injecting content into a section and append it.
     *
     * @return string
     */
    public function append()
    {
        return $this->factory->appendSection();
    }

    /**
     * Get the string contents of a section.
     *
     * @param  string  $section
     * @param  string  $default
     * @return string
     */
    public function render($section, $default = '')
    {
        return $this->factory->yieldContent($section, $default);
    }
}
