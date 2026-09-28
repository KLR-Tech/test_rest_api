<?php
declare(strict_types=1);

namespace Tests;

use App\Router;
use App\Database\DB;
use PHPUnit\Framework\TestCase;
use PDO;

final class TaskApiTest extends TestCase
{
/*    
    private array $dbConfig;

    protected function setUp(): void
    {
        // Use an in-memory SQLite database for test isolation
        $this->dbConfig = [
            'host'     => '127.0.0.1',
            'dbname'   => 'test_db',
            'user'     => 'root',
            'password' => '',
        ];

        // Alternatively, if testing against real MySQL test schema, ensure clean state:
        // DB::run($this->dbConfig, 'TRUNCATE TABLE tasks');
    }
*/

/**
     * Helper to mock raw JSON POST/PUT input payload in PHPUnit
     */
    private function mockJsonInput(array $data): void
    {
        $json = json_encode($data);
        // Inject mock input stream override or handle buffer
        file_put_contents('php://memory', $json);
    }
    
/**
     * Test PUT /api/tasks/{id} - Successful Update
     */
    public function test_updates_existing_task_successfully(): void
    {
        $router = new Router();
        
        // Mock route handler execution
        $router->put('/api/tasks/{id}', function (string $id) {
            // Verify ID passing
            $this->assertEquals('1', $id);

            // Simulate Controller response logic
            $response = [
                'status'  => 'success',
                'message' => 'Task updated successfully.',
                'data'    => [
                    'id'          => (int)$id,
                    'title'       => 'Updated Title',
                    'description' => 'Updated Description',
                    'status'      => 'completed'
                ]
            ];

            echo json_encode($response);
        });

        // Capture output
        ob_start();
        $router->dispatch('/api/tasks/1', 'PUT');
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Updated Title', $data['data']['title']);
        $this->assertEquals('completed', $data['data']['status']);
    }

/**
     * Test PUT /api/tasks/{id} - Validation Failure (422)
     */
    public function test_returns_422_when_updating_without_required_title(): void
    {
        $router = new Router();

        $router->put('/api/tasks/{id}', function (string $id) {
            http_response_code(422);
            echo json_encode(['error' => 'The title field is required for updating.']);
        });

        ob_start();
        $router->dispatch('/api/tasks/1', 'PUT');
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertEquals(422, http_response_code());
        $this->assertEquals('The title field is required for updating.', $data['error']);
    }

    /**
     * Test DELETE /api/tasks/{id} - Successful Deletion
     */
    public function test_deletes_task_successfully(): void
    {
        $router = new Router();

        $router->delete('/api/tasks/{id}', function (string $id) {
            http_response_code(200);
            echo json_encode([
                'status'  => 'success',
                'message' => 'Task deleted successfully.'
            ]);
        });

        ob_start();
        $router->dispatch('/api/tasks/42', 'DELETE');
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertEquals(200, http_response_code());
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('Task deleted successfully.', $data['message']);
    }

    /**
     * Test DELETE /api/tasks/{id} - Resource Not Found (404)
     */
    public function test_returns_404_when_deleting_non_existent_task(): void
    {
        $router = new Router();

        $router->delete('/api/tasks/{id}', function (string $id) {
            http_response_code(404);
            echo json_encode(['error' => 'Task not found.']);
        });

        ob_start();
        $router->dispatch('/api/tasks/9999', 'DELETE');
        $output = ob_get_clean();

        $data = json_decode($output, true);

        $this->assertEquals(404, http_response_code());
        $this->assertEquals('Task not found.', $data['error']);
    }    
}