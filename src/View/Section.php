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
     * Stop injecting content into a section and return its contents.
     *
     * @return string
     */
    public function get()
    {
        return $this->factory->yieldSection();
    }

    /**
     * Stop injecting content into a section.
     *
     * @param  bool  $overwrite
     * @return string
     */
    public function stop($overwrite = false)
    {
        return $this->factory->stopSection($overwrite);
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
     * Append content to a given section.
     *
     * @param  string  $section
     * @param  string  $content
     * @return void
     */
    public function extend($section, $content)
    {
        $this->factory->extendSection($section, $content);
    }

    /**
     * Get the string contents of a section.
     *
     * @param  string  $section
     * @param  string  $default
     * @return string
     */
    public function getContent($section, $default = '')
    {
        return $this->factory->yieldContent($section, $default);
    }
}
