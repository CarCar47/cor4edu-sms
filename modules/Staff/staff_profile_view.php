<?php
/**
 * Staff Profile View Module
 * Shows staff member their own profile with 3 sections: Information, Required Documents, Other Documents
 */

// Check if logged in
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// Initialize gateways
$staffProfileGateway = getGateway('Cor4Edu\Domain\Staff\StaffProfileGateway');

// Get current staff member's ID from session
$staffID = $_SESSION['cor4edu']['staffID'];

// Get staff profile
$staffProfile = $staffProfileGateway->getStaffProfile($staffID);

if (!$staffProfile) {
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Profile Not Found - COR4EDU SMS',
        'message' => 'Your staff profile could not be found.',
        'user' => $_SESSION['cor4edu']
    ]);
    exit;
}

// Get document requirements and status
$documentRequirements = $staffProfileGateway->getDocumentRequirements();
$documentStatus = $staffProfileGateway->getStaffDocumentStatus($staffID);
$otherDocuments = $staffProfileGateway->getStaffOtherDocuments($staffID);

// Get all programs for teaching assignment dropdown
$allPrograms = $staffProfileGateway->getAllPrograms();

// Parse teaching programs JSON
$teachingPrograms = [];
if (!empty($staffProfile['teachingPrograms'])) {
    $teachingPrograms = json_decode($staffProfile['teachingPrograms'], true) ?: [];
}

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
echo $twig->render('staff/profile.twig.html', [
    'title' => 'My Profile - ' . $staffProfile['firstName'] . ' ' . $staffProfile['lastName'],
    'staffProfile' => $staffProfile,
    'documentRequirements' => $documentRequirements,
    'documentStatus' => $documentStatus,
    'otherDocuments' => $otherDocuments,
    'allPrograms' => $allPrograms,
    'teachingPrograms' => $teachingPrograms,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);