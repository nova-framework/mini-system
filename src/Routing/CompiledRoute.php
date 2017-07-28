<?php

namespace Mini\Routing;


class CompiledRoute
{
    /**
     * The regex pattern the route responds to.
     *
     * @var string
     */
    protected $regex;

    /**
     * The regex pattern the route responds to.
     *
     * @var string|null
     */
    protected $hostRegex;

    /**
     * The parameter names of the route.
     *
     * @var array
     */
    protected $variables = array();


    public function __construct($regex, $hostRegex, array $variables)
    {
        $this->regex        = $regex;
        $this->hostRegex    = $hostRegex;
        $this->variables    = $variables;
    }

    /**
     * Get the uri regular expression.
     *
     * @return string|null
     */
    public function getRegex()
    {
        return $this->regex;
    }

    /**
     * Get the host regular expression.
     *
     * @return string|null
     */
    public function getHostRegex()
    {
        return $this->hostRegex;
    }

    /**
     * Get the variables defined in route.
     *
     * @return array
     */
    public function getVariables()
    {
        return $this->variables;
    }
}
