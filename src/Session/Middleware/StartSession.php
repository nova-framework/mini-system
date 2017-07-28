<?php

namespace Mini\Session\Middleware;

use Mini\Http\Request;
use Mini\Session\Contracts\SessionInterface;
use Mini\Session\SessionManager;
use Mini\Support\Arr;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

use Carbon\Carbon;

use Closure;


class StartSession
{
    /**
     * The session manager.
     *
     * @var \Mini\Session\SessionManager
     */
    protected $manager;

    /**
     * Indicates if the session was handled for the current request.
     *
     * @var bool
     */
    protected $sessionHandled = false;


    /**
     * Create a new session middleware.
     *
     * @param  \Mini\Session\SessionManager  $manager
     * @return void
     */
    public function __construct(SessionManager $manager)
    {
        $this->manager = $manager;
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
        $this->sessionHandled = true;

        if ($this->sessionConfigured()) {
            $session = $this->startSession($request);

            $request->setSession($session);
        }

        $response = $next($request);

        if ($this->sessionConfigured()) {
            $this->storeCurrentUrl($request, $session);

            $this->collectGarbage($session);

            $this->addCookieToResponse($response, $session);
        }

        return $response;
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  \Mini\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function terminate($request, $response)
    {
        if ($this->sessionHandled && $this->sessionConfigured()) {
            $this->manager->driver()->save();
        }
    }

    /**
     * Start the session for the given request.
     *
     * @param  \Mini\Http\Request  $request
     * @return \Mini\Session\Contracts\SessionInterface
     */
    protected function startSession(Request $request)
    {
        with($session = $this->getSession($request))->setRequestOnHandler($request);

        $session->start();

        return $session;
    }

    /**
     * Get the session implementation from the manager.
     *
     * @param  \Mini\Http\Request  $request
     * @return \Mini\Session\Contracts\SessionInterface
     */
    public function getSession(Request $request)
    {
        $session = $this->manager->driver();

        $session->setId($request->cookies->get($session->getName()));

        return $session;
    }

    /**
     * Store the current URL for the request if necessary.
     *
     * @param  \Mini\Http\Request  $request
     * @param  \Mini\Session\Contracts\SessionInterface  $session
     * @return void
     */
    protected function storeCurrentUrl(Request $request, $session)
    {
        if (($request->method() === 'GET') && $request->route() && ! $request->ajax()) {
            $session->setPreviousUrl($request->fullUrl());
        }
    }

    /**
     * Remove the garbage from the session if necessary.
     *
     * @param  \Mini\Session\Contracts\SessionInterface  $session
     * @return void
     */
    protected function collectGarbage(SessionInterface $session)
    {
        $config = $this->manager->getSessionConfig();

        if ($this->configHitsLottery($config)) {
            $session->getHandler()->gc($this->getSessionLifetimeInSeconds());
        }
    }

    /**
     * Determine if the configuration odds hit the lottery.
     *
     * @param  array  $config
     * @return bool
     */
    protected function configHitsLottery(array $config)
    {
        return mt_rand(1, $config['lottery'][1]) <= $config['lottery'][0];
    }

    /**
     * Add the session cookie to the application response.
     *
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @param  \Mini\Session\Contracts\SessionInterface  $session
     * @return void
     */
    protected function addCookieToResponse(Response $response, SessionInterface $session)
    {
        if ($this->sessionIsPersistent($config = $this->manager->getSessionConfig())) {
            $response->headers->setCookie(new Cookie(
                $session->getName(), $session->getId(), $this->getCookieExpirationDate(),
                $config['path'], $config['domain'], Arr::get($config, 'secure', false)
            ));
        }
    }

    /**
     * Get the session lifetime in seconds.
     *
     * @return int
     */
    protected function getSessionLifetimeInSeconds()
    {
        return Arr::get($this->manager->getSessionConfig(), 'lifetime') * 60;
    }

    /**
     * Get the cookie lifetime in seconds.
     *
     * @return int
     */
    protected function getCookieExpirationDate()
    {
        $config = $this->manager->getSessionConfig();

        return $config['expireOnClose'] ? 0 : Carbon::now()->addMinutes($config['lifetime']);
    }

    /**
     * Determine if a session driver has been configured.
     *
     * @return bool
     */
    protected function sessionConfigured()
    {
        return ! is_null(Arr::get($this->manager->getSessionConfig(), 'driver'));
    }

    /**
     * Determine if the configured session driver is persistent.
     *
     * @param  array|null  $config
     * @return bool
     */
    protected function sessionIsPersistent(array $config = null)
    {
        $config = $config ?: $this->manager->getSessionConfig();

        return ! in_array($config['driver'], array(null, 'array'));
    }
}
