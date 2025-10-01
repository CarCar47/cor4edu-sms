<?php
/**
 * COR4EDU SMS Bootstrap
 * Simplified Gibbon-style service container
 */

// Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Enable error display for debugging (before any errors can occur)
if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

// Load environment variables (optional - Cloud Run uses $_ENV directly)
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

// Database configuration
require_once __DIR__ . '/config/database.php';

// Create simple container similar to Gibbon
$container = [];

// Database Connection
use Cor4Edu\Config\Database;
try {
    $pdo = Database::getInstance();
    $container['db'] = $pdo;
} catch (Exception $e) {
    // Always log the error
    error_log('Database connection failed: ' . $e->getMessage());

    // Check PHP PDO drivers
    $pdoDrivers = 'PDO not available';
    if (class_exists('PDO')) {
        $pdoDrivers = implode(', ', PDO::getAvailableDrivers());
    }

    // Check what's in /cloudsql directory
    $cloudsqlContents = 'not accessible';
    if (is_dir('/cloudsql')) {
        $files = scandir('/cloudsql');
        $cloudsqlContents = implode(', ', $files);
    }

    // Get actual database config being used
    $dbHost = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'not set';
    $dbPort = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: 'not set';
    $dbSocket = $_ENV['DB_SOCKET'] ?? getenv('DB_SOCKET') ?: 'not set';

    // Always display full error in Cloud Run for debugging
    die('Database connection failed: ' . $e->getMessage() . "\n\nEnvironment:\nDB_HOST: " . $dbHost . "\nDB_PORT: " . $dbPort . "\nDB_SOCKET: " . $dbSocket . "\nDB_NAME: " . ($_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'not set') . "\nDB_USERNAME: " . (isset($_ENV['DB_USERNAME']) || getenv('DB_USERNAME') ? 'SET' : 'not set') . "\nDB_PASSWORD: " . (isset($_ENV['DB_PASSWORD']) || getenv('DB_PASSWORD') ? 'SET' : 'not set') . "\n\nPDO Drivers: " . $pdoDrivers . "\n/cloudsql directory: " . $cloudsqlContents);
}

// Create gateway factory function that matches Gibbon's container->get() pattern
function getGateway($className) {
    global $container;
    $pdo = $container['db'];

    switch ($className) {
        case 'Cor4Edu\Domain\Student\StudentGateway':
            return new Cor4Edu\Domain\Student\StudentGateway($pdo);
        case 'Cor4Edu\Domain\Program\ProgramGateway':
            return new Cor4Edu\Domain\Program\ProgramGateway($pdo);
        case 'Cor4Edu\Domain\Document\DocumentGateway':
            return new Cor4Edu\Domain\Document\DocumentGateway($pdo);
        case 'Cor4Edu\Domain\Document\DocumentRequirementGateway':
            return new Cor4Edu\Domain\Document\DocumentRequirementGateway($pdo);
        case 'Cor4Edu\Domain\Payment\PaymentGateway':
            return new Cor4Edu\Domain\Payment\PaymentGateway($pdo);
        case 'Cor4Edu\Domain\Financial\FinancialGateway':
            return new Cor4Edu\Domain\Financial\FinancialGateway($pdo);
        case 'Cor4Edu\Domain\Staff\StaffGateway':
            return new Cor4Edu\Domain\Staff\StaffGateway($pdo);
        case 'Cor4Edu\Domain\CareerPlacement\CareerPlacementGateway':
            return new Cor4Edu\Domain\CareerPlacement\CareerPlacementGateway($pdo);
        case 'Cor4Edu\Domain\Staff\StaffProfileGateway':
            return new Cor4Edu\Domain\Staff\StaffProfileGateway($pdo);

        // Reports Module Gateways
        case 'Cor4Edu\Reports\Domain\ReportsGateway':
            return new Cor4Edu\Reports\Domain\ReportsGateway($pdo);
        case 'Cor4Edu\Reports\Domain\FinancialReportsGateway':
            return new Cor4Edu\Reports\Domain\FinancialReportsGateway($pdo);
        case 'Cor4Edu\Reports\Domain\CareerReportsGateway':
            return new Cor4Edu\Reports\Domain\CareerReportsGateway($pdo);
        case 'Cor4Edu\Reports\Domain\AcademicReportsGateway':
            return new Cor4Edu\Reports\Domain\AcademicReportsGateway($pdo);

        default:
            throw new Exception("Gateway not found: $className");
    }
}

/**
 * Helper function to get user permissions for navigation consistency
 * This ensures Reports tab appears everywhere for users with proper permissions
 */
function getUserPermissionsForNavigation($staffID) {
    $staffGateway = getGateway('Cor4Edu\Domain\Staff\StaffGateway');
    $staff = $staffGateway->getStaffById($staffID);

    // Level 1: SuperAdmin (Creator) - unlimited access
    if ($staff && $staff['isSuperAdmin'] === 'Y') {
        return getAllSystemPermissions();
    }

    // Level 2: Build permissions from role defaults + individual overrides
    $permissions = [];

    // Add admin-specific permissions for navigation
    if ($staff && ($staff['isAdminRole'] === 'Y' || $staff['roleTypeID'] == 6)) {
        $permissions['manage_permissions'] = true; // Can see Permissions tab
        $permissions['manage_staff_permissions'] = true; // Can modify staff permissions
        $permissions['view_staff_list'] = true; // Can see Staff tab
    }

    // Get role default permissions and individual overrides
    $allPermissions = resolveUserPermissions($staffID, $staff);

    return array_merge($permissions, $allPermissions);
}

/**
 * Get all available system permissions (for SuperAdmin)
 */
function getAllSystemPermissions() {
    global $container;
    $pdo = $container['db'];

    try {
        $stmt = $pdo->prepare("SELECT action FROM cor4edu_system_permissions WHERE active = 'Y'");
        $stmt->execute();
        $permissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['action']] = true;
        }
        // Add navigation permissions and essential permissions for SuperAdmin
        $permissions['manage_permissions'] = true;
        $permissions['manage_staff_permissions'] = true;
        $permissions['view_staff_list'] = true;
        $permissions['view_financial_details'] = true;
        return $permissions;
    } catch (PDOException $e) {
        // Log warning about missing permission tables
        error_log("⚠️ Warning: cor4edu_system_permissions table not found. This means the permission system migration has not been run yet.");
        error_log("Run the web migration at: /run_migration.php (SuperAdmin access required)");
        error_log("Error details: " . $e->getMessage());

        // Fallback to existing permissions if new tables don't exist yet
        return [
            'view_reports_tab' => true,
            'view_financial_details' => true,
            'generate_overview_reports' => true,
            'generate_admissions_reports' => true,
            'generate_financial_reports' => true,
            'generate_career_reports' => true,
            'generate_academic_reports' => true,
            'export_reports_csv' => true,
            'export_reports_excel' => true,
            'manage_permissions' => true,
            'manage_staff_permissions' => true,
            'view_staff_list' => true
        ];
    }
}

/**
 * Resolve user permissions using role defaults + individual overrides
 */
function resolveUserPermissions($staffID, $staff) {
    global $container;
    $pdo = $container['db'];
    $permissions = [];

    try {
        // Get role default permissions
        $stmt = $pdo->prepare("
            SELECT module, action, allowed
            FROM cor4edu_role_permission_defaults
            WHERE roleTypeID = ? AND allowed = 'Y'
        ");
        $stmt->execute([$staff['roleTypeID']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['action']] = true;
        }

        // Get individual permission overrides (these supersede role defaults)
        $stmt = $pdo->prepare("
            SELECT module, action, allowed
            FROM cor4edu_staff_permissions
            WHERE staffID = ?
        ");
        $stmt->execute([$staffID]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['action']] = ($row['allowed'] === 'Y');
        }

    } catch (PDOException $e) {
        // Log detailed error about missing permission tables
        error_log("⚠️ Warning: Permission tables not found in database. Error code: " . $e->getCode());
        error_log("This is expected if the permission system migration hasn't been run yet.");
        error_log("To fix: Run /run_migration.php as SuperAdmin or execute database_complete_schema.sql");

        // Fallback to existing logic if new tables don't exist yet
        try {
            $staffGateway = getGateway('Cor4Edu\Domain\Staff\StaffGateway');
            $userPermissions = $staffGateway->getStaffPermissionsDetailed($staffID);

            foreach ($userPermissions as $permission) {
                if (is_array($permission) &&
                    isset($permission['module']) &&
                    isset($permission['allowed']) &&
                    isset($permission['action']) &&
                    $permission['allowed'] === 'Y') {
                    $permissions[$permission['action']] = true;
                }
            }
        } catch (Exception $fallbackError) {
            error_log("❌ Fatal: Could not resolve permissions using fallback method: " . $fallbackError->getMessage());
        }
    }

    return $permissions;
}

/**
 * Check if user has specific permission (main permission checking function)
 */
function hasPermission($staffID, $module, $action) {
    global $container;
    $pdo = $container['db'];

    // Get staff info
    $staffGateway = getGateway('Cor4Edu\Domain\Staff\StaffGateway');
    $staff = $staffGateway->getStaffById($staffID);

    // Level 1: SuperAdmin bypass (unlimited access)
    if ($staff && $staff['isSuperAdmin'] === 'Y') {
        return true;
    }

    // Level 2: Check admin-only permissions
    try {
        $stmt = $pdo->prepare("
            SELECT requiresAdminRole
            FROM cor4edu_system_permissions
            WHERE module = ? AND action = ? AND active = 'Y'
        ");
        $stmt->execute([$module, $action]);
        $sysPermission = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sysPermission && $sysPermission['requiresAdminRole'] === 'Y') {
            // This permission requires admin role
            if (!$staff || ($staff['isAdminRole'] !== 'Y' && $staff['roleTypeID'] != 6)) {
                return false;
            }

            // Special restrictions for admin management (SuperAdmin only)
            if (in_array($action, ['create_admin_accounts', 'manage_admin_permissions', 'manage_role_defaults'])) {
                return ($staff['isSuperAdmin'] === 'Y');
            }
        }
    } catch (PDOException $e) {
        // Log warning about missing system permissions table
        if (strpos($e->getMessage(), 'cor4edu_system_permissions') !== false) {
            error_log("⚠️ hasPermission(): cor4edu_system_permissions table not found for {$module}.{$action}");
        }
        // Continue with permission check if system permissions table doesn't exist
    }

    // Level 3: Check individual override first
    try {
        $stmt = $pdo->prepare("
            SELECT allowed
            FROM cor4edu_staff_permissions
            WHERE staffID = ? AND module = ? AND action = ?
        ");
        $stmt->execute([$staffID, $module, $action]);
        $individual = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($individual) {
            return ($individual['allowed'] === 'Y');
        }
    } catch (PDOException $e) {
        // Log warning about missing staff permissions table
        if (strpos($e->getMessage(), 'cor4edu_staff_permissions') !== false) {
            error_log("⚠️ hasPermission(): cor4edu_staff_permissions table not found for staff {$staffID}");
        }
        // Continue to role default check
    }

    // Level 4: Check role default
    try {
        $stmt = $pdo->prepare("
            SELECT allowed
            FROM cor4edu_role_permission_defaults
            WHERE roleTypeID = ? AND module = ? AND action = ?
        ");
        $stmt->execute([$staff['roleTypeID'], $module, $action]);
        $roleDefault = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($roleDefault) {
            return ($roleDefault['allowed'] === 'Y');
        }
    } catch (PDOException $e) {
        // Log warning about missing role permission defaults table
        if (strpos($e->getMessage(), 'cor4edu_role_permission_defaults') !== false) {
            error_log("⚠️ hasPermission(): cor4edu_role_permission_defaults table not found for staff {$staffID}");
            error_log("To fix: Run /run_migration.php or execute database_complete_schema.sql");
        }
        // Fallback to false if tables don't exist
    }

    // Permission denied - no match found in any permission table
    return false;
}

/**
 * Check if user has edit permission for a specific module action
 * Wrapper around hasPermission for clearer semantic meaning
 *
 * @param int $staffID Staff ID
 * @param string $module Module name (e.g., 'students')
 * @param string $action Action name (e.g., 'edit_information_tab')
 * @return bool True if user has edit permission
 */
function hasEditPermission($staffID, $module, $action) {
    return hasPermission($staffID, $module, $action);
}

/**
 * Check if user can edit a specific student tab
 * Converts tab names to edit permission actions
 *
 * @param int $staffID Staff ID
 * @param string $tabName Tab name (e.g., 'information', 'academics', 'graduation')
 * @return bool True if user can edit the tab
 */
function canUserEditTab($staffID, $tabName) {
    $editPermissionMap = [
        'information' => 'edit_information_tab',
        'admissions' => 'edit_admissions_tab',
        'bursar' => 'edit_bursar_tab',
        'registrar' => 'edit_registrar_tab',
        'academics' => 'edit_academics_tab',
        'career' => 'edit_career_tab',
        'graduation' => 'edit_graduation_tab'
    ];

    $editAction = $editPermissionMap[$tabName] ?? null;
    if (!$editAction) {
        return false;
    }

    return hasEditPermission($staffID, 'students', $editAction);
}

/**
 * Get user's edit permissions for all student tabs
 * Returns array of tab names user can edit
 *
 * @param int $staffID Staff ID
 * @return array Array of tab names user can edit
 */
function getUserEditableTabsForStudents($staffID) {
    $tabs = ['information', 'admissions', 'bursar', 'registrar', 'academics', 'career', 'graduation'];
    $editableTabs = [];

    foreach ($tabs as $tab) {
        if (canUserEditTab($staffID, $tab)) {
            $editableTabs[] = $tab;
        }
    }

    return $editableTabs;
}

return $container;