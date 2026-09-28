<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Cache\Cache;
use App\Http\HttpStatusCode;

class RateLimitingMiddleware implements MiddlewareInterface
{
    private Cache $cache;
    private array $rules;

    /**
     * @param array $rules List of rate limiting rules to apply.
     * @param Cache|null $cache Injected cache instance or fallback.
     */
    public function __construct(array $rules = [], ?Cache $cache = null)
    {
        $this->cache = $cache ?? new Cache();     
        // Default global rule if none provided
        $this->rules = !empty($rules) ? $rules : [
            ['type' => 'client', 'max' => 60, 'window' => 60]
        ];
    }

    public function handle(callable $next): mixed
    {
        $clientIp = $this->getClientIp();
        $method    = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri       = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        foreach ($this->rules as $rule) {
            $type = $rule['type'] ?? 'client';

            // -----------------------------------------------------------------
            // Rule 1: Global Per-Client Limit (Sliding Window)
            // -----------------------------------------------------------------
            if ($type === 'client') {
                $maxRequests   = (int) ($rule['max'] ?? 60);
                $windowSeconds = (int) ($rule['window'] ?? 60);
                $keyPrefix     = "rate_limit:client:" . md5($clientIp);

                $blockResponse = $this->checkSlidingWindow($keyPrefix, $maxRequests, $windowSeconds);
                if ($blockResponse !== null) {
                    return $blockResponse; // Short-circuit, pass JSON back through middleware stack
                }
            }

            // -----------------------------------------------------------------
            // Rule 2: Per-Endpoint Limit (Sliding Window)
            // -----------------------------------------------------------------
            if ($type === 'endpoint') {
                $maxRequests   = (int) ($rule['max'] ?? 10);
                $windowSeconds = (int) ($rule['window'] ?? 60);
                $keyPrefix     = "rate_limit:endpoint:" . md5($clientIp . ':' . $method . ':' . $uri);

                $blockResponse = $this->checkSlidingWindow($keyPrefix, $maxRequests, $windowSeconds);
                if ($blockResponse !== null) {
                    return $blockResponse;
                }
            }

            // -----------------------------------------------------------------
            // Rule 3: Payload Cooldown / Duplicate Request Lock
            // Prevent same POST/PUT/PATCH payload within X seconds
            // -----------------------------------------------------------------
            if ($type === 'cooldown' && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                $cooldownSeconds = (int) ($rule['window'] ?? 10);
                
                // Read input stream
                $rawBody     = file_get_contents('php://input') ?: '';
                $payloadHash = md5($method . ':' . $uri . ':' . $rawBody);
                $cooldownKey = "cooldown:" . md5($clientIp) . ":" . $payloadHash;

                // Check if key exists in cache
                if ($this->cache->get($cooldownKey) !== false) {
                    return $this->tooManyRequests(
                        $cooldownSeconds, 
                        "Duplicate request detected. Please wait {$cooldownSeconds} seconds before repeating this action."
                    );
                }

                // Lock payload signature for $cooldownSeconds
                $this->cache->set($cooldownKey, 1, $cooldownSeconds);
            }
        }

        // Proceed down the middleware chain
        return $next();
    }

    /**
     * Checks sliding window and returns JSON error string if limit is exceeded, or null if allowed.
     */
    private function checkSlidingWindow(string $keyPrefix, int $maxRequests, int $windowSeconds): ?string
    {
        $currentTime   = time();
        $currentBucket = (int) floor($currentTime / $windowSeconds);

        $currentWindowKey  = "{$keyPrefix}:{$currentBucket}";
        $previousWindowKey = "{$keyPrefix}:" . ($currentBucket - 1);

        $currentCount  = (int) $this->cache->get($currentWindowKey);
        $previousCount = (int) $this->cache->get($previousWindowKey);

        $timeIntoCurrentWindow = $currentTime % $windowSeconds;
        $previousWeight        = 1 - ($timeIntoCurrentWindow / $windowSeconds);

        $estimatedRequests = ($previousCount * $previousWeight) + $currentCount;

        if ($estimatedRequests >= $maxRequests) {
            $retryAfter = max(1, $windowSeconds - $timeIntoCurrentWindow);
            return $this->tooManyRequests(
                $retryAfter, 
                "Rate limit exceeded. Maximum {$maxRequests} requests per {$windowSeconds}s allowed."
            );
        }

        // Increment or initialize bucket
        if ($currentCount === 0) {
            $this->cache->set($currentWindowKey, 1, $windowSeconds * 2);
        } else {
            $this->cache->increment($currentWindowKey);
        }

        $remaining = max(0, (int) floor($maxRequests - ($estimatedRequests + 1)));

        // Set informatory headers for successful requests
        header("X-RateLimit-Limit: {$maxRequests}", false);
        header("X-RateLimit-Remaining: {$remaining}", false);

        return null;
    }

    private function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function tooManyRequests(int $retryAfter, string $message): string
    {
        HttpStatusCode::TOO_MANY_REQUESTS_429->setResponseCode();
        header('Content-Type: application/json; charset=utf-8');
        header("X-RateLimit-Remaining: 0");
        header("Retry-After: {$retryAfter}");

        $response = json_encode([
            'status'      => 'error',
            'error'       => HttpStatusCode::TOO_MANY_REQUESTS_429->label(),
            'message'     => $message,
            'retry_after' => $retryAfter
        ]);

//        echo $response;
        return $response;
    }
}