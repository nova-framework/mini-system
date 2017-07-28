<?php

namespace Mini\Foundation\Http\Middleware;

use Mini\Http\Exception\PostTooLargeException;

use Closure;


class VerifyPostSize
{
    /**
     * Handle an incoming request.
     *
     * @param  \Mini\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Mini\Http\Exception\PostTooLargeException
     */
    public function handle($request, Closure $next)
    {
        $contentLength = $request->server('CONTENT_LENGTH');

        if ($contentLength > $this->getPostMaxSize()) {
            throw new PostTooLargeException();
        }

        return $next($request);
    }

    /**
     * Determine the server 'post_max_size' as bytes.
     *
     * @return int
     */
    protected function getPostMaxSize()
    {
        $postMaxSize = ini_get('post_max_size');

        $multiplier = substr($postMaxSize, -1);

        $postMaxSize = (int) $postMaxSize;

        switch (strtoupper($multiplier)) {
            case 'M':
                return $postMaxSize * 1048576;

            case 'K':
                return $postMaxSize * 1024;

            case 'G':
                return $postMaxSize * 1073741824;
        }

        return $postMaxSize;
    }
}
