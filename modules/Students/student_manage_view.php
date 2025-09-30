<?php
/**
 * Student View Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Security check - ensure user has permission to access student details
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// SuperAdmin bypass - always allow SuperAdmin access
if (!$_SESSION['cor4edu']['is_super_admin']) {
    // Check if user has permission to view at least one student tab
    $hasStudentAccess = hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_information_tab') ||
                       hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_admissions_tab') ||
                       hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_bursar_tab') ||
                       hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_registrar_tab') ||
                       hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_academics_tab') ||
                       hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_career_tab') ||
                       hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_graduation_tab');

    if (!$hasStudentAccess) {
        header('Location: index.php?q=/access_denied');
        exit;
    }
}

// Initialize gateways - Gibbon style
$studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');
$paymentGateway = getGateway('Cor4Edu\Domain\Payment\PaymentGateway');
$financialGateway = getGateway('Cor4Edu\Domain\Financial\FinancialGateway');
$documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');
$careerPlacementGateway = getGateway('Cor4Edu\Domain\CareerPlacement\CareerPlacementGateway');

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

// Initialize data arrays
$financialSummary = null;
$programPayments = [];
$otherPayments = [];
$outstandingBalance = 0;
$informationRequirements = [];
$admissionsRequirements = [];
$bursarRequirements = [];
$careerRequirements = [];
$graduationRequirements = [];
$currentPlacement = null;
$placementHistory = [];

// Get financial data for bursar tab - only if user has permission
if ($_SESSION['cor4edu']['is_super_admin'] || hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_bursar_tab')) {
    $financialSummary = $financialGateway->getStudentFinancialSummary($studentID);
    $programPayments = $paymentGateway->selectPaymentsByStudent($studentID, 'program');
    $otherPayments = $paymentGateway->selectPaymentsByStudent($studentID, 'other');
    $outstandingBalance = $paymentGateway->calculateOutstandingBalance($studentID);
}

// Get document requirements for each tab - only load if user has access to that tab
if ($_SESSION['cor4edu']['is_super_admin'] || hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_information_tab')) {
    $informationRequirements = $documentGateway->getRequirementsByTab('information', (int)$studentID);
}
if ($_SESSION['cor4edu']['is_super_admin'] || hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_admissions_tab')) {
    $admissionsRequirements = $documentGateway->getRequirementsByTab('admissions', (int)$studentID);
}
if ($_SESSION['cor4edu']['is_super_admin'] || hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_bursar_tab')) {
    $bursarRequirements = $documentGateway->getRequirementsByTab('bursar', (int)$studentID);
}
if ($_SESSION['cor4edu']['is_super_admin'] || hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_career_tab')) {
    $careerRequirements = $documentGateway->getRequirementsByTab('career', (int)$studentID);
}
if ($_SESSION['cor4edu']['is_super_admin'] || hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_graduation_tab')) {
    $graduationRequirements = $documentGateway->getRequirementsByTab('graduation', (int)$studentID);
}

// Get career placement data - only if user has permission
if ($_SESSION['cor4edu']['is_super_admin'] || hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_career_tab')) {
    $currentPlacement = $careerPlacementGateway->getCurrentPlacementRecord((int)$studentID);
    $placementHistory = $careerPlacementGateway->getPlacementHistory((int)$studentID);
}

// Get faculty notes data
global $container;
$pdo = $container['db'];

// Get faculty notes
$stmt = $pdo->prepare("
    SELECT fn.*, s.firstName as facultyFirstName, s.lastName as facultyLastName
    FROM cor4edu_faculty_notes fn
    LEFT JOIN cor4edu_staff s ON fn.facultyID = s.staffID
    WHERE fn.studentID = ?
    ORDER BY fn.createdOn DESC
    LIMIT 10
");
$stmt->execute([$studentID]);
$facultyNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get student meetings
$stmt = $pdo->prepare("
    SELECT sm.*, s.firstName as facultyFirstName, s.lastName as facultyLastName
    FROM cor4edu_student_meetings sm
    LEFT JOIN cor4edu_staff s ON sm.facultyID = s.staffID
    WHERE sm.studentID = ?
    ORDER BY sm.meetingDate DESC
    LIMIT 10
");
$stmt->execute([$studentID]);
$studentMeetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get academic support sessions
$stmt = $pdo->prepare("
    SELECT ass.*, s.firstName as facultyFirstName, s.lastName as facultyLastName
    FROM cor4edu_academic_support_sessions ass
    LEFT JOIN cor4edu_staff s ON ass.facultyID = s.staffID
    WHERE ass.studentID = ?
    ORDER BY ass.sessionDate DESC
    LIMIT 10
");
$stmt->execute([$studentID]);
$supportSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff accessible tabs based on role
$staffProfileGateway = getGateway('Cor4Edu\Domain\Staff\StaffProfileGateway');
try {
    $accessibleTabs = $staffProfileGateway->getStaffAccessibleTabs($_SESSION['cor4edu']['staffID']);
    // Ensure at least one tab is always available
    if (empty($accessibleTabs)) {
        $accessibleTabs = ['information'];
    }
} catch (Exception $e) {
    // If there's an error, provide basic access
    $accessibleTabs = ['information'];
    error_log("StaffProfileGateway error: " . $e->getMessage());
}

// Get user permissions for navigation
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Get edit permissions for each tab
$editableTabsArray = getUserEditableTabsForStudents($_SESSION['cor4edu']['staffID']);
$editPermissions = [];

// Create edit permission flags for template
foreach ($accessibleTabs as $tab) {
    $editPermissions[$tab] = in_array($tab, $editableTabsArray);
}

// Add specific edit permissions for common actions
$editPermissions['can_edit_payments'] = $_SESSION['cor4edu']['is_super_admin'] ||
                                       hasEditPermission($_SESSION['cor4edu']['staffID'], 'students', 'edit_bursar_tab');
$editPermissions['can_edit_student_info'] = $_SESSION['cor4edu']['is_super_admin'] ||
                                           hasEditPermission($_SESSION['cor4edu']['staffID'], 'students', 'edit_information_tab');

// Get session messages - keep original session data intact
$sessionData = $_SESSION['cor4edu'];
$sessionData['session'] = [
    'success' => $_SESSION['flash_success'] ?? null,
    'payment_errors' => $_SESSION['flash_errors'] ?? null
];

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Render the template
echo $twig->render('students/view.twig.html', [
    'title' => $student['firstName'] . ' ' . $student['lastName'] . ' - Student Details',
    'student' => $student,
    'financialSummary' => $financialSummary,
    'programPayments' => $programPayments,
    'otherPayments' => $otherPayments,
    'outstandingBalance' => $outstandingBalance,
    'informationRequirements' => $informationRequirements,
    'admissionsRequirements' => $admissionsRequirements,
    'bursarRequirements' => $bursarRequirements,
    'careerRequirements' => $careerRequirements,
    'graduationRequirements' => $graduationRequirements,
    'currentPlacement' => $currentPlacement,
    'placementHistory' => $placementHistory,
    'facultyNotes' => $facultyNotes,
    'studentMeetings' => $studentMeetings,
    'supportSessions' => $supportSessions,
    'accessibleTabs' => $accessibleTabs,
    'editPermissions' => $editPermissions,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);