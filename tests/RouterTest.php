<?php

declare(strict_types=1);

namespace Tests;

use App\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function test_matches_simple_get_route(): void
    {
        // Capture standard output from echo statements
        $this->expectOutputString('Hello World');

        $this->router->get('/hello', function () {
            echo 'Hello World';
        });

        $this->router->dispatch('/hello', 'GET');
    }

    public function test_matches_dynamic_parameters(): void
    {
        $this->expectOutputString('User ID is 42');

        $this->router->get('/users/{id}', function (string $id) {
            echo 'User ID is ' . $id;
        });

        $this->router->dispatch('/users/42', 'GET');
    }

    public function test_returns_404_when_route_not_found(): void
    {
        $this->expectOutputString(json_encode(["error" => "Not Found"]));

        $this->router->get('/home', function () {
            echo 'Home';
        });

        // Request a path that hasn't been registered
        $this->router->dispatch('/unknown-path', 'GET');

        $this->assertSame(404, http_response_code());
    }
}