<?php
/**
 * Student Delete Module
 * Handles soft deletion of students with security checks
 */

// Check if logged in and has permission
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

$currentStaffID = $_SESSION['cor4edu']['staffID'];
$isSuperAdmin = $_SESSION['cor4edu']['is_super_admin'];

// Get student ID to delete from URL
$studentIDToDelete = $_GET['studentID'] ?? '';

if (empty($studentIDToDelete)) {
    $_SESSION['flash_errors'] = ['No student specified'];
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Initialize gateways
$studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');

// Get database connection
global $container;
$pdo = $container['db'];

// Get student details to delete
$stmt = $pdo->prepare("
    SELECT s.studentID, s.firstName, s.lastName, s.studentCode, s.status,
           p.name as programName
    FROM cor4edu_students s
    LEFT JOIN cor4edu_programs p ON s.programID = p.programID
    WHERE s.studentID = ?
");
$stmt->execute([$studentIDToDelete]);
$studentToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$studentToDelete) {
    $_SESSION['flash_errors'] = ['Student not found'];
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Security checks
$errors = [];

// 1. Check if student is already withdrawn
if ($studentToDelete['status'] === 'withdrawn') {
    $errors[] = 'This student is already marked as withdrawn';
}

// 2. Check permission (if not SuperAdmin)
if (!$isSuperAdmin) {
    if (!hasPermission($currentStaffID, 'students', 'delete_students')) {
        $errors[] = 'You do not have permission to delete students';
    }
}

// If errors, redirect back
if (!empty($errors)) {
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Handle confirmation
$confirmed = $_GET['confirm'] ?? 'no';

if ($confirmed !== 'yes') {
    // Show confirmation page
    $sessionData = $_SESSION['cor4edu'];
    $reportPermissions = getUserPermissionsForNavigation($currentStaffID);

    echo $twig->render('students/delete_confirm.twig.html', [
        'title' => 'Delete Student - Confirmation',
        'student' => $studentToDelete,
        'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
    ]);
    exit;
}

// Perform soft delete
try {
    $pdo->beginTransaction();

    // Soft delete: Set status to 'withdrawn' and withdrawnDate
    $stmt = $pdo->prepare("
        UPDATE cor4edu_students
        SET status = 'withdrawn',
            withdrawnDate = CURDATE(),
            modifiedBy = ?,
            modifiedOn = NOW()
        WHERE studentID = ?
    ");

    $stmt->execute([$currentStaffID, $studentIDToDelete]);

    $pdo->commit();

    $_SESSION['flash_success'] = [
        "Student '{$studentToDelete['firstName']} {$studentToDelete['lastName']}' ({$studentToDelete['studentCode']}) has been withdrawn successfully"
    ];

    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Student deletion failed: " . $e->getMessage());

    $_SESSION['flash_errors'] = ['Failed to withdraw student. Please try again.'];
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}