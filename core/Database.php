<?php

declare(strict_types=1);

namespace Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * PDO Database Wrapper Singleton for NOEI CMS.
 * Strictly enforces prepared statements and utf8mb4 encoding across all database operations.
 */
class Database
{
    private static ?Database $instance = null;
    private ?PDO $pdo = null;

    /**
     * Private constructor for Singleton pattern.
     *
     * @param array<string, mixed> $config
     */
    private function __construct(array $config)
    {
        $driver = strtolower((string)($config['driver'] ?? 'mysql'));
        $host = (string)($config['host'] ?? '127.0.0.1');
        $port = (int)($config['port'] ?? 3306);
        $dbname = (string)($config['dbname'] ?? ($config['database'] ?? ''));
        $username = (string)($config['username'] ?? '');
        $password = (string)($config['password'] ?? '');
        $charset = (string)($config['charset'] ?? 'utf8mb4');

        if ($driver === 'sqlite') {
            $dsn = "sqlite:{$dbname}";
        } else {
            $dsn = "{$driver}:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $isMysql = ($driver === 'mysql' || str_starts_with($dsn, 'mysql:'));

        if ($isMysql && defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci";
        }

        try {
            $this->pdo = new PDO($dsn, $username, $password, $options);
            if ($isMysql) {
                $this->pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Get or initialize the Database instance.
     *
     * @param array<string, mixed>|null $config
     * @return Database
     */
    public static function getInstance(?array $config = null): Database
    {
        if (self::$instance === null) {
            if ($config === null) {
                $configFile = dirname(__DIR__) . '/config/database.php';
                if (file_exists($configFile)) {
                    $config = require $configFile;
                } else {
                    throw new RuntimeException("Database configuration file not found at {$configFile}.");
                }
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /**
     * Inject an existing PDO instance (useful for testing or custom setup).
     *
     * @param PDO $pdo
     */
    public static function setPdo(PDO $pdo): void
    {
        if (self::$instance === null) {
            self::$instance = new self(['driver' => 'sqlite', 'dbname' => ':memory:']);
        }
        self::$instance->pdo = $pdo;
    }

    /**
     * Reset database instance (useful for testing).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Get the underlying PDO instance.
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        if ($this->pdo === null) {
            throw new RuntimeException("PDO connection has not been initialized.");
        }
        return $this->pdo;
    }

    /**
     * Prepare and execute a SQL statement with bound parameters.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @return PDOStatement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row from a query result.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @param int $fetchMode
     * @return mixed
     */
    public function fetch(string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): mixed
    {
        return $this->query($sql, $params)->fetch($fetchMode);
    }

    /**
     * Fetch all matching rows from a query result.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @param int $fetchMode
     * @return array<int, mixed>
     */
    public function fetchAll(string $sql, array $params = [], int $fetchMode = PDO::FETCH_ASSOC): array
    {
        return $this->query($sql, $params)->fetchAll($fetchMode);
    }

    /**
     * Fetch a single column value from a query result.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @param int $columnNumber
     * @return mixed
     */
    public function fetchColumn(string $sql, array $params = [], int $columnNumber = 0): mixed
    {
        return $this->query($sql, $params)->fetchColumn($columnNumber);
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE query and return affected row count.
     *
     * @param string $sql
     * @param array<string|int, mixed> $params
     * @return int Number of affected rows
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /**
     * Get the last inserted ID.
     *
     * @param string|null $name
     * @return string
     */
    public function lastInsertId(?string $name = null): string
    {
        return $this->getPdo()->lastInsertId($name);
    }

    /**
     * Begin a database transaction.
     *
     * @return bool
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }

    /**
     * Commit the active transaction.
     *
     * @return bool
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }

    /**
     * Rollback the active transaction.
     *
     * @return bool
     */
    public function rollBack(): bool
    {
        return $this->getPdo()->rollBack();
    }

    /**
     * Check if currently inside a transaction.
     *
     * @return bool
     */
    public function inTransaction(): bool
    {
        return $this->getPdo()->inTransaction();
    }
}
