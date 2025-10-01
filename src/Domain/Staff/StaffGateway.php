<?php
/**
 * COR4EDU SMS - Staff Gateway
 * Following Gibbon patterns and COR4EDU Single permission architecture
 */

namespace Cor4Edu\Domain\Staff;

class StaffGateway
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Role-based permission mapping following COR4EDU Single patterns
     */
    private $rolePermissions = [
        'admissions' => [
            'students.read', 'students.create', 'students.write',
            'documents.read', 'documents.upload', 'reports.admissions'
        ],
        'bursar' => [
            'students.read', 'payments.read', 'payments.create', 'payments.write',
            'reports.financial', 'documents.read'
        ],
        'registrar' => [
            'students.read', 'students.write', 'programs.read',
            'reports.academic', 'documents.read', 'documents.upload'
        ],
        'career_services' => [
            'students.read', 'students.write', 'job_applications.read',
            'job_applications.create', 'job_applications.write',
            'reports.career_services', 'documents.read'
        ],
        'faculty' => [
            'students.read', 'faculty_notes.read', 'faculty_notes.create',
            'faculty_notes.write', 'academic_support.read',
            'academic_support.create', 'academic_support.write', 'documents.read'
        ]
    ];

    /**
     * Check if staff member has specific permission
     * Following actual database structure with cor4edu_staff_permissions table
     *
     * @param int $staffID Staff member ID
     * @param string $permission Permission to check (e.g., 'payments.create')
     * @param string|null $resourceId Optional resource ID for resource-specific permissions
     * @return bool
     */
    public function hasPermission(int $staffID, string $permission, ?string $resourceId = null): bool
    {
        try {
            $staff = $this->getStaffById($staffID);

            if (!$staff) {
                return false;
            }

            // 1. Super Admin Check - bypasses all other checks
            if ($staff['isSuperAdmin'] === 'Y') {
                return true;
            }

            // 2. Parse permission into module.action format
            $permissionParts = explode('.', $permission);
            if (count($permissionParts) !== 2) {
                return false;
            }

            $module = $permissionParts[0];
            $action = $permissionParts[1];

            // 3. Check granular permissions in cor4edu_staff_permissions table
            $sql = "SELECT allowed FROM cor4edu_staff_permissions
                    WHERE staffID = :staffID AND module = :module AND action = :action";

            $stmt = $this->pdo->prepare($sql);
            $stmt->bindParam(':staffID', $staffID, \PDO::PARAM_INT);
            $stmt->bindParam(':module', $module);
            $stmt->bindParam(':action', $action);
            $stmt->execute();

            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($result && $result['allowed'] === 'Y') {
                return true;
            }

            return false;

        } catch (\Exception $e) {
            // Log error and deny access for security
            error_log("Permission check failed for staff $staffID: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Require permission - throws exception if denied
     */
    public function requirePermission(int $staffID, string $permission, ?string $resourceId = null): void
    {
        if (!$this->hasPermission($staffID, $permission, $resourceId)) {
            throw new \Exception("Access denied: Missing permission '$permission'");
        }
    }

    /**
     * Get staff member by ID
     */
    public function getStaffById(int $staffID): ?array
    {
        $sql = "SELECT staffID, username, email, firstName, lastName,
                       isSuperAdmin, canCreateAdmins, active, position
                FROM cor4edu_staff
                WHERE staffID = :staffID AND active = 'Y'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':staffID', $staffID, \PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Add default values for columns that don't exist yet
        if ($result) {
            $result['roleTypeID'] = 1; // Default role
            $result['isAdminRole'] = $result['isSuperAdmin']; // SuperAdmin counts as admin
        }

        return $result ?: null;
    }

    /**
     * Get staff effective permissions
     */
    public function getStaffPermissions(int $staffID): array
    {
        $staff = $this->getStaffById($staffID);

        if (!$staff) {
            return [];
        }

        // Super admin gets all permissions
        if ($staff['isSuperAdmin'] === 'Y') {
            return ['*']; // Wildcard for all permissions
        }

        // Get permissions from cor4edu_staff_permissions table
        $sql = "SELECT CONCAT(module, '.', action) as permission
                FROM cor4edu_staff_permissions
                WHERE staffID = :staffID AND allowed = 'Y'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':staffID', $staffID, \PDO::PARAM_INT);
        $stmt->execute();

        $permissions = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $permissions[] = $row['permission'];
        }

        return $permissions;
    }

    /**
     * Get staff permissions in detailed format for reports module
     */
    public function getStaffPermissionsDetailed(int $staffID): array
    {
        $staff = $this->getStaffById($staffID);

        if (!$staff) {
            return [];
        }

        // Super admin gets all permissions - return a comprehensive set for reports
        if ($staff['isSuperAdmin'] === 'Y') {
            return [
                ['module' => 'reports', 'action' => 'view_reports_tab', 'allowed' => 'Y'],
                ['module' => 'reports', 'action' => 'view_financial_details', 'allowed' => 'Y'],
                ['module' => 'reports', 'action' => 'generate_overview_reports', 'allowed' => 'Y'],
                ['module' => 'reports', 'action' => 'generate_admissions_reports', 'allowed' => 'Y'],
                ['module' => 'reports', 'action' => 'generate_financial_reports', 'allowed' => 'Y'],
                ['module' => 'reports', 'action' => 'generate_career_reports', 'allowed' => 'Y'],
                ['module' => 'reports', 'action' => 'generate_academic_reports', 'allowed' => 'Y'],
                ['module' => 'reports', 'action' => 'export_reports_csv', 'allowed' => 'Y'],
                ['module' => 'reports', 'action' => 'export_reports_excel', 'allowed' => 'Y']
            ];
        }

        // Get permissions from cor4edu_staff_permissions table
        $sql = "SELECT module, action, allowed
                FROM cor4edu_staff_permissions
                WHERE staffID = :staffID";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':staffID', $staffID, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if staff can access specific student
     */
    public function canAccessStudent(int $staffID, int $studentID): bool
    {
        // Basic student access check - can be expanded with program-specific logic
        return $this->hasPermission($staffID, 'students.read');
    }

    /**
     * Get accessible programs for staff member
     */
    public function getAccessiblePrograms(int $staffID): array
    {
        $staff = $this->getStaffById($staffID);

        if (!$staff) {
            return [];
        }

        // Super admin sees all programs
        if ($staff['isSuperAdmin'] === 'Y') {
            $sql = "SELECT programID, name FROM cor4edu_programs WHERE active = 'Y'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        // Faculty might be limited to specific programs
        // For now, return all active programs for other roles
        if ($this->hasPermission($staffID, 'programs.read')) {
            $sql = "SELECT programID, name FROM cor4edu_programs WHERE active = 'Y'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    /**
     * Check legacy permission fields for backward compatibility
     */
    private function checkLegacyPermissions(array $staff, string $permission): bool
    {
        // Map modern permissions to legacy fields if they exist
        $legacyMappings = [
            'payments.read' => 'canViewFinances',
            'payments.create' => 'canProcessPayments',
            'payments.write' => 'canProcessPayments',
            'students.read' => 'canViewStudents',
            'students.write' => 'canEditStudents',
            'students.create' => 'canAddStudents',
            'programs.read' => 'canViewPrograms',
            'programs.write' => 'canEditPrograms'
        ];

        if (isset($legacyMappings[$permission])) {
            $legacyField = $legacyMappings[$permission];
            return isset($staff[$legacyField]) && $staff[$legacyField] === 'Y';
        }

        return false;
    }

    /**
     * Authenticate staff member
     */
    public function authenticateStaff(string $emailOrUsername, string $password): ?array
    {
        $sql = "SELECT staffID, username, email, firstName, lastName, password,
                       isSuperAdmin, roles, granularPermissions, active, position
                FROM cor4edu_staff
                WHERE (email = :credential OR username = :credential)
                AND active = 'Y'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':credential', $emailOrUsername);
        $stmt->execute();

        $staff = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($staff && password_verify($password, $staff['password'])) {
            // Remove password from returned data
            unset($staff['password']);
            return $staff;
        }

        return null;
    }

    /**
     * Update staff roles
     */
    public function updateStaffRoles(int $staffID, array $roles, int $updatedBy): bool
    {
        $sql = "UPDATE cor4edu_staff
                SET roles = :roles, lastModifiedBy = :updatedBy, updated_at = NOW()
                WHERE staffID = :staffID";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':roles', json_encode($roles));
        $stmt->bindParam(':updatedBy', $updatedBy, \PDO::PARAM_INT);
        $stmt->bindParam(':staffID', $staffID, \PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Add permission for staff member
     */
    public function addPermission(int $staffID, string $module, string $action, int $createdBy): bool
    {
        $sql = "INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy)
                VALUES (:staffID, :module, :action, 'Y', :createdBy)
                ON DUPLICATE KEY UPDATE allowed = 'Y'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindParam(':staffID', $staffID, \PDO::PARAM_INT);
        $stmt->bindParam(':module', $module);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':createdBy', $createdBy, \PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Get total staff count for dashboard
     */
    public function getTotalStaffCount(): int
    {
        $sql = "SELECT COUNT(*) as total FROM cor4edu_staff WHERE active = 'Y'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return (int)$result['total'];
    }

    /**
     * Debug method to check staff info and permissions
     */
    public function debugStaffPermissions(int $staffID): array
    {
        $staff = $this->getStaffById($staffID);
        $permissions = $this->getStaffPermissions($staffID);

        return [
            'staff' => $staff,
            'permissions' => $permissions,
            'isSuperAdmin' => $staff ? $staff['isSuperAdmin'] : 'N/A'
        ];
    }
}