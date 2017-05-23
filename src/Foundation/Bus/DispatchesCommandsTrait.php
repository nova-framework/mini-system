<?php

namespace Mini\Foundation\Bus;


trait DispatchesCommandsTrait
{
    /**
     * Dispatch a command.
     *
     * @param  mixed  $command
     * @return mixed
     */
    protected function dispatch($command)
    {
        return app('Mini\Bus\Contracts\DispatcherInterface')->dispatch($command);
    }
}
