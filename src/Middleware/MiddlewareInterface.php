<?php

declare(strict_types=1);

namespace App\Middleware;

interface MiddlewareInterface
{
    /**
     * Process an incoming request and pass control to the next layer in the pipeline.
     * 
     * @param callable $next The next middleware layer or controller action.
     * @return mixed
     */
    public function handle(callable $next): mixed;
}