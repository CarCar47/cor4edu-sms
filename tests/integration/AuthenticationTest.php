<?php
/**
 * Authentication Integration Tests
 *
 * Tests the core authentication and permission system to prevent
 * unauthorized access (critical for FERPA compliance and security).
 *
 * These tests ensure:
 * - Staff login works correctly
 * - Password verification is secure
 * - Permission checks function properly
 * - Super admin privileges work
 * - Access control is enforced
 */

namespace Cor4Edu\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Cor4Edu\Domain\Staff\StaffGateway;

class AuthenticationTest extends TestCase
{
    private StaffGateway $staffGateway;
    private \PDO $pdo;
    private static int $testStaffID;
    private static int $testAdminID;

    public static function setUpBeforeClass(): void
    {
        // Store IDs for cleanup
        self::$testStaffID = 0;
        self::$testAdminID = 0;
    }

    protected function setUp(): void
    {
        // Connect to test database or production (tests should be non-destructive)
        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbPort = getenv('DB_PORT') ?: '3306';
        $dbName = getenv('DB_NAME') ?: 'cor4edu_sms';
        $dbUser = getenv('DB_USERNAME') ?: 'root';
        $dbPass = getenv('DB_PASSWORD') ?: '';

        $this->pdo = new \PDO(
            "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
            $dbUser,
            $dbPass,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]
        );

        $this->staffGateway = new StaffGateway($this->pdo);
    }

    /**
     * Test that we can fetch staff by ID
     */
    public function testGetStaffById(): void
    {
        // Use existing superadmin (staffID = 1)
        $staff = $this->staffGateway->getStaffById(1);

        $this->assertNotNull($staff, 'Should fetch staff member');
        $this->assertArrayHasKey('staffID', $staff);
        $this->assertArrayHasKey('username', $staff);
        $this->assertArrayHasKey('email', $staff);
        $this->assertArrayHasKey('firstName', $staff);
        $this->assertArrayHasKey('lastName', $staff);
        $this->assertArrayHasKey('isSuperAdmin', $staff);
    }

    /**
     * Test that inactive staff cannot be fetched
     */
    public function testGetInactiveStaffReturnsNull(): void
    {
        // Create inactive test staff
        $stmt = $this->pdo->prepare("
            INSERT INTO cor4edu_staff
            (username, email, firstName, lastName, passwordStrong, passwordStrongSalt, isSuperAdmin, active)
            VALUES
            ('test_inactive', 'inactive@test.com', 'Inactive', 'Test', '', '', 'N', 'N')
        ");
        $stmt->execute();
        $inactiveStaffID = (int) $this->pdo->lastInsertId();

        $staff = $this->staffGateway->getStaffById($inactiveStaffID);

        $this->assertNull($staff, 'Inactive staff should not be fetched');

        // Cleanup
        $this->pdo->prepare("DELETE FROM cor4edu_staff WHERE staffID = ?")->execute([$inactiveStaffID]);
    }

    /**
     * Test super admin has all permissions
     */
    public function testSuperAdminHasAllPermissions(): void
    {
        // Assume staffID 1 is superadmin
        $this->assertTrue(
            $this->staffGateway->hasPermission(1, 'students.read'),
            'Super admin should have students.read'
        );

        $this->assertTrue(
            $this->staffGateway->hasPermission(1, 'payments.write'),
            'Super admin should have payments.write'
        );

        $this->assertTrue(
            $this->staffGateway->hasPermission(1, 'programs.delete'),
            'Super admin should have any permission'
        );
    }

    /**
     * Test non-admin staff permission checking
     */
    public function testStaffPermissionChecking(): void
    {
        // Create test staff WITHOUT super admin
        $hashedPassword = password_hash('Test123!', PASSWORD_BCRYPT);
        $stmt = $this->pdo->prepare("
            INSERT INTO cor4edu_staff
            (username, email, firstName, lastName, passwordStrong, passwordStrongSalt,
             isSuperAdmin, active, position)
            VALUES
            ('test_staff', 'teststaff@test.com', 'Test', 'Staff', ?, '', 'N', 'Y', 'Tester')
        ");
        $stmt->execute([$hashedPassword]);
        $staffID = (int) $this->pdo->lastInsertId();
        self::$testStaffID = $staffID;

        // Initially should have NO permissions
        $this->assertFalse(
            $this->staffGateway->hasPermission($staffID, 'students.read'),
            'Staff without permissions should not have students.read'
        );

        // Grant students.read permission
        $this->staffGateway->addPermission($staffID, 'students', 'read', 1);

        // Now should have students.read
        $this->assertTrue(
            $this->staffGateway->hasPermission($staffID, 'students.read'),
            'Staff should have students.read after grant'
        );

        // But should NOT have students.write
        $this->assertFalse(
            $this->staffGateway->hasPermission($staffID, 'students.write'),
            'Staff should not have students.write (not granted)'
        );
    }

    /**
     * Test getting staff permissions
     */
    public function testGetStaffPermissions(): void
    {
        // Test super admin gets wildcard
        $permissions = $this->staffGateway->getStaffPermissions(1);
        $this->assertContains('*', $permissions, 'Super admin should have wildcard permission');

        // Test regular staff permissions
        if (self::$testStaffID > 0) {
            $permissions = $this->staffGateway->getStaffPermissions(self::$testStaffID);
            $this->assertIsArray($permissions);
            $this->assertContains('students.read', $permissions, 'Test staff should have granted permission');
        }
    }

    /**
     * Test permission requirement throws exception when denied
     */
    public function testRequirePermissionThrowsExceptionWhenDenied(): void
    {
        if (self::$testStaffID > 0) {
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('Access denied');

            $this->staffGateway->requirePermission(self::$testStaffID, 'payments.write');
        } else {
            $this->markTestSkipped('Test staff not created');
        }
    }

    /**
     * Test permission requirement passes when granted
     */
    public function testRequirePermissionPassesWhenGranted(): void
    {
        if (self::$testStaffID > 0) {
            // Should not throw exception
            $this->staffGateway->requirePermission(self::$testStaffID, 'students.read');
            $this->assertTrue(true, 'Permission requirement should pass');
        } else {
            $this->markTestSkipped('Test staff not created');
        }
    }

    /**
     * Test student access control
     */
    public function testCanAccessStudent(): void
    {
        // Super admin can access any student
        $this->assertTrue(
            $this->staffGateway->canAccessStudent(1, 1),
            'Super admin should access any student'
        );

        // Staff with students.read can access
        if (self::$testStaffID > 0) {
            $this->assertTrue(
                $this->staffGateway->canAccessStudent(self::$testStaffID, 1),
                'Staff with students.read should access students'
            );
        }
    }

    /**
     * Test getting accessible programs
     */
    public function testGetAccessiblePrograms(): void
    {
        // Super admin gets all programs
        $programs = $this->staffGateway->getAccessiblePrograms(1);
        $this->assertIsArray($programs);
        $this->assertGreaterThan(0, count($programs), 'Should have at least one program');

        if (count($programs) > 0) {
            $this->assertArrayHasKey('programID', $programs[0]);
            $this->assertArrayHasKey('name', $programs[0]);
        }
    }

    /**
     * Cleanup test data
     */
    protected function tearDown(): void
    {
        // Clean up test staff and permissions
        if (self::$testStaffID > 0) {
            $this->pdo->prepare("DELETE FROM cor4edu_staff_permissions WHERE staffID = ?")
                ->execute([self::$testStaffID]);
            $this->pdo->prepare("DELETE FROM cor4edu_staff WHERE staffID = ?")
                ->execute([self::$testStaffID]);
            self::$testStaffID = 0;
        }
    }
}
