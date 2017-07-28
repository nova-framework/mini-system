<?php

namespace Mini\Foundation\Auth\Access;

use Mini\Auth\Contracts\Access\GateInterface as Gate;
use Mini\Support\Facades\App;


trait AuthorizableTrait
{
    /**
     * Determine if the entity has a given ability.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function can($ability, $arguments = array())
    {
        $gate = App::make(Gate::class);

        return $gate->forUser($this)->check($ability, $arguments);
    }

    /**
     * Determine if the entity does not have a given ability.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function cant($ability, $arguments = array())
    {
        return ! $this->can($ability, $arguments);
    }

    /**
     * Determine if the entity does not have a given ability.
     *
     * @param  string  $ability
     * @param  array|mixed  $arguments
     * @return bool
     */
    public function cannot($ability, $arguments = array())
    {
        return $this->cant($ability, $arguments);
    }
}
