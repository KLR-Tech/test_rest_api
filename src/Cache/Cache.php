<?php

declare(strict_types=1);

namespace App\Cache;

use Memcached;
use RuntimeException;

final class Cache
{
    private Memcached $client;

    public function __construct(
        string $host = '127.0.0.1',
        int $port = 11211,
        string $persistentId = 'app_cache_pool'
    ) {
        // Passing a persistent ID reuses the underlying connection across HTTP requests
        $this->client = new Memcached($persistentId);

        // Prevent adding duplicate servers on persistent connections
        if (empty($this->client->getServerList())) {
            $this->client->addServer($host, $port);
            
            // Optimization options
            $this->client->setOptions([
                Memcached::OPT_COMPRESSION => true,
                Memcached::OPT_CONNECT_TIMEOUT => 1000, // 1 second connection timeout
            ]);
        }

        // Fast healthcheck without pulling full stats
        $version = $this->client->getVersion();
        $serverKey = "{$host}:{$port}";

        if (!isset($version[$serverKey]) || $version[$serverKey] === '255.255.255') {
            throw new RuntimeException("Could not connect to Memcached server at {$serverKey}.");
        }

/*
            // Verify connection
            $stats = self::$instance->getStats();
            if (empty($stats) || current($stats)['pid'] === -1) {
                throw new RuntimeException('Could not connect to Memcached server.');
            }
*/
    }


    public function get(string $key): mixed
    {
        return $this->client->get($key);
    }

    public function set(string $key, mixed $value, int $seconds): bool
    {
        return $this->client->set($key, $value, $seconds);
    }

    public function increment(string $key, int $offset = 1): int|false
    {
        return $this->client->increment($key, $offset);
    }

    // Get underlying Memcached instance if direct access is needed.
    public function getClient(): Memcached
    {
        return $this->client;
    }

    // Fetch item or execute callback, store result, and return.
    public function remember(string $key, int $seconds, callable $callback): mixed
    {
        $value = $this->client->get($key);

        if ($this->client->getResultCode() === Memcached::RES_SUCCESS) {
            return $value;
        }

        $freshData = $callback();

        // Avoid caching NULL 
        $this->client->set($key, $freshData, $seconds);

        return $freshData;
    }

    /**
     * Delete an item from cache.
     */
    public function forget(string $key): bool
    {
        return $this->client->delete($key);
    }
}