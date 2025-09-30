<?php

namespace Cor4Edu\Domain;

use PDO;
use Cor4Edu\Config\Database;

/**
 * Base Gateway class following Gibbon patterns
 * Simple database access abstraction
 */
abstract class Gateway
{
    protected $pdo;

    public function __construct($db)
    {
        $this->pdo = $db;
    }

    /**
     * Get database connection
     * @return PDO
     */
    protected function db(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a select query and return results
     * @param string $sql
     * @param array $params
     * @return array
     */
    protected function select(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute a select query and return single result
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    protected function selectOne(string $sql, array $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Insert data into table
     * @param string $table
     * @param array $data
     * @return int Last insert ID
     */
    protected function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Update data in table
     * @param string $table
     * @param array $data
     * @param string $where
     * @param array $whereParams
     * @return int Affected rows
     */
    protected function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = array_map(fn($col) => "{$col} = :{$col}", array_keys($data));

        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge($data, $whereParams));

        return $stmt->rowCount();
    }

    /**
     * Delete from table
     * @param string $table
     * @param string $where
     * @param array $whereParams
     * @return int Affected rows
     */
    protected function delete(string $table, string $where, array $whereParams = []): int
    {
        $sql = "DELETE FROM {$table} WHERE {$where}";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereParams);

        return $stmt->rowCount();
    }
}