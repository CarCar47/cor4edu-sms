<?php
/**
 * Student Edit Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Initialize gateways - Gibbon style
$studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');
$programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');

// Get student ID from URL
$studentID = $_GET['studentID'] ?? '';

if (empty($studentID)) {
    // No student ID provided, redirect back to student list
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Get student details
$student = $studentGateway->selectStudentWithProgram($studentID);

if (!$student) {
    // Student not found
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Student Not Found - COR4EDU SMS',
        'message' => 'The requested student could not be found.',
        'user' => $_SESSION['cor4edu']
    ]);
    exit;
}

// Get programs for dropdown
$programs = $programGateway->getActiveProgramsForDropdown();

// Get user permissions for navigation
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);
$sessionData = $_SESSION['cor4edu'];

// Render the template
echo $twig->render('students/edit.twig.html', [
    'title' => 'Edit ' . $student['firstName'] . ' ' . $student['lastName'] . ' - Student Details',
    'student' => $student,
    'programs' => $programs,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);