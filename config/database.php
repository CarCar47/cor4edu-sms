<?php

namespace Cor4Edu\Config;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database Connection Class
 * Following Gibbon patterns for secure database connectivity
 */
class Database
{
    private static ?PDO $instance = null;
    private static ?self $databaseInstance = null;

    private function __construct()
    {
        // Private constructor to prevent direct instantiation
    }

    /**
     * Get singleton PDO instance
     * @return PDO
     * @throws RuntimeException
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::createConnection();
        }

        return self::$instance;
    }

    /**
     * Create database connection with proper error handling
     * Supports both standard TCP connections and Google Cloud SQL Unix sockets
     * @throws RuntimeException
     */
    private static function createConnection(): void
    {
        $dbName = $_ENV['DB_NAME'] ?? 'cor4edu_sms';
        $username = $_ENV['DB_USERNAME'] ?? 'root';
        $password = $_ENV['DB_PASSWORD'] ?? '';

        // Detect Google Cloud SQL connection via Unix socket
        $cloudSqlSocket = $_ENV['DB_SOCKET'] ?? null;

        if ($cloudSqlSocket) {
            // Google Cloud SQL Unix socket connection
            // Format: /cloudsql/PROJECT_ID:REGION:INSTANCE_NAME
            $dsn = "mysql:unix_socket={$cloudSqlSocket};dbname={$dbName};charset=utf8mb4";
        } else {
            // Standard TCP connection (localhost/development)
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $port = $_ENV['DB_PORT'] ?? '3306';
            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        }

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_general_ci"
        ];

        try {
            self::$instance = new PDO($dsn, $username, $password, $options);

            // Test connection
            self::$instance->query('SELECT 1');

        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Test database connection
     * @return bool
     */
    public static function testConnection(): bool
    {
        try {
            $pdo = self::getInstance();
            $pdo->query('SELECT 1');
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get database instance (for dependency injection)
     * @return self
     */
    public static function getDatabaseInstance(): self
    {
        if (self::$databaseInstance === null) {
            self::$databaseInstance = new self();
        }
        return self::$databaseInstance;
    }

    /**
     * Execute SQL file
     * @param string $filePath
     * @return bool
     * @throws RuntimeException
     */
    public static function executeSqlFile(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("SQL file not found: {$filePath}");
        }

        $sql = file_get_contents($filePath);
        if ($sql === false) {
            throw new RuntimeException("Could not read SQL file: {$filePath}");
        }

        try {
            $pdo = self::getInstance();
            $pdo->exec($sql);
            return true;
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Failed to execute SQL file {$filePath}: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Close connection (for cleanup)
     */
    public static function closeConnection(): void
    {
        self::$instance = null;
        self::$databaseInstance = null;
    }
}