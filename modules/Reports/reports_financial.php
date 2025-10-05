<?php

/**
 * COR4EDU SMS Financial Reports
 * Payment tracking, outstanding balances, and financial analytics
 */

// Check if logged in and has permission
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// Check permissions using new system
$navigationPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);
$hasAccess = (isset($navigationPermissions['view_reports_tab']) && $navigationPermissions['view_reports_tab']) &&
             (isset($navigationPermissions['generate_financial_reports']) && $navigationPermissions['generate_financial_reports']);

if (!$hasAccess) {
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Access Denied - Financial Reports',
        'message' => 'You do not have permission to access financial reports.'
    ]);
    exit;
}

// Get filter parameters
$reportType = $_GET['reportType'] ?? 'financial_summary';
$programID = $_GET['programID'] ?? [];
$status = $_GET['status'] ?? [];
$enrollmentDateStart = $_GET['enrollmentDateStart'] ?? '';
$enrollmentDateEnd = $_GET['enrollmentDateEnd'] ?? '';
$outstandingOnly = isset($_GET['outstandingOnly']) ? $_GET['outstandingOnly'] : false;

// Ensure arrays
if (!is_array($programID)) {
    $programID = $programID ? [$programID] : [];
}
if (!is_array($status)) {
    $status = $status ? [$status] : [];
}
$programID = array_filter($programID); // Remove empty values
$status = array_filter($status); // Remove empty values

// Default status (all statuses if none selected)
if (empty($status)) {
    $status = ['Prospective', 'Active', 'Graduated', 'Alumni', 'Withdrawn'];
}

// Default date range (last 12 months for student enrollment period)
if (empty($enrollmentDateStart) || empty($enrollmentDateEnd)) {
    $enrollmentDateEnd = date('Y-m-d');
    $enrollmentDateStart = date('Y-m-d', strtotime('-12 months'));
}

// Available report types
$availableReportTypes = [
    ['value' => 'financial_summary', 'label' => 'Financial Summary by Program (Global)'],
    ['value' => 'student_financial', 'label' => 'Student Financial Details (Individual)'],
    ['value' => 'payment_history', 'label' => 'Payment History Report'],
    ['value' => 'outstanding_balances', 'label' => 'Outstanding Balances Summary'],
    ['value' => 'revenue_analysis', 'label' => 'Revenue Analysis']
];

// Special filters for financial reports
$specialFilters = [
    [
        'name' => 'outstandingOnly',
        'type' => 'checkbox',
        'label' => 'Show only students with outstanding balances',
        'checked' => $outstandingOnly
    ]
];

// Clear any previous errors first
unset($_SESSION['cor4edu']['flash_errors']);

// Get data based on report type
try {
    $reportsGateway = getGateway('Cor4Edu\Reports\Domain\ReportsGateway');
    $financialGateway = getGateway('Cor4Edu\Reports\Domain\FinancialReportsGateway');

    // Build filters array
    $filters = [];
    if (!empty($programID)) {
        $filters['programID'] = $programID;
    }
    if (!empty($status)) {
        $filters['status'] = $status;
    }
    if (!empty($enrollmentDateStart)) {
        $filters['enrollmentDateStart'] = $enrollmentDateStart;
    }
    if (!empty($enrollmentDateEnd)) {
        $filters['enrollmentDateEnd'] = $enrollmentDateEnd;
    }
    if ($outstandingOnly) {
        $filters['outstandingOnly'] = true;
    }

    $reportData = null;
    $reportTitle = '';

    switch ($reportType) {
        case 'financial_summary':
            $reportData = $financialGateway->getFinancialSummaryByProgram($filters);
            $reportTitle = 'Financial Summary by Program';
            break;

        case 'student_financial':
            $reportData = $financialGateway->getStudentFinancialDetails($filters);
            $reportTitle = 'Student Financial Details';
            break;

        case 'payment_history':
            $reportData = $financialGateway->getPaymentHistory($filters);
            $reportTitle = 'Payment History Report';
            break;

        case 'outstanding_balances':
            $reportData = $financialGateway->getStudentFinancialDetails($filters);
            $reportTitle = 'Outstanding Balances Summary';
            break;

        case 'revenue_analysis':
            $reportData = $financialGateway->getFinancialSummaryByProgram($filters);
            $reportTitle = 'Revenue Analysis by Program';
            break;

        default:
            $reportData = $financialGateway->getFinancialSummaryByProgram($filters);
            $reportTitle = 'Financial Summary by Program';
    }

    // Get available filter options
    $availablePrograms = $reportsGateway->getAvailablePrograms();
    $availableStatuses = $reportsGateway->getAvailableStatuses();
} catch (Exception $e) {
    $reportData = null;
    $reportTitle = 'Error Loading Report';
    $availablePrograms = [];
    $availableStatuses = [];
    error_log("Financial Reports Error - FULL STACK: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    // Set flash error message
    $_SESSION['cor4edu']['flash_errors'] = ['Error loading financial report data: ' . $e->getMessage()];
}

// Prepare template data
$templateData = [
    'title' => 'Financial Reports - COR4EDU SMS',
    'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $navigationPermissions]),
    'activeTab' => 'financial',
    'reportType' => $reportType,
    'reportTitle' => $reportTitle,
    'reportData' => $reportData,
    'selectedEnrollmentDateStart' => $enrollmentDateStart,
    'selectedEnrollmentDateEnd' => $enrollmentDateEnd,
    'selectedPrograms' => $programID,
    'selectedStatuses' => $status,
    'outstandingOnly' => $outstandingOnly,
    'availableReportTypes' => $availableReportTypes,
    'availablePrograms' => $availablePrograms,
    'availableStatuses' => $availableStatuses,
    'selectedReportType' => $reportType,

    // Filter form configuration
    'filterTitle' => 'Financial Report Filters',
    'currentUrl' => 'index.php',
    'currentQuery' => '/modules/Reports/reports_financial.php',
    'showReportTypeFilter' => true,
    'showProgramFilter' => true,
    'showStatusFilter' => ($reportType === 'student_financial'),
    'showDateFilter' => true,
    'showSpecialFilters' => true,
    'specialFilters' => $specialFilters,
    'showExportButtons' => true,
    'dateFilterLabel' => 'Student Enrollment Period',

    // Map enrollment date to startDate/endDate for filter form
    'selectedStartDate' => $enrollmentDateStart,
    'selectedEndDate' => $enrollmentDateEnd
];

// Render the template
echo $twig->render('reports/financial/financial.twig.html', $templateData);
