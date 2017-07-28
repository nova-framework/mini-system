<?php

namespace Mini\Foundation\Auth;

use Mini\Http\Request;
use Mini\Support\Facades\App;
use Mini\Support\Facades\Redirect;


trait ThrottlesLoginsTrait
{
    /**
     * Determine if the user has too many failed login attempts.
     *
     * @param  \Mini\Http\Request  $request
     * @return bool
     */
    protected function hasTooManyLoginAttempts(Request $request)
    {
        $rateLimiter = App::make('Mini\Cache\RateLimiter');

        return $rateLimiter->tooManyAttempts(
            $this->getThrottleKey($request),
            $this->maxLoginAttempts(),
            $this->lockoutTime() / 60
        );
    }

    /**
     * Increment the login attempts for the user.
     *
     * @param  \Mini\Http\Request  $request
     * @return int
     */
    protected function incrementLoginAttempts(Request $request)
    {
        $rateLimiter = App::make('Mini\Cache\RateLimiter');

        $rateLimiter->hit($this->getThrottleKey($request));
    }

    /**
     * Determine how many retries are left for the user.
     *
     * @param  \Mini\Http\Request  $request
     * @return int
     */
    protected function retriesLeft(Request $request)
    {
        $rateLimiter = App::make('Mini\Cache\RateLimiter');

        $attempts = $rateLimiter->attempts($this->getThrottleKey($request));

        return $this->maxLoginAttempts() - $attempts + 1;
    }

    /**
     * Redirect the user after determining they are locked out.
     *
     * @param  \Mini\Http\Request  $request
     * @return \Mini\Http\RedirectResponse
     */
    protected function sendLockoutResponse(Request $request)
    {
        $rateLimiter = App::make('Mini\Cache\RateLimiter');

        $seconds = $rateLimiter->availableIn($this->getThrottleKey($request));

        $errors = array($this->loginUsername() => $this->getLockoutErrorMessage($seconds));

        return Redirect::back()
            ->withInput($request->only($this->loginUsername(), 'remember'))
            ->withErrors($errors);
    }

    /**
     * Get the login lockout error message.
     *
     * @param  int  $seconds
     * @return string
     */
    protected function getLockoutErrorMessage($seconds)
    {
        return __d('nova', 'Too many login attempts. Please try again in {0} seconds.', $seconds);
    }

    /**
     * Clear the login locks for the given user credentials.
     *
     * @param  \Mini\Http\Request  $request
     * @return void
     */
    protected function clearLoginAttempts(Request $request)
    {
        $rateLimiter = App::make('Mini\Cache\RateLimiter');

        $rateLimiter->clear($this->getThrottleKey($request));
    }

    /**
     * Get the throttle key for the given request.
     *
     * @param  \Mini\Http\Request  $request
     * @return string
     */
    protected function getThrottleKey(Request $request)
    {
        return mb_strtolower($request->input($this->username())) .'|' .$request->ip();
    }

    /**
     * Get the maximum number of login attempts for delaying further attempts.
     *
     * @return int
     */
    protected function maxLoginAttempts()
    {
        return property_exists($this, 'maxLoginAttempts') ? $this->maxLoginAttempts : 5;
    }

    /**
     * The number of seconds to delay further login attempts.
     *
     * @return int
     */
    protected function lockoutTime()
    {
        return property_exists($this, 'lockoutTime') ? $this->lockoutTime : 60;
    }
}
