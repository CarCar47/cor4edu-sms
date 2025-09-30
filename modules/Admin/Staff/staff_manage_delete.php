<?php
/**
 * Admin Staff Delete Module
 * Handles soft deletion of staff members with security checks
 */

// Check if logged in and has permission
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

$currentStaffID = $_SESSION['cor4edu']['staffID'];
$isSuperAdmin = $_SESSION['cor4edu']['is_super_admin'];

// Get staff ID to delete from URL
$staffIDToDelete = $_GET['staffID'] ?? '';

if (empty($staffIDToDelete)) {
    $_SESSION['flash_errors'] = ['No staff member specified'];
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Initialize gateways
$staffGateway = getGateway('Cor4Edu\Domain\Staff\StaffGateway');

// Get database connection
global $container;
$pdo = $container['db'];

// Get staff member details to delete
$stmt = $pdo->prepare("SELECT staffID, firstName, lastName, isSuperAdmin, active FROM cor4edu_staff WHERE staffID = ?");
$stmt->execute([$staffIDToDelete]);
$staffToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staffToDelete) {
    $_SESSION['flash_errors'] = ['Staff member not found'];
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Security checks
$errors = [];

// 1. Prevent self-deletion
if ($currentStaffID == $staffIDToDelete) {
    $errors[] = 'You cannot delete your own account';
}

// 2. Check if staff is already inactive
if ($staffToDelete['active'] === 'N') {
    $errors[] = 'This staff member is already inactive';
}

// 3. Non-SuperAdmin cannot delete SuperAdmin accounts
if (!$isSuperAdmin && $staffToDelete['isSuperAdmin'] === 'Y') {
    $errors[] = 'Only SuperAdmins can delete SuperAdmin accounts';
}

// 4. Check permission (if not SuperAdmin)
if (!$isSuperAdmin) {
    if (!hasPermission($currentStaffID, 'staff', 'delete_staff')) {
        $errors[] = 'You do not have permission to delete staff members';
    }
}

// If errors, redirect back
if (!empty($errors)) {
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Handle confirmation
$confirmed = $_GET['confirm'] ?? 'no';

if ($confirmed !== 'yes') {
    // Show confirmation page
    $sessionData = $_SESSION['cor4edu'];
    $reportPermissions = getUserPermissionsForNavigation($currentStaffID);

    echo $twig->render('admin/staff/delete_confirm.twig.html', [
        'title' => 'Delete Staff Member - Confirmation',
        'staff' => $staffToDelete,
        'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
    ]);
    exit;
}

// Perform soft delete
try {
    $pdo->beginTransaction();

    // Soft delete: Set active to 'N'
    $stmt = $pdo->prepare("
        UPDATE cor4edu_staff
        SET active = 'N',
            lastModifiedBy = ?,
            lastModifiedOn = NOW()
        WHERE staffID = ?
    ");

    $stmt->execute([$currentStaffID, $staffIDToDelete]);

    // Log the deletion for audit trail
    $stmt = $pdo->prepare("
        INSERT INTO cor4edu_staff_permissions
        (staffID, module, action, allowed, createdBy, createdOn)
        VALUES
        (?, 'system', 'staff_deleted', 'N', ?, NOW())
    ");
    $stmt->execute([$staffIDToDelete, $currentStaffID]);

    $pdo->commit();

    $_SESSION['flash_success'] = [
        "Staff member '{$staffToDelete['firstName']} {$staffToDelete['lastName']}' has been deactivated successfully"
    ];

    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Staff deletion failed: " . $e->getMessage());

    $_SESSION['flash_errors'] = ['Failed to deactivate staff member. Please try again.'];
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}