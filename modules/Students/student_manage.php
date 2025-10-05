<?php

/**
 * Student Management Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Security check - ensure user has permission to access students
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// SuperAdmin bypass - always allow SuperAdmin access
if (!$_SESSION['cor4edu']['is_super_admin']) {
    // Check if user has permission to view students
    if (!hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_information_tab')) {
        header('Location: index.php?q=/access_denied');
        exit;
    }
}

// Initialize student gateway - Gibbon style
$studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');

// Load user permissions for navigation consistency
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

// Get students based on filters
if (!empty($search) || !empty($status)) {
    $students = $studentGateway->searchStudents($search, $status);
} else {
    $students = $studentGateway->selectAllWithPrograms();
}

// Get status counts for filter buttons
$statusCounts = $studentGateway->getStatusCounts();

// Get session messages
$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Render the template
echo $twig->render('students/index.twig.html', [
    'title' => 'Students - COR4EDU SMS',
    'students' => $students,
    'statusCounts' => $statusCounts,
    'currentSearch' => $search,
    'currentStatus' => $status,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);
