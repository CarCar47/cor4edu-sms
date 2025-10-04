<?php
/**
 * Query Builder Test
 *
 * Tests Aura SQL Query Builder integration following Gibbon patterns
 */

namespace Cor4Edu\Tests;

use PHPUnit\Framework\TestCase;
use Cor4Edu\Database\QueryBuilder;
use PDO;

class QueryBuilderTest extends TestCase
{
    private QueryBuilder $queryBuilder;
    private PDO $pdo;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create test table
        $this->pdo->exec('
            CREATE TABLE test_users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(100),
                email VARCHAR(100),
                status VARCHAR(20)
            )
        ');

        $this->queryBuilder = new QueryBuilder($this->pdo);
    }

    /**
     * Test SELECT query building
     */
    public function testSelectQueryBuilding(): void
    {
        // Insert test data
        $this->pdo->exec("
            INSERT INTO test_users (username, email, status)
            VALUES
                ('user1', 'user1@example.com', 'active'),
                ('user2', 'user2@example.com', 'active'),
                ('user3', 'user3@example.com', 'inactive')
        ");

        // Build SELECT query with Aura SQL Query
        $query = $this->queryBuilder->newSelect()
            ->cols(['*'])
            ->from('test_users')
            ->where('status = :status')
            ->bindValue('status', 'active');

        $results = $this->queryBuilder->runSelect($query);

        $this->assertCount(2, $results, "Should find 2 active users");
        $this->assertEquals('user1', $results[0]['username']);
        $this->assertEquals('user2', $results[1]['username']);
    }

    /**
     * Test INSERT query building
     */
    public function testInsertQueryBuilding(): void
    {
        $query = $this->queryBuilder->newInsert()
            ->into('test_users')
            ->cols([
                'username' => 'newuser',
                'email' => 'newuser@example.com',
                'status' => 'active'
            ]);

        $insertId = $this->queryBuilder->runInsert($query);

        $this->assertGreaterThan(0, $insertId, "Should return insert ID");

        // Verify inserted data
        $selectQuery = $this->queryBuilder->newSelect()
            ->cols(['*'])
            ->from('test_users')
            ->where('id = :id')
            ->bindValue('id', $insertId);

        $result = $this->queryBuilder->runSelectOne($selectQuery);

        $this->assertEquals('newuser', $result['username']);
        $this->assertEquals('newuser@example.com', $result['email']);
    }

    /**
     * Test UPDATE query building
     */
    public function testUpdateQueryBuilding(): void
    {
        // Insert test data
        $this->pdo->exec("
            INSERT INTO test_users (username, email, status)
            VALUES ('updateuser', 'update@example.com', 'active')
        ");

        $userId = (int) $this->pdo->lastInsertId();

        // Build UPDATE query
        $query = $this->queryBuilder->newUpdate()
            ->table('test_users')
            ->cols([
                'status' => 'inactive',
                'email' => 'updated@example.com'
            ])
            ->where('id = :id')
            ->bindValue('id', $userId);

        $affectedRows = $this->queryBuilder->runUpdate($query);

        $this->assertEquals(1, $affectedRows, "Should update 1 row");

        // Verify updated data
        $selectQuery = $this->queryBuilder->newSelect()
            ->cols(['*'])
            ->from('test_users')
            ->where('id = :id')
            ->bindValue('id', $userId);

        $result = $this->queryBuilder->runSelectOne($selectQuery);

        $this->assertEquals('inactive', $result['status']);
        $this->assertEquals('updated@example.com', $result['email']);
    }

    /**
     * Test DELETE query building
     */
    public function testDeleteQueryBuilding(): void
    {
        // Insert test data
        $this->pdo->exec("
            INSERT INTO test_users (username, email, status)
            VALUES ('deleteuser', 'delete@example.com', 'active')
        ");

        $userId = (int) $this->pdo->lastInsertId();

        // Build DELETE query
        $query = $this->queryBuilder->newDelete()
            ->from('test_users')
            ->where('id = :id')
            ->bindValue('id', $userId);

        $affectedRows = $this->queryBuilder->runDelete($query);

        $this->assertEquals(1, $affectedRows, "Should delete 1 row");

        // Verify deletion
        $selectQuery = $this->queryBuilder->newSelect()
            ->cols(['COUNT(*) as count'])
            ->from('test_users')
            ->where('id = :id')
            ->bindValue('id', $userId);

        $result = $this->queryBuilder->runSelectOne($selectQuery);

        $this->assertEquals(0, $result['count'], "User should be deleted");
    }

    /**
     * Test complex query with JOINs
     */
    public function testComplexQueryWithJoins(): void
    {
        // Create related table
        $this->pdo->exec('
            CREATE TABLE test_profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                bio TEXT
            )
        ');

        // Insert test data
        $this->pdo->exec("
            INSERT INTO test_users (username, email, status)
            VALUES ('joinuser', 'join@example.com', 'active')
        ");
        $userId = (int) $this->pdo->lastInsertId();

        $this->pdo->exec("
            INSERT INTO test_profiles (user_id, bio)
            VALUES ($userId, 'Test bio')
        ");

        // Build complex SELECT with JOIN
        $query = $this->queryBuilder->newSelect()
            ->cols([
                'test_users.username',
                'test_users.email',
                'test_profiles.bio'
            ])
            ->from('test_users')
            ->join('INNER', 'test_profiles', 'test_profiles.user_id = test_users.id')
            ->where('test_users.id = :id')
            ->bindValue('id', $userId);

        $result = $this->queryBuilder->runSelectOne($query);

        $this->assertEquals('joinuser', $result['username']);
        $this->assertEquals('Test bio', $result['bio']);
    }

    /**
     * Test SQL injection prevention
     */
    public function testSqlInjectionPrevention(): void
    {
        // Insert test data
        $this->pdo->exec("
            INSERT INTO test_users (username, email, status)
            VALUES ('victim', 'victim@example.com', 'active')
        ");

        // Attempt SQL injection (should fail safely)
        $maliciousInput = "active' OR '1'='1";

        $query = $this->queryBuilder->newSelect()
            ->cols(['*'])
            ->from('test_users')
            ->where('status = :status')
            ->bindValue('status', $maliciousInput);

        $results = $this->queryBuilder->runSelect($query);

        // Should find 0 results because bind value escapes the injection
        $this->assertCount(0, $results, "SQL injection should be prevented");
    }

    /**
     * Test backward compatibility with raw SQL
     */
    public function testBackwardCompatibilityRawSql(): void
    {
        $this->pdo->exec("
            INSERT INTO test_users (username, email, status)
            VALUES ('rawuser', 'raw@example.com', 'active')
        ");

        // Test raw SQL still works (backward compatibility)
        $results = $this->queryBuilder->select(
            "SELECT * FROM test_users WHERE status = :status",
            ['status' => 'active']
        );

        $this->assertGreaterThan(0, count($results));
        $this->assertEquals('rawuser', $results[0]['username']);
    }
}
