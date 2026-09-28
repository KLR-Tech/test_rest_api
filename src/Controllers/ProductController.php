<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Traits\ApiResponse;

class ProductController
{
    use ApiResponse;

    // Simulated database
    private static array $products = [
        1 => ['id' => 1, 'name' => 'Mechanical Keyboard', 'price' => 120.00],
        2 => ['id' => 2, 'name' => 'Wireless Mouse', 'price' => 45.00],
    ];

    /**
     * GET /api/products
     */
    public function index(): void
    {
        $this->json([
            'status' => 'success',
            'data' => array_values(self::$products)
        ]);
    }

    /**
     * GET /api/products/{id}
     */
    public function show(string $id): void
    {
        $productId = (int)$id;

        if (!isset(self::$products[$productId])) {
            $this->error('Product not found.', 404);
        }

        $this->json([
            'status' => 'success',
            'data' => self::$products[$productId]
        ]);
    }

    /**
     * POST /api/products
     */
    public function store(): void
    {
        $data = $this->getJsonBody();

        // Basic Validation
        if (empty($data['name']) || empty($data['price'])) {
            $this->error('Missing required fields: name and price are required.', 422);
        }

        $newProduct = [
            'id' => count(self::$products) + 1,
            'name' => htmlspecialchars((string)$data['name'], ENT_QUOTES, 'UTF-8'),
            'price' => (float)$data['price'],
        ];

        self::$products[$newProduct['id']] = $newProduct;

        // Return 201 Created with the new resource
        $this->json([
            'status' => 'success',
            'message' => 'Product created successfully.',
            'data' => $newProduct
        ], 201);
    }
}