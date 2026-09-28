<?php

declare(strict_types=1);

namespace App\Middleware;

class LoggingMiddleware implements MiddlewareInterface
{
    private string $logFile;
    private array $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key'];

    public function __construct(?string $logFile = null)
    {
        $this->logFile = $logFile ?? __DIR__ . '/../../logs/'.date("Ymd").'_requests.log';

        $logDir = dirname($this->logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public function handle(callable $next): mixed
    {
        $startTime = microtime(true);

        // 1. Capture request body safely
        $rawBody = file_get_contents('php://input');
        $parsedBody = !empty($rawBody) ? json_decode($rawBody, true) : null;
        
        // Format body for concise single-line logging (or "NONE" if empty)
        $bodyLog = $parsedBody !== null 
            ? json_encode($parsedBody, JSON_UNESCAPED_SLASHES) 
            : (!empty($rawBody) ? trim(preg_replace('/\s+/', ' ', $rawBody)) : 'NONE');

        // 2. Capture and redact sensitive headers
        $sanitizedHeaders = $this->getSanitizedHeaders();
        $headersLog = json_encode($sanitizedHeaders, JSON_UNESCAPED_SLASHES);

        // 3. Process downstream request (Middlewares + Controller)
        $result = $next();

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);
        $method     = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $uri        = $_SERVER['REQUEST_URI'] ?? '/';
        $statusCode = http_response_code();
        $ip         = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $timestamp  = date('Y-m-d H:i:s');

        // Formatted log entry
        $logEntry = sprintf(
            "[%s] [%s ms] %s %s | Status: %d | IP: %s" . PHP_EOL .
            "  └─ Headers: %s" . PHP_EOL .
            "  └─ Body: %s" . PHP_EOL,
            $timestamp,
            $durationMs,
            $method,
            $uri,
            $statusCode,
            $ip,
            $headersLog,
            $bodyLog
        );

        // Clean double spaces if URI was clean
//        $logEntry = preg_replace('/\s+/', ' ', $logEntry) . PHP_EOL;

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);

        return $result;
    }

    /**
     * Retrieve HTTP headers and mask sensitive tokens/passwords.
     */
    private function getSanitizedHeaders(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $sanitized = [];

        foreach ($headers as $key => $value) {
            $lowerKey = strtolower($key);
            if (in_array($lowerKey, $this->sensitiveHeaders, true)) {
                // Redact token values (e.g. "Bearer secret-123" -> "Bearer ***REDACTED***")
                if (preg_match('/^(Bearer|Basic)\s+(.*)$/i', $value, $matches)) {
                    $sanitized[$key] = $matches[1] . ' ***'.substr($matches[2],-4); // ' ***REDACTED***';
                } else {
                    $sanitized[$key] = ' ***'.substr($value,-4); // '***REDACTED***';
                }
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}