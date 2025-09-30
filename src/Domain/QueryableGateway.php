<?php

namespace Cor4Edu\Domain;

/**
 * QueryableGateway following Gibbon patterns
 * Adds query building and filtering capabilities
 */
abstract class QueryableGateway extends Gateway
{
    protected static $tableName = '';
    protected static $primaryKey = '';
    protected static $searchableColumns = [];

    /**
     * Get table name
     * @return string
     */
    protected function getTableName(): string
    {
        return static::$tableName;
    }

    /**
     * Get primary key
     * @return string
     */
    protected function getPrimaryKey(): string
    {
        return static::$primaryKey;
    }

    /**
     * Get all records
     * @return array
     */
    public function selectAll(): array
    {
        $sql = "SELECT * FROM {$this->getTableName()} ORDER BY {$this->getPrimaryKey()}";
        return $this->select($sql);
    }

    /**
     * Get record by ID
     * @param int $id
     * @return array|false
     */
    public function getByID(int $id)
    {
        $sql = "SELECT * FROM {$this->getTableName()} WHERE {$this->getPrimaryKey()} = :id";
        return $this->selectOne($sql, ['id' => $id]);
    }

    /**
     * Count all records
     * @return int
     */
    public function getCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->getTableName()}";
        $result = $this->selectOne($sql);
        return (int) $result['count'];
    }

    /**
     * Search records by searchable columns
     * @param string $search
     * @return array
     */
    public function search(string $search): array
    {
        if (empty(static::$searchableColumns) || empty($search)) {
            return $this->selectAll();
        }

        $searchConditions = [];
        $params = [];

        foreach (static::$searchableColumns as $column) {
            $searchConditions[] = "{$column} LIKE :search";
        }

        $params['search'] = "%{$search}%";

        $sql = "SELECT * FROM {$this->getTableName()}
                WHERE " . implode(' OR ', $searchConditions) . "
                ORDER BY {$this->getPrimaryKey()}";

        return $this->select($sql, $params);
    }

    /**
     * Insert new record
     * @param array $data
     * @return int
     */
    public function insertRecord(array $data): int
    {
        return $this->insert($this->getTableName(), $data);
    }

    /**
     * Update record by ID
     * @param int $id
     * @param array $data
     * @return int
     */
    public function updateByID(int $id, array $data): int
    {
        $where = "{$this->getPrimaryKey()} = :id";
        return $this->update($this->getTableName(), $data, $where, ['id' => $id]);
    }

    /**
     * Delete record by ID
     * @param int $id
     * @return int
     */
    public function deleteByID(int $id): int
    {
        $where = "{$this->getPrimaryKey()} = :id";
        return $this->delete($this->getTableName(), $where, ['id' => $id]);
    }
}