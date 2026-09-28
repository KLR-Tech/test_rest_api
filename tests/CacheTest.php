<?php

declare(strict_types=1);

namespace Tests;

use App\Cache\Cache;
use PHPUnit\Framework\TestCase;

final class CacheTest extends TestCase
{
    private Cache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        // Instantiate using test configuration or default localhost
        $this->cache = new Cache('127.0.0.1', 11211, 'test_cache_pool');
    }

    protected function tearDown(): void
    {
        // Clean up test key after every test run
        $this->cache->forget('test_key_123');
        parent::tearDown();
    }

    public function test_remember_executes_callback_on_cache_miss(): void
    {
        $executed = false;

        $result = $this->cache->remember('test_key_123', 10, function () use (&$executed) {
            $executed = true;
            return 'cached_value';
        });

        $this->assertTrue($executed);
        $this->assertEquals('cached_value', $result);

        // Clean up
        $this->cache->forget('test_key_123');
    }
}