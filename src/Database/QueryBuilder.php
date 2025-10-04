<?php

namespace Cor4Edu\Database;

use PDO;
use Aura\SqlQuery\QueryFactory;
use Aura\SqlQuery\Common\SelectInterface;
use Aura\SqlQuery\Common\InsertInterface;
use Aura\SqlQuery\Common\UpdateInterface;
use Aura\SqlQuery\Common\DeleteInterface;

/**
 * Query Builder Service following Gibbon patterns
 *
 * Wraps Aura SQL Query for type-safe, composable database queries
 * following PSR standards and preventing SQL injection.
 *
 * @version v1.0.0
 * @since v1.0.0
 */
class QueryBuilder
{
    /**
     * PDO database connection
     */
    protected PDO $pdo;

    /**
     * Aura SQL Query Factory
     */
    protected QueryFactory $queryFactory;

    /**
     * Constructor
     *
     * @param PDO $pdo Database connection
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->queryFactory = new QueryFactory('mysql');
    }

    /**
     * Create a new SELECT query
     *
     * @return SelectInterface
     */
    public function newSelect(): SelectInterface
    {
        return $this->queryFactory->newSelect();
    }

    /**
     * Create a new INSERT query
     *
     * @return InsertInterface
     */
    public function newInsert(): InsertInterface
    {
        return $this->queryFactory->newInsert();
    }

    /**
     * Create a new UPDATE query
     *
     * @return UpdateInterface
     */
    public function newUpdate(): UpdateInterface
    {
        return $this->queryFactory->newUpdate();
    }

    /**
     * Create a new DELETE query
     *
     * @return DeleteInterface
     */
    public function newDelete(): DeleteInterface
    {
        return $this->queryFactory->newDelete();
    }

    /**
     * Execute a SELECT query and return all results
     *
     * @param SelectInterface $query
     * @return array
     */
    public function runSelect(SelectInterface $query): array
    {
        $stmt = $this->pdo->prepare($query->getStatement());
        $stmt->execute($query->getBindValues());
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute a SELECT query and return first result
     *
     * @param SelectInterface $query
     * @return array|false
     */
    public function runSelectOne(SelectInterface $query): array|false
    {
        $stmt = $this->pdo->prepare($query->getStatement());
        $stmt->execute($query->getBindValues());
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Execute an INSERT query and return last insert ID
     *
     * @param InsertInterface $query
     * @return int
     */
    public function runInsert(InsertInterface $query): int
    {
        $stmt = $this->pdo->prepare($query->getStatement());
        $stmt->execute($query->getBindValues());
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Execute an UPDATE query and return affected rows
     *
     * @param UpdateInterface $query
     * @return int
     */
    public function runUpdate(UpdateInterface $query): int
    {
        $stmt = $this->pdo->prepare($query->getStatement());
        $stmt->execute($query->getBindValues());
        return $stmt->rowCount();
    }

    /**
     * Execute a DELETE query and return affected rows
     *
     * @param DeleteInterface $query
     * @return int
     */
    public function runDelete(DeleteInterface $query): int
    {
        $stmt = $this->pdo->prepare($query->getStatement());
        $stmt->execute($query->getBindValues());
        return $stmt->rowCount();
    }

    /**
     * Execute a raw SELECT query (backward compatibility)
     *
     * NOTE: Prefer using newSelect() for new code
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function select(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Execute a raw SELECT query returning single result (backward compatibility)
     *
     * NOTE: Prefer using newSelect() for new code
     *
     * @param string $sql
     * @param array $params
     * @return array|false
     */
    public function selectOne(string $sql, array $params = []): array|false
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get the PDO connection (for transactions, etc.)
     *
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /**
     * Get the Aura QueryFactory (advanced usage)
     *
     * @return QueryFactory
     */
    public function getQueryFactory(): QueryFactory
    {
        return $this->queryFactory;
    }
}
