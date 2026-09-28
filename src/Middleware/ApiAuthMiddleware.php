<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Http\HttpStatusCode;

class ApiAuthMiddleware implements MiddlewareInterface
{
    private string $validToken = 'secret-api-key-12345'; // Store in config / .env in production

    public function handle(callable $next): mixed
    {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

        if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorized('Missing or malformed Authorization header.');
        } 

        $token = $matches[1];

        if ($token !== $this->validToken) {
            return $this->unauthorized('Invalid or expired API token.');
        }

        // Token is valid; proceed to the next layer
        return $next();
    }

    private function unauthorized(string $message): null
    {

        HttpStatusCode::UNAUTHORIZED_401->setResponseCode();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => HttpStatusCode::UNAUTHORIZED_401->label(), 'message' => $message]);
//        exit; // messing up the logging middleware
        return null;
    }
}