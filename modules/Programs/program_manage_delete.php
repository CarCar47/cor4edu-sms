<?php
/**
 * Program Delete Module
 * Handles soft deletion of programs with security checks
 * SuperAdmin only
 */

// Check if logged in and has permission
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

$currentStaffID = $_SESSION['cor4edu']['staffID'];
$isSuperAdmin = $_SESSION['cor4edu']['is_super_admin'];

// Get program ID to delete from URL
$programIDToDelete = $_GET['programID'] ?? '';

if (empty($programIDToDelete)) {
    $_SESSION['flash_errors'] = ['No program specified'];
    header('Location: index.php?q=/modules/Programs/program_manage.php');
    exit;
}

// Initialize gateways
$programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');

// Get database connection
global $container;
$pdo = $container['db'];

// Get program details to delete
$stmt = $pdo->prepare("
    SELECT programID, programCode, name, description, active
    FROM cor4edu_programs
    WHERE programID = ?
");
$stmt->execute([$programIDToDelete]);
$programToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$programToDelete) {
    $_SESSION['flash_errors'] = ['Program not found'];
    header('Location: index.php?q=/modules/Programs/program_manage.php');
    exit;
}

// Security checks
$errors = [];

// 1. Check permission - SuperAdmin only
if (!$isSuperAdmin) {
    if (!hasPermission($currentStaffID, 'programs', 'delete')) {
        $errors[] = 'You do not have permission to delete programs (SuperAdmin only)';
    }
}

// 2. Check total student count (ALL students - active, graduated, withdrawn)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as studentCount
    FROM cor4edu_students
    WHERE programID = ?
");
$stmt->execute([$programIDToDelete]);
$studentCheck = $stmt->fetch(PDO::FETCH_ASSOC);
$totalStudents = (int)$studentCheck['studentCount'];

// If errors, redirect back
if (!empty($errors)) {
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php?q=/modules/Programs/program_manage.php');
    exit;
}

// Handle POST (confirmation form submission)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Show confirmation page
    $sessionData = $_SESSION['cor4edu'];
    $reportPermissions = getUserPermissionsForNavigation($currentStaffID);

    echo $twig->render('programs/delete_confirm.twig.html', [
        'title' => 'Delete Program - Confirmation',
        'program' => $programToDelete,
        'studentCount' => $totalStudents,
        'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
    ]);
    exit;
}

// Check if permanent delete requested
$permanentDelete = ($_POST['permanent_delete'] ?? '0') === '1';

// Validate permanent delete: only allowed if zero students
if ($permanentDelete && $totalStudents > 0) {
    $_SESSION['flash_errors'] = ["Cannot permanently delete: {$totalStudents} student(s) have been enrolled in this program"];
    header('Location: index.php?q=/modules/Programs/program_manage.php');
    exit;
}

// Perform delete (permanent or soft)
try {
    $pdo->beginTransaction();

    if ($permanentDelete && $totalStudents === 0) {
        // PERMANENT DELETE: Remove from database completely
        $stmt = $pdo->prepare("DELETE FROM cor4edu_programs WHERE programID = ?");
        $stmt->execute([$programIDToDelete]);

        $successMessage = "Program '{$programToDelete['name']}' ({$programToDelete['programCode']}) has been permanently deleted";
    } else {
        // SOFT DELETE: Set active to 'N'
        $stmt = $pdo->prepare("
            UPDATE cor4edu_programs
            SET active = 'N',
                modifiedBy = ?,
                modifiedOn = NOW()
            WHERE programID = ?
        ");
        $stmt->execute([$currentStaffID, $programIDToDelete]);

        $successMessage = "Program '{$programToDelete['name']}' ({$programToDelete['programCode']}) has been deactivated successfully";
    }

    $pdo->commit();

    $_SESSION['flash_success'] = [$successMessage];
    header('Location: index.php?q=/modules/Programs/program_manage.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Program deletion failed: " . $e->getMessage());

    $_SESSION['flash_errors'] = ['Failed to delete program. Please try again.'];
    header('Location: index.php?q=/modules/Programs/program_manage.php');
    exit;
}
