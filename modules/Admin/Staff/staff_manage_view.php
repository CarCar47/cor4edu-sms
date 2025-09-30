<?php
/**
 * Admin Staff View Module
 * View detailed staff member information
 */

// Check if logged in and is admin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    header('Location: index.php?q=/login');
    exit;
}

// Initialize gateways
$staffProfileGateway = getGateway('Cor4Edu\Domain\Staff\StaffProfileGateway');
$documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');

// Get staff ID from URL
$staffID = $_GET['staffID'] ?? '';

if (empty($staffID)) {
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Get staff details
global $container;
$pdo = $container['db'];

$stmt = $pdo->prepare("
    SELECT s.*, rt.roleTypeName, rt.description as roleDescription,
           CONCAT(creator.firstName, ' ', creator.lastName) as createdByName,
           CONCAT(modifier.firstName, ' ', modifier.lastName) as modifiedByName
    FROM cor4edu_staff s
    LEFT JOIN cor4edu_staff_role_types rt ON s.roleTypeID = rt.roleTypeID
    LEFT JOIN cor4edu_staff creator ON s.createdBy = creator.staffID
    LEFT JOIN cor4edu_staff modifier ON s.lastModifiedBy = modifier.staffID
    WHERE s.staffID = ?
");
$stmt->execute([$staffID]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    $_SESSION['flash_errors'] = ['Staff member not found'];
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Get staff permissions
$stmt = $pdo->prepare("
    SELECT sp.module, sp.action, sp.allowed, sp.createdOn
    FROM cor4edu_staff_permissions sp
    WHERE sp.staffID = ?
    ORDER BY sp.module, sp.action
");
$stmt->execute([$staffID]);
$permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff document requirements
$staffRequirements = $documentGateway->getStaffRequirements((int)$staffID);

// Get user permissions for navigation
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Get session messages
$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Render the template
echo $twig->render('admin/staff/view.twig.html', [
    'title' => $staff['firstName'] . ' ' . $staff['lastName'] . ' - Staff Details',
    'staff' => $staff,
    'permissions' => $permissions,
    'staffRequirements' => $staffRequirements,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);