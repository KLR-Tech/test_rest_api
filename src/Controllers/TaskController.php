<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database\DB;
use App\Cache\Cache;
use App\Traits\ApiResponse;
use App\DTO\CreateTaskDto;
use App\DTO\UpdateTaskDto;

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
            $sql = 'SELECT id, title, dscr, completed, created_at, updated_at FROM tasks WHERE id = :id LIMIT 1';
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
        $rawBody = $this->getJsonBody();

        // Hydrate & validate CreateTaskDto (throws 422 ValidationException if invalid)
        $taskDto = CreateTaskDto::fromArray($rawBody);

        $sql = 'INSERT INTO tasks (title, dscr, completed, created_at) VALUES (:title, :dscr, :completed, NOW())';
        $params = [
//            'title'  => htmlspecialchars((string)$data['title'], ENT_QUOTES, 'UTF-8'),
            'title' => $taskDto->title,
            'dscr' => $taskDto->dscr,
            'completed' => $taskDto->completed
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
                'title' => $taskDto->title,
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
        $rawBody = $this->getJsonBody();
        $taskDto = UpdateTaskDto::fromArray($rawBody);

/*
        if (empty($data['title'])) {
            $this->error('The title field is required for updating.', 422);
        }
*/

// Get only the fields sent by the client
        $fieldsToUpdate = $taskDto->getPresentFields();
        if (empty($fieldsToUpdate)) {
            $this->error('No fields provided for update.', 400);
        }

        // build UPDATE query
        $sql = 'UPDATE tasks 
                SET updated_at = NOW()
        ';

        $params = [
            'id' => $taskId,
        ];

        foreach($fieldsToUpdate as $fldKey => $fldVal) {
            $sql .= ' , '.$fldKey.' = :'.$fldKey;
            $params[$fldKey] = $taskDto->$fldKey;
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