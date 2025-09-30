<?php
/**
 * Student Document Requirement Delete Process Module
 * Following Gibbon patterns exactly - handles requirement document removal with audit trail preservation
 */

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Get form data
$studentID = $_POST['studentID'] ?? '';
$requirementCode = $_POST['requirementCode'] ?? '';

// Validate required fields
$errors = [];

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
    $errors[] = 'You do not have permission to delete documents in this section.';
}

if (empty($studentID)) {
    $errors[] = 'Student ID is required.';
}

if (empty($requirementCode)) {
    $errors[] = 'Requirement code is required.';
}

// If there are validation errors, redirect back
if (!empty($errors)) {
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . urlencode($studentID));
    exit;
}

try {
    // Initialize gateways
    $studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');
    $documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');
    $requirementGateway = getGateway('Cor4Edu\Domain\Document\DocumentRequirementGateway');

    // Verify student exists
    $student = $studentGateway->selectStudentWithProgram($studentID);
    if (!$student) {
        throw new Exception('Student not found.');
    }

    // Verify requirement exists
    $requirement = $requirementGateway->getRequirementByCode($requirementCode);
    if (!$requirement) {
        throw new Exception('Requirement not found.');
    }

    // Get current requirement document
    $currentDocument = $documentGateway->getRequirementDocument((int)$studentID, $requirementCode);
    if (!$currentDocument) {
        throw new Exception('No document found for this requirement.');
    }

    // Unlink the requirement document (keeps document in archive for audit trail)
    $unlinkSuccess = $documentGateway->unlinkRequirementDocument(
        (int)$studentID,
        $requirementCode,
        $_SESSION['cor4edu']['staffID']
    );

    if ($unlinkSuccess) {
        $_SESSION['flash_success'] = $requirement['displayName'] . ' removed from requirement. Document preserved in document management for audit trail.';
        // Include tab parameter to preserve tab context
        $tabName = $requirement['tabName'];
        header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . urlencode($studentID) . '&tab=' . urlencode($tabName));
        exit;
    } else {
        throw new Exception('Failed to remove requirement document.');
    }

} catch (Exception $e) {
    $_SESSION['flash_errors'] = ['Error removing requirement document: ' . $e->getMessage()];
    // If we have requirement data, include tab parameter
    $tabRedirect = '';
    if (isset($requirement['tabName'])) {
        $tabRedirect = '&tab=' . urlencode($requirement['tabName']);
    }
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . urlencode($studentID) . $tabRedirect);
    exit;
}