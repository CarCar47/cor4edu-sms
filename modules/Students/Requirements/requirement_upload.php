<?php

/**
 * Student Document Requirement Upload Module
 * Following Gibbon patterns exactly - handles requirement-specific uploads
 */

// Get parameters
$studentID = $_GET['studentID'] ?? '';
$requirementCode = $_GET['requirementCode'] ?? '';

if (empty($studentID) || empty($requirementCode)) {
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Initialize gateways
$studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');
$documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');
$requirementGateway = getGateway('Cor4Edu\Domain\Document\DocumentRequirementGateway');

// Get student details
$student = $studentGateway->selectStudentWithProgram($studentID);

if (!$student) {
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Get requirement definition
$requirement = $requirementGateway->getRequirementByCode($requirementCode);

if (!$requirement) {
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . urlencode($studentID));
    exit;
}

// CRITICAL SECURITY CHECK: Verify user has EDIT permission for this requirement
// Map requirement code to tab name for permission checking
$requirementTabMap = [
    'id_verification' => 'information',
    'enrollment_agreement' => 'admissions',
    'hs_diploma_transcripts' => 'admissions',
    'payment_plan_agreement' => 'bursar',
    'current_resume' => 'career',
    'school_degree' => 'graduation',
    'school_transcript' => 'graduation'
];

$tabName = $requirementTabMap[$requirementCode] ?? 'information';

// Check if user has EDIT permission for this tab
if (!$_SESSION['cor4edu']['is_super_admin'] && !canUserEditTab($_SESSION['cor4edu']['staffID'], $tabName)) {
    $_SESSION['flash_errors'] = ['You do not have permission to edit documents in this section.'];
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . urlencode($studentID));
    exit;
}

// Get current requirement document
$currentDocument = $documentGateway->getRequirementDocument((int)$studentID, $requirementCode);

// Get requirement history for audit trail
$documentHistory = $documentGateway->getRequirementHistory((int)$studentID, $requirementCode);

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
echo $twig->render('students/requirements/upload.twig.html', [
    'title' => $requirement['displayName'] . ' - ' . $student['firstName'] . ' ' . $student['lastName'],
    'student' => $student,
    'requirement' => $requirement,
    'currentDocument' => $currentDocument,
    'documentHistory' => $documentHistory,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions]),
    'success' => $sessionData['flash_success'],
    'errors' => $sessionData['flash_errors']
]);
