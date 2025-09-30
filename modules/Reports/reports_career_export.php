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
$reportType = $_GET['reportType'] ?? 'verification_report';
$programID = $_GET['programID'] ?? [];
$employmentStatus = $_GET['employmentStatus'] ?? [];
$verificationStatus = $_GET['verificationStatus'] ?? '';
$graduationDateStart = $_GET['graduationDateStart'] ?? '';
$graduationDateEnd = $_GET['graduationDateEnd'] ?? '';

// Ensure arrays
if (!is_array($programID)) $programID = $programID ? [$programID] : [];
if (!is_array($employmentStatus)) $employmentStatus = $employmentStatus ? [$employmentStatus] : [];
$programID = array_filter($programID);
$employmentStatus = array_filter($employmentStatus);

// Default date range
if (empty($graduationDateStart) || empty($graduationDateEnd)) {
    $graduationDateEnd = date('Y-m-d');
    $graduationDateStart = date('Y-m-d', strtotime('-12 months'));
}

try {
    $careerReportsGateway = getGateway('Cor4Edu\Reports\Domain\CareerReportsGateway');

    // Build filters array
    $filters = [];
    if (!empty($programID)) $filters['programID'] = $programID;
    if (!empty($employmentStatus)) $filters['employmentStatus'] = $employmentStatus;
    if (!empty($verificationStatus)) $filters['verificationStatus'] = $verificationStatus;
    if (!empty($graduationDateStart)) $filters['graduationDateStart'] = $graduationDateStart;
    if (!empty($graduationDateEnd)) $filters['graduationDateEnd'] = $graduationDateEnd;

    // Get report data
    $reportData = $careerReportsGateway->getJobPlacementVerificationReport($filters);

    // Set CSV headers
    $filename = 'job_placement_verification_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Headers
    $headers = [
        'Student First Name',
        'Student Last Name',
        'Student Code',
        'Student Phone',
        'Student Email',
        'Program Name',
        'Program Code',
        'Enrollment Date',
        'Graduation Date',
        'Employment Status',
        'Employment Date',
        'Job Title',
        'Employer Name',
        'Employer Address',
        'Employer Contact Name',
        'Employer Contact Phone',
        'Employer Contact Email',
        'Employment Type',
        'Entry Level',
        'Salary Range',
        'Exact Salary',
        'Verification Date',
        'Verification Source',
        'Verified By',
        'Verification Notes',
        'Requires License',
        'License Type',
        'License Obtained',
        'License Number',
        'Continuing Education Institution',
        'Continuing Education Program',
        'Comments'
    ];

    fputcsv($output, $headers);

    // Add data rows
    foreach ($reportData as $row) {
        $csvRow = [
            $row['studentFirstName'] ?? '',
            $row['studentLastName'] ?? '',
            $row['studentCode'] ?? '',
            $row['studentPhone'] ?? '',
            $row['studentEmail'] ?? '',
            $row['programName'] ?? '',
            $row['programCode'] ?? '',
            $row['enrollmentDate'] ?? '',
            $row['actualGraduationDate'] ?? '',
            $row['employmentStatus'] ?? '',
            $row['employmentDate'] ?? '',
            $row['jobTitle'] ?? '',
            $row['employerName'] ?? '',
            $row['employerAddress'] ?? '',
            $row['employerContactName'] ?? '',
            $row['employerContactPhone'] ?? '',
            $row['employerContactEmail'] ?? '',
            $row['employmentType'] ?? '',
            $row['isEntryLevel'] ?? '',
            $row['salaryRange'] ?? '',
            $row['salaryExact'] ?? '',
            $row['verificationDate'] ?? '',
            $row['verificationSource'] ?? '',
            ($row['verifiedByFirstName'] ?? '') . ' ' . ($row['verifiedByLastName'] ?? ''),
            $row['verificationNotes'] ?? '',
            $row['requiresLicense'] ?? '',
            $row['licenseType'] ?? '',
            $row['licenseObtained'] ?? '',
            $row['licenseNumber'] ?? '',
            $row['continuingEducationInstitution'] ?? '',
            $row['continuingEducationProgram'] ?? '',
            $row['comments'] ?? ''
        ];

        fputcsv($output, $csvRow);
    }

    fclose($output);
    exit;

} catch (Exception $e) {
    error_log("Career Reports Export Error: " . $e->getMessage());
    http_response_code(500);
    die('Error generating export: ' . $e->getMessage());
}
?>