<?php

declare(strict_types=1);

namespace App\Http;

enum HttpStatusCode: int
{
    // 2xx Success
    case OK_200 = 200;
    case CREATED_201 = 201;
    case NO_CONTENT_204 = 204;

    // 4xx Client Errors
    case BAD_REQUEST_400 = 400;
    case UNAUTHORIZED_401 = 401;
    case FORBIDDEN_403 = 403;
    case NOT_FOUND_404 = 404;
    case UNPROCESSABLE_ENTITY_422 = 422;
    case TOO_MANY_REQUESTS_429 = 429;

    // 5xx Server Errors
    case INTERNAL_SERVER_ERROR_500 = 500;

    /**
     * Helper to set the http response code directly.
     */
    public function setResponseCode(): int|bool
    {
        return http_response_code($this->value);
    }

    /**
     * Standard status text for responses.
     */
    public function label(): string
    {
        return match($this) {
            self::OK_200 => 'OK',
            self::CREATED_201 => 'Created',
            self::NO_CONTENT_204 => 'No Content',
            self::BAD_REQUEST_400 => 'Bad Request',
            self::UNAUTHORIZED_401 => 'Unauthorized',
            self::FORBIDDEN_403 => 'Forbidden',
            self::NOT_FOUND_404 => 'Not Found',
            self::UNPROCESSABLE_ENTITY_422 => 'Unprocessable Entity',
            self::TOO_MANY_REQUESTS_429 => 'Too Many Requests',
            self::INTERNAL_SERVER_ERROR_500 => 'Internal Server Error',
        };
    }
}