<?php
/**
 * COR4EDU SMS Overview Reports
 * Institution-wide statistics and summary reports
 */

// Check if logged in and has permission
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// Clear any persistent error messages from previous startDate issues
unset($_SESSION['cor4edu']['flash_errors']);

// Check permissions using new system
$navigationPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);
$hasAccess = isset($navigationPermissions['view_reports_tab']) && $navigationPermissions['view_reports_tab'];

if (!$hasAccess) {
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Access Denied - Reports',
        'message' => 'You do not have permission to access the reports section.'
    ]);
    exit;
}

// Get filter parameters
$reportType = $_GET['reportType'] ?? 'summary';
$dateRange = $_GET['dateRange'] ?? '30';
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';

// Calculate date range if not provided
if (empty($startDate) || empty($endDate)) {
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime("-{$dateRange} days"));
}

// Available report types
$availableReportTypes = [
    ['value' => 'summary', 'label' => 'Institution Summary'],
    ['value' => 'trends', 'label' => 'Enrollment Trends'],
    ['value' => 'programs', 'label' => 'Program Summary'],
    ['value' => 'dashboard', 'label' => 'Dashboard Metrics']
];

// Get data based on report type
try {
    $reportsGateway = getGateway('Cor4Edu\Reports\Domain\ReportsGateway');

    $reportData = null;
    $reportTitle = '';

    switch ($reportType) {
        case 'summary':
            $reportData = $reportsGateway->getInstitutionOverview();
            $reportTitle = 'Institution Overview Summary';
            break;

        case 'trends':
            $reportData = $reportsGateway->getEnrollmentTrends($startDate, $endDate);
            $reportTitle = 'Enrollment Trends';
            break;

        case 'programs':
            $reportData = $reportsGateway->getProgramEnrollmentSummary([
                'startDate' => $startDate,
                'endDate' => $endDate
            ]);
            $reportTitle = 'Program Enrollment Summary';
            break;

        case 'dashboard':
            $reportData = $reportsGateway->getInstitutionOverview();
            $reportTitle = 'Dashboard Metrics';
            break;

        default:
            $reportData = $reportsGateway->getInstitutionOverview();
            $reportTitle = 'Institution Overview Summary';
    }

    // Get available programs for filters
    $availablePrograms = $reportsGateway->getAvailablePrograms();

} catch (Exception $e) {
    $reportData = null;
    $reportTitle = 'Error Loading Report';
    $availablePrograms = [];
    error_log("Overview Reports Error: " . $e->getMessage());

    // Set flash error message
    $_SESSION['cor4edu']['flash_errors'] = ['Error loading report data: ' . $e->getMessage()];
}

// Prepare template data
$templateData = [
    'title' => 'Overview Reports - COR4EDU SMS',
    'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $navigationPermissions]),
    'activeTab' => 'overview',
    'reportType' => $reportType,
    'reportTitle' => $reportTitle,
    'reportData' => $reportData,
    'selectedStartDate' => $startDate,
    'selectedEndDate' => $endDate,
    'availableReportTypes' => $availableReportTypes,
    'availablePrograms' => $availablePrograms,
    'selectedReportType' => $reportType,

    // Filter form configuration
    'filterTitle' => 'Overview Report Filters',
    'currentUrl' => 'index.php',
    'currentQuery' => '/modules/Reports/reports_overview.php',
    'showReportTypeFilter' => true,
    'showDateFilter' => ($reportType === 'trends' || $reportType === 'programs'),
    'showExportButtons' => true,
    'dateFilterLabel' => 'Enrollment Date'
];

// Render the template
echo $twig->render('reports/overview/overview.twig.html', $templateData);
?>