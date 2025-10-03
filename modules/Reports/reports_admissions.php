<?php
/**
 * COR4EDU SMS Admissions & Enrollment Reports
 * Student enrollment tracking, demographics, and application analytics
 */

// Check if logged in and has permission
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// Check permissions
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

$hasAccess = isset($reportPermissions['view_reports_tab']) || isset($reportPermissions['generate_admissions_reports']);

if (!$hasAccess || !isset($reportPermissions['generate_admissions_reports'])) {
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Access Denied - Admissions Reports',
        'message' => 'You do not have permission to access admissions reports.'
    ]);
    exit;
}

// Get filter parameters
$reportType = $_GET['reportType'] ?? 'enrollment_summary';
$programID = $_GET['programID'] ?? [];
$status = $_GET['status'] ?? [];
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';
$optionalFields = $_GET['optionalFields'] ?? [];

// Ensure arrays
if (!is_array($programID)) $programID = $programID ? [$programID] : [];
if (!is_array($status)) $status = $status ? [$status] : [];
if (!is_array($optionalFields)) $optionalFields = $optionalFields ? [$optionalFields] : [];
$programID = array_filter($programID); // Remove empty values
$status = array_filter($status); // Remove empty values

// Default date range (last 12 months)
if (empty($startDate) || empty($endDate)) {
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-12 months'));
}

// Available report types
$availableReportTypes = [
    ['value' => 'enrollment_summary', 'label' => 'Enrollment Summary (Global)'],
    ['value' => 'student_list', 'label' => 'Student Enrollment List (Individual)'],
    ['value' => 'demographics', 'label' => 'Student Demographics'],
    ['value' => 'trends', 'label' => 'Enrollment Trends'],
    ['value' => 'program_analysis', 'label' => 'Program Analysis']
];

// Available optional fields for detailed reports
$availableOptionalFields = [
    ['value' => 'address', 'label' => 'Address Information'],
    ['value' => 'demographics', 'label' => 'Demographics (DOB, Gender)'],
    ['value' => 'financial', 'label' => 'Financial Summary'],
    ['value' => 'employment', 'label' => 'Employment Status']
];

// Get data based on report type
try {
    $reportsGateway = getGateway('Cor4Edu\Reports\Domain\ReportsGateway');

    // Build filters array
    $filters = [];
    if (!empty($programID)) $filters['programID'] = $programID;
    if (!empty($status)) $filters['status'] = $status;
    if (!empty($startDate)) $filters['startDate'] = $startDate;
    if (!empty($endDate)) $filters['endDate'] = $endDate;

    $reportData = null;
    $reportTitle = '';
    $summaryStats = null;

    switch ($reportType) {
        case 'enrollment_summary':
            $reportData = $reportsGateway->getProgramEnrollmentSummary($filters);
            $reportTitle = 'Program Enrollment Summary';
            break;

        case 'student_list':
            $reportData = $reportsGateway->getStudentDetailReport($filters, $optionalFields);
            $reportTitle = 'Student Enrollment List';
            break;

        case 'demographics':
            // Get student details with demographic fields
            $demographicFilters = $filters;
            $reportData = $reportsGateway->getStudentDetailReport($demographicFilters, ['demographics', 'address']);
            $reportTitle = 'Student Demographics Report';
            break;

        case 'trends':
            $trendsData = $reportsGateway->getEnrollmentTrends($startDate, $endDate);
            $reportData = $trendsData['details'];
            $summaryStats = $trendsData['summary'];
            $reportTitle = 'Enrollment Trends Analysis';
            break;

        case 'program_analysis':
            $reportData = $reportsGateway->getProgramEnrollmentSummary($filters);
            $reportTitle = 'Program Performance Analysis';
            break;

        default:
            $reportData = $reportsGateway->getProgramEnrollmentSummary($filters);
            $reportTitle = 'Program Enrollment Summary';
    }

    // Get available filter options
    $availablePrograms = $reportsGateway->getAvailablePrograms();
    $availableStatuses = $reportsGateway->getAvailableStatuses();

} catch (Exception $e) {
    $reportData = null;
    $reportTitle = 'Error Loading Report';
    $availablePrograms = [];
    $availableStatuses = [];
    error_log("Admissions Reports Error: " . $e->getMessage());

    // Set flash error message
    $_SESSION['cor4edu']['flash_errors'] = ['Error loading report data: ' . $e->getMessage()];
}

// Build export URL with all current filters
$exportParams = http_build_query([
    'reportType' => $reportType,
    'programID' => $programID,
    'status' => $status,
    'startDate' => $startDate,
    'endDate' => $endDate,
    'optionalFields' => $optionalFields
]);

// Prepare template data
$templateData = [
    'title' => 'Admissions & Enrollment Reports - COR4EDU SMS',
    'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $reportPermissions]),
    'activeTab' => 'admissions',
    'reportType' => $reportType,
    'reportTitle' => $reportTitle,
    'reportData' => $reportData,
    'summaryStats' => $summaryStats,
    'selectedStartDate' => $startDate,
    'selectedEndDate' => $endDate,
    'selectedPrograms' => $programID,
    'selectedStatuses' => $status,
    'selectedOptionalFields' => $optionalFields,
    'availableReportTypes' => $availableReportTypes,
    'availablePrograms' => $availablePrograms,
    'availableStatuses' => $availableStatuses,
    'availableOptionalFields' => $availableOptionalFields,
    'selectedReportType' => $reportType,

    // Filter form configuration
    'filterTitle' => 'Admissions & Enrollment Filters',
    'currentUrl' => 'index.php',
    'currentQuery' => '/modules/Reports/reports_admissions.php',
    'showReportTypeFilter' => true,
    'showProgramFilter' => true,
    'showStatusFilter' => true,
    'showDateFilter' => true,
    'showOptionalFieldsFilter' => ($reportType === 'student_list'),
    'showExportButtons' => true,
    'dateFilterLabel' => 'Enrollment Date',
    'exportUrl' => 'index.php?q=/modules/Reports/reports_admissions_export.php',
    'exportParams' => $exportParams
];

// Render the template
echo $twig->render('reports/admissions/admissions.twig.html', $templateData);
?>