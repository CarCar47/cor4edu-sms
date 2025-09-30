<?php
/**
 * Admin Staff Management Module
 * Allows admins to view, create, edit staff users and manage permissions
 */

// Check if logged in and is admin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    header('Location: index.php?q=/login');
    exit;
}

// Initialize gateways
$staffGateway = getGateway('Cor4Edu\Domain\Staff\StaffGateway');
$staffProfileGateway = getGateway('Cor4Edu\Domain\Staff\StaffProfileGateway');

// Load user permissions for navigation consistency
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$roleTypeFilter = $_GET['roleType'] ?? '';
$statusFilter = $_GET['status'] ?? '';

// Get all staff with role information
global $container;
$pdo = $container['db'];

$sql = "SELECT s.*, rt.roleTypeName, rt.description as roleDescription, rt.isAdminRole,
               CONCAT(creator.firstName, ' ', creator.lastName) as createdByName
        FROM cor4edu_staff s
        LEFT JOIN cor4edu_staff_role_types rt ON s.roleTypeID = rt.roleTypeID
        LEFT JOIN cor4edu_staff creator ON s.createdBy = creator.staffID
        WHERE 1=1";

$params = [];

// Apply search filter
if (!empty($search)) {
    $sql .= " AND (s.firstName LIKE ? OR s.lastName LIKE ? OR s.email LIKE ? OR s.staffCode LIKE ?)";
    $searchParam = "%{$search}%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

// Apply role type filter
if (!empty($roleTypeFilter)) {
    $sql .= " AND s.roleTypeID = ?";
    $params[] = $roleTypeFilter;
}

// Apply status filter
if (!empty($statusFilter)) {
    $sql .= " AND s.active = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY s.lastName, s.firstName";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all role types for filter dropdown
$roleTypes = $staffProfileGateway->getAllRoleTypes();

// Get total counts for dashboard
$totalStaff = count($staff);
$activeStaff = count(array_filter($staff, function($s) { return $s['active'] === 'Y'; }));

// Get session messages
$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Render the template
echo $twig->render('admin/staff/manage.twig.html', [
    'title' => 'Staff Management - COR4EDU SMS',
    'staff' => $staff,
    'roleTypes' => $roleTypes,
    'search' => $search,
    'roleTypeFilter' => $roleTypeFilter,
    'statusFilter' => $statusFilter,
    'totalStaff' => $totalStaff,
    'activeStaff' => $activeStaff,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);