<?php

/**
 * COR4EDU SMS Career Services Reports CSV Export
 * Exports job placement verification report for state auditors
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
    http_response_code(403);
    die('Access Denied');
}

// Get filter parameters (same as reports_career.php)
$reportType = $_GET['reportType'] ?? 'placement_rate';
$programID = $_GET['programID'] ?? [];
$studentStatus = $_GET['studentStatus'] ?? [];
$employmentStatus = $_GET['employmentStatus'] ?? [];
$verificationStatus = $_GET['verificationStatus'] ?? '';
$graduationDateStart = $_GET['graduationDateStart'] ?? '';
$graduationDateEnd = $_GET['graduationDateEnd'] ?? '';

// Ensure arrays
if (!is_array($programID)) {
    $programID = $programID ? [$programID] : [];
}
if (!is_array($studentStatus)) {
    $studentStatus = $studentStatus ? [$studentStatus] : [];
}
if (!is_array($employmentStatus)) {
    $employmentStatus = $employmentStatus ? [$employmentStatus] : [];
}
$programID = array_filter($programID);
$studentStatus = array_filter($studentStatus);
// Don't filter employmentStatus - empty string '' is valid

// Default student status
if (empty($studentStatus)) {
    $studentStatus = ['Active', 'Graduated', 'Alumni'];
}

// Default date range
if (empty($graduationDateStart) || empty($graduationDateEnd)) {
    $graduationDateEnd = date('Y-m-d');
    $graduationDateStart = date('Y-m-d', strtotime('-12 months'));
}

try {
    $careerReportsGateway = getGateway('Cor4Edu\Reports\Domain\CareerReportsGateway');

    // Build filters array
    $filters = [];
    if (!empty($programID)) {
        $filters['programID'] = $programID;
    }
    if (!empty($studentStatus)) {
        $filters['studentStatus'] = $studentStatus;
    }
    if (!empty($employmentStatus)) {
        $filters['employmentStatus'] = $employmentStatus;
    }
    if (!empty($verificationStatus)) {
        $filters['verificationStatus'] = $verificationStatus;
    }
    if (!empty($graduationDateStart)) {
        $filters['graduationDateStart'] = $graduationDateStart;
    }
    if (!empty($graduationDateEnd)) {
        $filters['graduationDateEnd'] = $graduationDateEnd;
    }

    // Get report data based on report type
    $reportData = [];
    $filename = '';

    switch ($reportType) {
        case 'placement_rate':
            $reportData = $careerReportsGateway->getPlacementSummaryByProgram($filters);
            $filename = 'placement_rate_by_program_' . date('Y-m-d');
            break;

        case 'verification_report':
            $reportData = $careerReportsGateway->getJobPlacementVerificationReport($filters);
            $filename = 'job_placement_verification_' . date('Y-m-d');
            break;

        case 'outcomes_summary':
            $reportData = $careerReportsGateway->getStudentCareerDetails($filters);
            $filename = 'employment_outcomes_summary_' . date('Y-m-d');
            break;

        case 'unverified_placements':
            $reportData = $careerReportsGateway->getUnverifiedPlacements($filters);
            $filename = 'unverified_placements_' . date('Y-m-d');
            break;

        default:
            $reportData = $careerReportsGateway->getPlacementSummaryByProgram($filters);
            $filename = 'placement_rate_by_program_' . date('Y-m-d');
    }

    // Set CSV headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Dynamic CSV Headers based on report type
    if (empty($reportData)) {
        fputcsv($output, ['No data available for the selected filters']);
        fclose($output);
        exit;
    }

    // Use first row keys as headers
    $headers = array_keys($reportData[0]);
    fputcsv($output, $headers);

    // Add data rows
    foreach ($reportData as $row) {
        $csvRow = [];
        foreach ($headers as $header) {
            $csvRow[] = $row[$header] ?? '';
        }
        fputcsv($output, $csvRow);
    }

    fclose($output);
    exit;
} catch (Exception $e) {
    error_log("Career Reports Export Error: " . $e->getMessage());
    http_response_code(500);
    die('Error generating export: ' . $e->getMessage());
}
