<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Router;
use App\Middleware\CorsMiddleware;
use App\Middleware\ApiAuthMiddleware;
use App\Middleware\LoggingMiddleware;
use App\Middleware\RateLimitingMiddleware;

use App\Controllers\ProductController;
use App\Controllers\TaskController;

use App\Http\HttpStatusCode;

/* In Middleware now
// Enable CORS for API clients
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle browser pre-flight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
*/
/*
// If no Composer
// Native PSR-4 Autoloader replacement
spl_autoload_register(function ($class) {
    // Project namespace prefix
    $prefix = 'App\\';
    // Base directory for the namespace prefix
    $base_dir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return; // Move to the next registered autoloader if not in App\
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
*/

$router = new Router();

// Attach Global Middleware (runs on all requests)
$router->use(new CorsMiddleware());
$router->use(new LoggingMiddleware());
$router->use(new RateLimitingMiddleware([
    ['type' => 'client', 'max' => 10, 'window' => 60],
    ['type' => 'endpoint', 'max' => 3, 'window' => 60],
    ['type' => 'cooldown', 'window' => 10], // post/put/patch
]));

// Instantiate protected middleware
$auth = new ApiAuthMiddleware();

// --- API Routes --- Products
$router->get('/api/products', [ProductController::class, 'index']);
$router->get('/api/products/{id}', [ProductController::class, 'show']);
$router->post('/api/products', [ProductController::class, 'store']);

// --- API Routes --- Tasks
$router->get('/api/tasks', [TaskController::class, 'index']);
$router->get('/api/tasks/{id}', [TaskController::class, 'show']);
$router->post('/api/tasks', [TaskController::class, 'store'], [$auth]);
$router->put('/api/tasks/{id}', [TaskController::class, 'update'], [$auth]);
$router->patch('/api/tasks/{id}', [TaskController::class, 'update'], [$auth]); // alias - testing
$router->delete('/api/tasks/{id}', [TaskController::class, 'delete'], [$auth]);


$router->get('/api/check', function () {
    HttpStatusCode::OK_200->setResponseCode();
    header('Content-Type: application/json');
    echo json_encode(['status' => 'OK']);
});


// --- Page Routes
$router->get('/', function () {
    HttpStatusCode::OK_200->setResponseCode();
    header('Content-Type: application/json');
    echo json_encode(["message" => "Test API"]);
});


// Execute the request
$router->dispatch($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);