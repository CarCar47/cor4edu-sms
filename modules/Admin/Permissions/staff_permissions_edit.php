<?php
require_once __DIR__ . '/../../../bootstrap.php';

// Basic session check for direct access protection
// (Main security is handled by index.php routing with SuperAdmin bypass)
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

$currentStaffID = $_SESSION['cor4edu']['staffID'];
$username = $_SESSION['cor4edu']['username'];

$targetStaffID = $_GET['staffID'] ?? null;
if (!$targetStaffID || !is_numeric($targetStaffID)) {
    header('Location: index.php?q=/modules/Admin/Permissions/permissions_manage.php');
    exit;
}

$staffGateway = getGateway('Cor4Edu\Domain\Staff\StaffGateway');
$currentStaff = $staffGateway->getStaffById($currentStaffID);
$targetStaff = $staffGateway->getStaffById($targetStaffID);

if (!$targetStaff) {
    header('Location: index.php?q=/modules/Admin/Permissions/permissions_manage.php');
    exit;
}

$isSuper = ($currentStaff && $currentStaff['isSuperAdmin'] === 'Y');

// Only allow SuperAdmin to edit other SuperAdmins
// Regular admins cannot edit SuperAdmins
if (!$isSuper && isset($targetStaff['isSuperAdmin']) && $targetStaff['isSuperAdmin'] === 'Y') {
    header('Location: index.php?q=/access_denied');
    exit;
}

$pdo = $container['db'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM cor4edu_staff_permissions WHERE staffID = ?");
        $stmt->execute([$targetStaffID]);

        if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
            $insertStmt = $pdo->prepare("
                INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            foreach ($_POST['permissions'] as $permissionKey => $value) {
                list($module, $action) = explode(':', $permissionKey);
                $allowed = ($value === 'Y') ? 'Y' : 'N';
                $insertStmt->execute([$targetStaffID, $module, $action, $allowed, $currentStaffID]);
            }
        }

        $pdo->commit();
        $_SESSION['cor4edu']['flash_success'] = 'Permissions updated successfully for ' . $targetStaff['firstName'] . ' ' . $targetStaff['lastName'];

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['cor4edu']['flash_errors'] = ['Error updating permissions: ' . $e->getMessage()];
    }

    header('Location: index.php?q=/modules/Admin/Permissions/staff_permissions_edit.php&staffID=' . $targetStaffID);
    exit;
}

try {
    $systemPermissions = [];
    $permQuery = "SELECT * FROM cor4edu_system_permissions WHERE active = 'Y'";

    if (!$isSuper) {
        $permQuery .= " AND (requiresAdminRole = 'N' OR module NOT IN ('staff', 'permissions'))";
    }

    $permQuery .= " ORDER BY category, displayOrder";
    $systemPermissions = $pdo->query($permQuery)->fetchAll(PDO::FETCH_ASSOC);

    $currentPermissions = [];
    $currentPermStmt = $pdo->prepare("
        SELECT module, action, allowed
        FROM cor4edu_staff_permissions
        WHERE staffID = ?
    ");
    $currentPermStmt->execute([$targetStaffID]);
    while ($row = $currentPermStmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $row['module'] . ':' . $row['action'];
        $currentPermissions[$key] = $row['allowed'];
    }

    $roleDefaults = [];
    // Since staff table doesn't have roleTypeID, use default role (6 = general staff)
    // or skip role defaults if roleTypeID doesn't exist
    $defaultRoleTypeID = isset($targetStaff['roleTypeID']) ? $targetStaff['roleTypeID'] : 6;

    try {
        $roleDefaultStmt = $pdo->prepare("
            SELECT module, action, allowed
            FROM cor4edu_role_permission_defaults
            WHERE roleTypeID = ?
        ");
        $roleDefaultStmt->execute([$defaultRoleTypeID]);
        while ($row = $roleDefaultStmt->fetch(PDO::FETCH_ASSOC)) {
            $key = $row['module'] . ':' . $row['action'];
            $roleDefaults[$key] = $row['allowed'];
        }
    } catch (PDOException $e) {
        // If role defaults table doesn't exist, just continue with empty defaults
        $roleDefaults = [];
    }

} catch (PDOException $e) {
    $_SESSION['cor4edu']['flash_errors'] = ['Database error: ' . $e->getMessage()];
    $systemPermissions = [];
    $currentPermissions = [];
    $roleDefaults = [];
}

$sessionData = [
    'staffID' => $currentStaffID,
    'username' => $username,
    'flash_success' => $_SESSION['cor4edu']['flash_success'] ?? null,
    'flash_errors' => $_SESSION['cor4edu']['flash_errors'] ?? [],
    'is_super_admin' => $isSuper
];

unset($_SESSION['cor4edu']['flash_success']);
unset($_SESSION['cor4edu']['flash_errors']);

$permissions = getUserPermissionsForNavigation($currentStaffID);

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../../resources/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('staff_permissions_edit.twig.html', [
    'title' => 'Edit Staff Permissions - ' . $targetStaff['firstName'] . ' ' . $targetStaff['lastName'],
    'app_name' => 'COR4EDU SMS',
    'user' => array_merge($sessionData, ['permissions' => $permissions]),
    'target_staff' => $targetStaff,
    'system_permissions' => $systemPermissions,
    'current_permissions' => $currentPermissions,
    'role_defaults' => $roleDefaults,
    'grouped_permissions' => array_reduce($systemPermissions, function($carry, $perm) {
        $carry[$perm['category']][] = $perm;
        return $carry;
    }, []),
    'is_super_admin' => $isSuper
]);
?>