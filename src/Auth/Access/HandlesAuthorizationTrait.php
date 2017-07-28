<?php

namespace Mini\Auth\Access;

use Mini\Auth\Access\Response;
use Mini\Auth\Access\AuthorizationException;


trait HandlesAuthorizationTrait
{
    /**
     * Create a new access response.
     *
     * @param  string|null  $message
     * @return \Mini\Auth\Access\Response
     */
    protected function allow($message = null)
    {
        return new Response($message);
    }

    /**
     * Throws an unauthorized exception.
     *
     * @param  string  $message
     * @return void
     *
     * @throws \Mini\Auth\Access\AuthorizationException
     */
    protected function deny($message = null)
    {
        $message = $message ?: __d('nova', 'This action is unauthorized.');

        throw new AuthorizationException($message);
    }
}
