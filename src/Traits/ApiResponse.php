<?php

declare(strict_types=1);

namespace App\Traits;

trait ApiResponse
{
    /**
     * Send a structured JSON success response.
     */
    protected function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
//        exit;  // we can not have this here, because it terminates the Middleware for logging, since it process after the call
    }

    /**
     * Send a JSON error response.
     */
    protected function error(string $message, int $statusCode = 400): void
    {
        $this->json(['error' => $message], $statusCode);
    }

    /**
     * Parse raw JSON input sent via POST/PUT body.
     */
    protected function getJsonBody(): array
    {
        $rawInput = file_get_contents('php://input');
        
        if (empty($rawInput)) {
            return [];
        }

        try {
            return json_decode($rawInput, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->error('Invalid JSON payload provided.', 400);
            exit;
        }
    }
}