<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

final class DB
{
    private static ?PDO $instance = null;

    private function __construct() {}

    public static function getConnection(array $config): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $config['host'] ?? '127.0.0.1',
                $config['port'] ?? '3306',
                $config['dbname'] ?? '',
                $config['charset'] ?? 'utf8mb4'
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            try {
                self::$instance = new PDO(
                    $dsn,
                    $config['user'] ?? '',
                    $config['password'] ?? '',
                    $options
                );
            } catch (PDOException $e) {
                throw new RuntimeException('Database connection failed: ' . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function run(array $config, string $sql, array $params = []): \PDOStatement
    {
//        echo $sql; exit;
        $pdo = self::getConnection($config);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}