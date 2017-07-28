<?php

namespace Mini\Foundation\Http\Middleware;

use Mini\Encryption\Encrypter;
use Mini\Foundation\Application;
use Mini\Session\TokenMismatchException;
use Mini\Support\Str;

use Symfony\Component\HttpFoundation\Cookie;

use Closure;


class VerifyCsrfToken
{
    /**
     * The application implementation.
     *
     * @var \Mini\Foundation\Application
     */
    protected $app;

    /**
     * The encrypter implementation.
     *
     * @var \Mini\Encryption\Encrypter
     */
    protected $encrypter;

    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = array();


    /**
     * Create a new middleware instance.
     *
     * @param  \Mini\Contracts\Encryption\Encrypter  $encrypter
     * @return void
     */
    public function __construct(Application $app, Encrypter $encrypter)
    {
        $this->app = $app;

        $this->encrypter = $encrypter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Mini\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Mini\Session\TokenMismatchException
     */
    public function handle($request, Closure $next)
    {
        if ($this->isReading($request) || $this->shouldPassThrough($request) || $this->tokensMatch($request)) {
            $response = $next($request);

            return $this->addCookieToResponse($request, $response);
        }

        throw new TokenMismatchException;
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param  \Mini\Http\Request  $request
     * @return bool
     */
    protected function shouldPassThrough($request)
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->is($except)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the session and input CSRF tokens match.
     *
     * @param  \Mini\Http\Request  $request
     * @return bool
     */
    protected function tokensMatch($request)
    {
        $sessionToken = $request->session()->token();

        $token = $request->input('_token') ?: $request->header('X-CSRF-TOKEN');

        if (is_null($token) && ! is_null($header = $request->header('X-XSRF-TOKEN'))) {
            $token = $this->encrypter->decrypt($header);
        }

        if (! is_string($sessionToken) || ! is_string($token)) {
            return false;
        }

        return Str::equals($sessionToken, $token);
    }

    /**
     * Add the CSRF token to the response cookies.
     *
     * @param  \Mini\Http\Request  $request
     * @param  \Mini\Http\Response  $response
     * @return \Mini\Http\Response
     */
    protected function addCookieToResponse($request, $response)
    {
        $config = $this->app['config']['session'];

        $cookie = new Cookie(
            'XSRF-TOKEN',
            $request->session()->token(),
            time() + 60 * 120,
            $config['path'],
            $config['domain'],
            $config['secure'], false
        );

        $response->headers->setCookie($cookie);

        return $response;
    }

    /**
     * Determine if the HTTP request uses a ‘read’ verb.
     *
     * @param  \Mini\Http\Request  $request
     * @return bool
     */
    protected function isReading($request)
    {
        return in_array($request->method(), array('HEAD', 'GET', 'OPTIONS'));
    }
}
