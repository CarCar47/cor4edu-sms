<?php

/**
 * Admin Staff Permissions Module
 * Manage individual staff permissions
 */

// Check if logged in and is admin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    header('Location: index.php?q=/login');
    exit;
}

// Get staff ID from URL
$staffID = $_GET['staffID'] ?? '';

if (empty($staffID)) {
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Get staff details
global $container;
$pdo = $container['db'];

$stmt = $pdo->prepare("SELECT staffID, firstName, lastName, username FROM cor4edu_staff WHERE staffID = ?");
$stmt->execute([$staffID]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    $_SESSION['flash_errors'] = ['Staff member not found'];
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Handle form submission for adding/removing permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $module = $_POST['module'] ?? '';
    $permissionAction = $_POST['permission_action'] ?? '';

    try {
        if ($action === 'add' && !empty($module) && !empty($permissionAction)) {
            // Add permission
            $stmt = $pdo->prepare("
                INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
                VALUES (?, ?, ?, 'Y', ?, NOW())
                ON DUPLICATE KEY UPDATE allowed = 'Y'
            ");
            $stmt->execute([$staffID, $module, $permissionAction, $_SESSION['cor4edu']['staffID']]);
            $_SESSION['flash_success'] = 'Permission added successfully';
        } elseif ($action === 'remove' && !empty($module) && !empty($permissionAction)) {
            // Remove permission
            $stmt = $pdo->prepare("DELETE FROM cor4edu_staff_permissions WHERE staffID = ? AND module = ? AND action = ?");
            $stmt->execute([$staffID, $module, $permissionAction]);
            $_SESSION['flash_success'] = 'Permission removed successfully';
        }
    } catch (Exception $e) {
        $_SESSION['flash_errors'] = ['Error updating permissions: ' . $e->getMessage()];
    }

    // Redirect to avoid form resubmission
    header('Location: index.php?q=/modules/Admin/Staff/staff_permissions.php&staffID=' . $staffID);
    exit;
}

// Get current permissions
$stmt = $pdo->prepare("
    SELECT module, action, allowed, createdOn
    FROM cor4edu_staff_permissions
    WHERE staffID = ?
    ORDER BY module, action
");
$stmt->execute([$staffID]);
$currentPermissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Available permissions (modules and actions)
$availablePermissions = [
    'students' => ['read', 'create', 'write', 'delete'],
    'payments' => ['read', 'create', 'write', 'delete'],
    'programs' => ['read', 'create', 'write', 'delete'],
    'documents' => ['read', 'upload', 'delete'],
    'reports' => ['admissions', 'financial', 'academic', 'career_services'],
    'faculty_notes' => ['read', 'create', 'write', 'delete'],
    'academic_support' => ['read', 'create', 'write', 'delete'],
    'job_applications' => ['read', 'create', 'write', 'delete']
];

// Get user permissions for navigation
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Get session messages
$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Redirect to new permission system
$staffID = $_GET['staffID'] ?? '';
header('Location: index.php?q=/modules/Admin/Permissions/staff_permissions_edit.php&staffID=' . $staffID);
exit;
