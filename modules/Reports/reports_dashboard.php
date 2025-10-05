<?php

/**
 * COR4EDU SMS Reports Dashboard
 * Main entry point for the comprehensive reports system
 */

// Check if logged in and has permission
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// Check if user has access to reports
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

$hasReportsAccess = isset($reportPermissions['view_reports_tab']);

if (!$hasReportsAccess) {
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Access Denied - Reports',
        'message' => 'You do not have permission to access the reports section.'
    ]);
    exit;
}

// Get overview data for dashboard
try {
    $reportsGateway = getGateway('Cor4Edu\Reports\Domain\ReportsGateway');
    $overviewData = $reportsGateway->getInstitutionOverview();
} catch (Exception $e) {
    $overviewData = null;
    error_log("Reports Dashboard Error: " . $e->getMessage());
}

// Render the reports dashboard
echo $twig->render('reports/dashboard.twig.html', [
    'title' => 'Reports Dashboard - COR4EDU SMS',
    'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $reportPermissions]),
    'overviewData' => $overviewData,
    'activeTab' => 'overview'
]);
