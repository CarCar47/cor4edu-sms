<?php
/**
 * COR4EDU SMS Career Services Reports
 * Job placement tracking, employment analytics, and compliance reporting
 */

// Check if logged in and has permission
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// Check permissions
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

$hasAccess = isset($reportPermissions['view_reports_tab']) || isset($reportPermissions['generate_career_reports']);

if (!$hasAccess || !isset($reportPermissions['generate_career_reports'])) {
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Access Denied - Career Services Reports',
        'message' => 'You do not have permission to access career services reports.'
    ]);
    exit;
}

// Get filter parameters
$reportType = $_GET['reportType'] ?? 'placement_rate';
$programID = $_GET['programID'] ?? [];
$studentStatus = $_GET['studentStatus'] ?? [];
$employmentStatus = $_GET['employmentStatus'] ?? [];
$verificationStatus = $_GET['verificationStatus'] ?? '';
$graduationDateStart = $_GET['graduationDateStart'] ?? '';
$graduationDateEnd = $_GET['graduationDateEnd'] ?? '';

// Ensure arrays
if (!is_array($programID)) $programID = $programID ? [$programID] : [];
if (!is_array($studentStatus)) $studentStatus = $studentStatus ? [$studentStatus] : [];
if (!is_array($employmentStatus)) $employmentStatus = $employmentStatus ? [$employmentStatus] : [];
$programID = array_filter($programID); // Remove empty values
$studentStatus = array_filter($studentStatus); // Remove empty values
// Don't filter employmentStatus - empty string '' is valid for "No Status Set" (NULL filter)

// Default student status (all career-relevant statuses)
if (empty($studentStatus)) {
    $studentStatus = ['Active', 'Graduated', 'Alumni'];
}

// Default date range (last 12 months)
if (empty($graduationDateStart) || empty($graduationDateEnd)) {
    $graduationDateEnd = date('Y-m-d');
    $graduationDateStart = date('Y-m-d', strtotime('-12 months'));
}

// Available report types
$availableReportTypes = [
    ['value' => 'placement_rate', 'label' => 'Job Placement Rate by Program'],
    ['value' => 'verification_report', 'label' => 'Job Placement Verification Report (State Audit)'],
    ['value' => 'outcomes_summary', 'label' => 'Employment Outcomes Summary'],
    ['value' => 'unverified_placements', 'label' => 'Unverified Placements (Action Items)']
];

// Get data based on report type
try {
    $careerReportsGateway = getGateway('Cor4Edu\Reports\Domain\CareerReportsGateway');

    // Build filters array
    $filters = [];
    if (!empty($programID)) $filters['programID'] = $programID;
    if (!empty($studentStatus)) $filters['studentStatus'] = $studentStatus;
    if (!empty($employmentStatus)) $filters['employmentStatus'] = $employmentStatus;
    if (!empty($verificationStatus)) $filters['verificationStatus'] = $verificationStatus;
    if (!empty($graduationDateStart)) $filters['graduationDateStart'] = $graduationDateStart;
    if (!empty($graduationDateEnd)) $filters['graduationDateEnd'] = $graduationDateEnd;

    $reportData = null;
    $reportTitle = '';

    switch ($reportType) {
        case 'placement_rate':
            $reportData = $careerReportsGateway->getPlacementSummaryByProgram($filters);
            $reportTitle = 'Job Placement Rate by Program';
            break;

        case 'verification_report':
            $reportData = $careerReportsGateway->getJobPlacementVerificationReport($filters);
            $reportTitle = 'Job Placement Verification Report (State Audit)';
            break;

        case 'outcomes_summary':
            $reportData = $careerReportsGateway->getStudentCareerDetails($filters);
            $reportTitle = 'Employment Outcomes Summary';
            break;

        case 'unverified_placements':
            $reportData = $careerReportsGateway->getUnverifiedPlacements($filters);
            $reportTitle = 'Unverified Placements';
            break;

        default:
            $reportData = $careerReportsGateway->getPlacementSummaryByProgram($filters);
            $reportTitle = 'Job Placement Rate by Program';
    }

    // Get available filter options
    $availablePrograms = $careerReportsGateway->getAvailablePrograms();
    $availableStudentStatuses = $careerReportsGateway->getAvailableStudentStatuses();
    $availableEmploymentStatuses = $careerReportsGateway->getAvailableEmploymentStatuses();

} catch (Exception $e) {
    $reportData = null;
    $reportTitle = 'Error Loading Report';
    $availablePrograms = [];
    $availableStudentStatuses = [];
    $availableEmploymentStatuses = [];
    error_log("Career Reports Error: " . $e->getMessage());

    // Set flash error message
    $_SESSION['cor4edu']['flash_errors'] = ['Error loading report data: ' . $e->getMessage()];
}

// Verification status options
$availableVerificationStatuses = [
    ['value' => '', 'label' => 'All Verification Statuses'],
    ['value' => 'verified', 'label' => 'Verified'],
    ['value' => 'unverified', 'label' => 'Unverified']
];

// Build export URL with all current filters
$exportParams = http_build_query([
    'reportType' => $reportType,
    'programID' => $programID,
    'studentStatus' => $studentStatus,
    'employmentStatus' => $employmentStatus,
    'verificationStatus' => $verificationStatus,
    'graduationDateStart' => $graduationDateStart,
    'graduationDateEnd' => $graduationDateEnd
]);

// Prepare template data
$templateData = [
    'title' => 'Career Services Reports - COR4EDU SMS',
    'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $reportPermissions]),
    'activeTab' => 'career',
    'reportType' => $reportType,
    'reportTitle' => $reportTitle,
    'reportData' => $reportData,
    'selectedGraduationDateStart' => $graduationDateStart,
    'selectedGraduationDateEnd' => $graduationDateEnd,
    'selectedPrograms' => $programID,
    'selectedStudentStatuses' => $studentStatus,
    'selectedEmploymentStatuses' => $employmentStatus,
    'selectedVerificationStatus' => $verificationStatus,
    'availableReportTypes' => $availableReportTypes,
    'availablePrograms' => $availablePrograms,
    'availableStudentStatuses' => $availableStudentStatuses,
    'availableEmploymentStatuses' => $availableEmploymentStatuses,
    'availableVerificationStatuses' => $availableVerificationStatuses,
    'selectedReportType' => $reportType,

    // Filter form configuration
    'filterTitle' => 'Career Services Filters',
    'currentUrl' => 'index.php',
    'currentQuery' => '/modules/Reports/reports_career.php',
    'showReportTypeFilter' => true,
    'showProgramFilter' => true,
    'showStudentStatusFilter' => true,
    'showEmploymentStatusFilter' => true,
    'showVerificationStatusFilter' => true,
    'showDateFilter' => true,
    'showExportButtons' => true,
    'dateFilterLabel' => 'Student Date Range (Enrollment/Graduation)',
    'exportUrl' => 'index.php?q=/modules/Reports/reports_career_export.php',
    'exportParams' => $exportParams
];

// Render the template
echo $twig->render('reports/career/career.twig.html', $templateData);
?>