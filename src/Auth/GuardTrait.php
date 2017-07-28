<?php

namespace Mini\Auth;

use Mini\Auth\AuthenticationException;
use Mini\Auth\Contracts\UserInterface;


/**
 * These methods are typically the same across all guards.
 */
trait GuardTrait
{
    /**
     * The currently authenticated user.
     *
     * @var \Mini\Auth\UserInterface
     */
    protected $user;


    /**
     * Determine if the current user is authenticated.
     *
     * @return \Mini\Auth\Contracts\UserInterface
     *
     * @throws \Mini\Auth\AuthenticationException
     */
    public function authenticate()
    {
        if (! is_null($user = $this->user())) {
            return $user;
        }

        throw new AuthenticationException;
    }

    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check()
    {
        return ! is_null($this->user());
    }

    /**
     * Determine if the current user is a guest.
     *
     * @return bool
     */
    public function guest()
    {
        return ! $this->check();
    }

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|null
     */
    public function id()
    {
        if (! is_null($user = $this->user())) {
            return $user->getAuthIdentifier();
        }
    }

    /**
     * Set the current user.
     *
     * @param  \Mini\Auth\Contracts\UserInterface  $user
     * @return $this
     */
    public function setUser(UserInterface $user)
    {
        $this->user = $user;

        return $this;
    }
}
