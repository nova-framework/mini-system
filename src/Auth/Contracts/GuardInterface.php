<?php

namespace Mini\Auth\Contracts;

use Mini\Auth\Contracts\UserInterface;


interface GuardInterface
{
    /**
     * Determine if the current user is authenticated.
     *
     * @return bool
     */
    public function check();

    /**
     * Determine if the current user is a guest.
     *
     * @return bool
     */
    public function guest();

    /**
     * Get the currently authenticated user.
     *
     * @return \Nova\Auth\Contracts\UserInterface|null
     */
    public function user();

    /**
     * Get the ID for the currently authenticated user.
     *
     * @return int|null
     */
    public function id();

    /**
     * Validate a user's credentials.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = array());

    /**
     * Set the current user.
     *
     * @param  \Nova\Auth\Contracts\UserInterface  $user
     * @return void
     */
    public function setUser(UserInterface $user);
}
