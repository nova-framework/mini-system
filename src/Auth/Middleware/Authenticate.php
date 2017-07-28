<?php

namespace Mini\Auth\Middleware;

use Mini\Auth\AuthManager as Auth;
use Mini\Auth\AuthenticationException;

use Closure;


class Authenticate
{
    /**
     * The authentication factory instance.
     *
     * @var \Mini\Auth\AuthManager
     */
    protected $auth;


    /**
     * Create a new middleware instance.
     *
     * @param  \Mini\Auth\AuthManager  $auth
     * @return void
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Mini\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $guards = array_slice(func_get_args(), 2);

        $this->authenticate($guards);

        return $next($request);
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     *
     * @param  array  $guards
     * @return void
     *
     * @throws \Mini\Auth\AuthenticationException
     */
    protected function authenticate(array $guards)
    {
        if (empty($guards)) {
            return $this->auth->authenticate();
        }

        foreach ($guards as $guard) {
            $instance = $this->auth->guard($guard);

            if ($instance->check()) {
                return $this->auth->shouldUse($guard);
            }
        }

        throw new AuthenticationException('Unauthenticated.', $guards);
    }
}
