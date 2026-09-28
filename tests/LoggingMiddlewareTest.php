<?php

declare(strict_types=1);

namespace Tests;

use App\Middleware\LoggingMiddleware;
use PHPUnit\Framework\TestCase;

final class LoggingMiddlewareTest extends TestCase
{
    private string $testLogFile;

    protected function setUp(): void
    {
        $this->testLogFile = __DIR__ . '/test_app.log';
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testLogFile)) {
            unlink($this->testLogFile);
        }
    }

    public function test_logs_request_and_execution_time(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $middleware = new LoggingMiddleware($this->testLogFile);

        // Dummy next callback that sleeps briefly to simulate work
        $next = function () {
            usleep(10000); // 10ms
            http_response_code(200);
            return 'OK';
        };

        $result = $middleware->handle($next);

        $this->assertEquals('OK', $result);
        $this->assertFileExists($this->testLogFile);

        $logContent = file_get_contents($this->testLogFile);
        $this->assertStringContainsString('GET /api/test', $logContent);
        $this->assertStringContainsString('Status: 200', $logContent);
        $this->assertStringContainsString('ms', $logContent);
    }
}