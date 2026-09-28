<?php

declare(strict_types=1);

namespace App;

use App\Middleware\MiddlewareInterface;
use App\Exception\ValidationException;
use App\Http\HttpStatusCode;

class Router
{
    private array $routes = [];
    private array $globalMiddleware = [];
    
    /**
     * Add global middleware that executes on every request.
     */
    public function use(MiddlewareInterface $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }


    /**
     * Store the route internally with regex pattern conversion.
     * Register a route with optional route-specific middleware.
     */
    public function addRoute(string $method, string $path, callable|array $handler, array $middleware = []): void
    {
        // Convert route placeholders like {id} into named regex capture groups
//        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<\1>[^/]+)', $path);
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $path);
        
        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'pattern'    => "#^" . $pattern . "$#",
            'handler' => $handler,
            'middleware' => $middleware, // Route-specific middlewares
        ];
    }

    public function get(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function patch(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    public function put(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, callable|array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Match the current request against registered routes and dispatch.
     */
    public function dispatch(string $uri, string $method): void
    {
        // Strip query strings (e.g., /users?sort=asc -> /users) and trailing slashes
        $parsedUri = parse_url($uri, PHP_URL_PATH);
        $parsedUri = rtrim($parsedUri, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] === strtoupper($method) && preg_match($route['pattern'], $parsedUri, $matches)) {
                array_shift($matches); // Remove full match

                // Combine global and route-specific middleware
//                $pipeline = array_merge($this->globalMiddleware, $route['middleware']);
                $pipeline = [...$this->globalMiddleware, ...$route['middleware']];

                // Build the execution chain backwards (onion style)
                $target = fn() => $this->executeHandler($route['handler'], $matches);

                $runner = array_reduce(
                    array_reverse($pipeline),
                    function (callable $next, MiddlewareInterface $middleware) {
                        return fn() => $middleware->handle($next);
                    },
                    $target
                );

                // Execute the pipeline
//                $runner();
//                return;

                try {
                    // Capture the pipeline result and output it if present
                    $result = $runner();
                    if (is_array($result) || $result instanceof \JsonSerializable) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                    } else if (is_string($result)) {
                        echo $result;
                    }
                } catch (ValidationException $e) {
                    HttpStatusCode::UNPROCESSABLE_ENTITY_422->setResponseCode();
                    header('Content-Type: application/json; charset=utf-8');

                    echo json_encode([
                        'error' => HttpStatusCode::UNPROCESSABLE_ENTITY_422->label(),
                        'message' => $e->getMessage(),
                        'errors' => $e->getErrors(),
                    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                }                    

                return;
            }
        }

        // Return a 404 response if no route matches
        HttpStatusCode::NOT_FOUND_404->setResponseCode();
        header('Content-Type: application/json');
        echo json_encode(['error' => HttpStatusCode::NOT_FOUND_404->label()]);
    }

    /**
     * Execute the matched handler (Closure or Controller Array).
     */
    private function executeHandler(callable|array $handler, array $params): void
    {
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        if (is_array($handler)) {
            [$class, $method] = $handler;

            if (class_exists($class)) {
                $controller = new $class();
                if (method_exists($controller, $method)) {
                    call_user_func_array([$controller, $method], $params);
                    return;
                }
            }
        }

        HttpStatusCode::INTERNAL_SERVER_ERROR_500->setResponseCode();
        echo json_encode(['error' => 'Handler execution failed.']);
    }
}