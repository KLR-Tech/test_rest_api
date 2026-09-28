<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\DB;
use App\Cache\Cache;
use App\Traits\ApiResponse;

class TaskController
{
    use ApiResponse;

    private array $dbConfig;
    private Cache $cache;

    public function __construct(
        ?Cache $cache = null
    )
    {
        $this->dbConfig = require __DIR__ . '/../../config/database.php';

        $this->cache = $cache ?? new Cache();
    }

    /**
     * GET /api/tasks
     */
    public function index(): void
    {
/*
 * Without memcached:        
        $stmt = DB::run($this->dbConfig, 'SELECT id, title, completed, created_at, updated_at FROM tasks');
        $tasks = $stmt->fetchAll();
*/

// Cache the result for 60 seconds
        $tasks = $this->cache->remember('tasks_all', 60, function () {
            $stmt = DB::run($this->dbConfig, 'SELECT id, title, completed, created_at, updated_at FROM tasks');
            return $stmt->fetchAll();
        });        
        
        $this->json([
            'status' => 'success',
            'data' => $tasks
        ]);
    }

    /**
     * GET /api/tasks/{id}
     */
    public function show(string $id): void
    {
/*        
        $stmt = DB::run(
            $this->dbConfig, 
            'SELECT id, title, completed, created_at, updated_at FROM tasks WHERE id = :id LIMIT 1', 
            ['id' => (int)$id]
        );
        $task = $stmt->fetch();
*/
        $taskId = (int)$id;
        $cacheKey = "task_{$taskId}";
        // Cache single task for 300 seconds (5 mins)
        $task = $this->cache->remember($cacheKey, 300, function () use ($taskId) {
            $sql = 'SELECT id, title, completed, created_at, updated_at FROM tasks WHERE id = :id LIMIT 1';
            $stmt = DB::run($this->dbConfig, $sql, ['id' => $taskId]);
            return $stmt->fetch();
        });

        if (!$task) {
            $this->error('Task not found.', 404);
        }

        $this->json([
            'status' => 'success',
            'data' => $task
        ]);
    }


    /**
     * POST /api/tasks
     */
    public function store(): void
    {
        $data = $this->getJsonBody();

        if (empty($data['title'])) {
            $this->error('Missing required fields: title is required.', 422);
        }

        $sql = 'INSERT INTO tasks (title, created_at) VALUES (:title, NOW())';
        $params = [
            'title'  => htmlspecialchars((string)$data['title'], ENT_QUOTES, 'UTF-8'),
//            'price' => (float)$data['price'],
        ];

        DB::run($this->dbConfig, $sql, $params);
        
        $pdo = DB::getConnection($this->dbConfig);
        $newId = (int)$pdo->lastInsertId();

        // Clear task list cache so GET /api/tasks returns the new record immediately
        $this->cache->forget('tasks_all');

        $this->json([
            'status' => 'success',
            'message' => 'Task created successfully.',
            'data' => [
                'id' => $newId,
                'title' => $data['title'],
//                'price' => (float)$data['price']
            ]
        ], 201);
    }

/**
     * PUT /api/tasks/{id} - Update an existing task
     */
    public function update(string $id): void
    {
        $taskId = (int)$id;

        // 1. Check if the record exists
        $stmt = DB::run($this->dbConfig, 'SELECT id FROM tasks WHERE id = :id LIMIT 1', ['id' => $taskId]);
        if (!$stmt->fetch()) {
            $this->error('Task not found.', 404);
        }

        // 2. Parse the JSON body payload
        $data = $this->getJsonBody();

        if (empty($data['title'])) {
            $this->error('The title field is required for updating.', 422);
        }

        // 3. Execute UPDATE query
        $sql = 'UPDATE tasks 
                SET title = :title
                  , updated_at = NOW()
        ';

        $params = [
            'id'          => $taskId,
            'title'       => htmlspecialchars((string)$data['title'], ENT_QUOTES, 'UTF-8'),
//            'description' => isset($data['description']) ? htmlspecialchars((string)$data['description'], ENT_QUOTES, 'UTF-8') : null,
//            'status'      => $data['status'] ?? 'pending',
        ];
        if(isset($data['completed'])) {
            $sql .= ' , completed = :completed';
            $params['completed'] = (int)in_array((int)$data['completed'],[0,1]) ? (int)$data['completed'] : 0;
        }

        $sql .= ' WHERE id = :id';


        DB::run($this->dbConfig, $sql, $params);

        // Invalidate both the individual task cache and the full list cache
        $this->cache->forget("task_{$taskId}");
        $this->cache->forget('tasks_all');

        $this->json([
            'status'  => 'success',
            'message' => 'Task updated successfully.',
            'data'    => array_merge(['id' => $taskId], $params)
        ]);
    }

    /**
     * DELETE /api/tasks/{id} - Delete a task by ID
     */
    public function destroy(string $id): void
    {
        $taskId = (int)$id;

        // 1. Check if record exists
        $stmt = DB::run($this->dbConfig, 'SELECT id FROM tasks WHERE id = :id LIMIT 1', ['id' => $taskId]);
        if (!$stmt->fetch()) {
            $this->error('Task not found.', 404);
        }

        // 2. Delete record
        DB::run($this->dbConfig, 'DELETE FROM tasks WHERE id = :id', ['id' => $taskId]);

        // Invalidate caches
        $this->cache->forget("task_{$taskId}");
        $this->cache->forget('tasks_all');

        // 3. Return 200 OK or 204 No Content
        $this->json([
            'status'  => 'success',
            'message' => 'Task deleted successfully.'
        ]);
    }
    
}