<?php

/**
 * Student Document Upload Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Get parameters
$studentID = $_GET['studentID'] ?? '';
$category = $_GET['category'] ?? 'personal';

if (empty($studentID)) {
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Initialize gateways
$studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');
$documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');

// Get student details
$student = $studentGateway->selectStudentWithProgram($studentID);

if (!$student) {
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Get available categories
$categories = $documentGateway->getStudentDocumentCategories();

// Get existing documents for this category
$documents = $documentGateway->getStudentDocumentsByCategory((int)$studentID, $category);

// Get session messages
$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Get user permissions for navigation
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Render the template
echo $twig->render('students/documents/upload.twig.html', [
    'title' => 'Upload Documents - ' . $student['firstName'] . ' ' . $student['lastName'],
    'student' => $student,
    'categories' => $categories,
    'currentCategory' => $category,
    'documents' => $documents,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);
