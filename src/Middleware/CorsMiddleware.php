<?php
declare(strict_types=1);

namespace App\Middleware;

class CorsMiddleware implements MiddlewareInterface
{
    public function handle(callable $next): mixed
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        // Intercept pre-flight OPTIONS requests immediately
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        return $next();
    }
}