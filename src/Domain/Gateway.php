<?php

namespace Cor4Edu\Domain;

use PDO;
use Cor4Edu\Config\Database;
use Cor4Edu\Database\QueryBuilder;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\InsertInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use Aura\SqlQuery\Common\DeleteInterface;

/**
 * Base Gateway class following Gibbon patterns
 * Enhanced with Aura SQL Query Builder for type-safe queries
 *
 * @version v1.0.0
 * @since v1.0.0
 */
abstract class Gateway
{
    protected $pdo;
    protected QueryBuilder $queryBuilder;

    public function __construct($db)
    {
        $this->pdo = $db;
        $this->queryBuilder = new QueryBuilder($db);
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
     * Create a new SELECT query (Aura SQL Query)
     *
     * Example:
     *   $query = $this->newSelect()
     *       ->cols(['*'])
     *       ->from('cor4edu_students')
     *       ->where('status = :status')
     *       ->bindValue('status', 'Active');
     *   $results = $this->runSelect($query);
     *
     * @return SelectInterface
     */
    protected function newSelect(): SelectInterface
    {
        return $this->queryBuilder->newSelect();
    }

    /**
     * Create a new INSERT query (Aura SQL Query)
     *
     * @return InsertInterface
     */
    protected function newInsert(): InsertInterface
    {
        return $this->queryBuilder->newInsert();
    }

    /**
     * Create a new UPDATE query (Aura SQL Query)
     *
     * @return UpdateInterface
     */
    protected function newUpdate(): UpdateInterface
    {
        return $this->queryBuilder->newUpdate();
    }

    /**
     * Create a new DELETE query (Aura SQL Query)
     *
     * @return DeleteInterface
     */
    protected function newDelete(): DeleteInterface
    {
        return $this->queryBuilder->newDelete();
    }

    /**
     * Execute a SELECT query built with Aura SQL Query
     *
     * @param SelectInterface $query
     * @return array
     */
    protected function runSelect(SelectInterface $query): array
    {
        return $this->queryBuilder->runSelect($query);
    }

    /**
     * Execute a SELECT query and return first result
     *
     * @param SelectInterface $query
     * @return array|false
     */
    protected function runSelectOne(SelectInterface $query): array|false
    {
        return $this->queryBuilder->runSelectOne($query);
    }

    /**
     * Execute an INSERT query built with Aura SQL Query
     *
     * @param InsertInterface $query
     * @return int Last insert ID
     */
    protected function runInsert(InsertInterface $query): int
    {
        return $this->queryBuilder->runInsert($query);
    }

    /**
     * Execute an UPDATE query built with Aura SQL Query
     *
     * @param UpdateInterface $query
     * @return int Affected rows
     */
    protected function runUpdate(UpdateInterface $query): int
    {
        return $this->queryBuilder->runUpdate($query);
    }

    /**
     * Execute a DELETE query built with Aura SQL Query
     *
     * @param DeleteInterface $query
     * @return int Affected rows
     */
    protected function runDelete(DeleteInterface $query): int
    {
        return $this->queryBuilder->runDelete($query);
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
