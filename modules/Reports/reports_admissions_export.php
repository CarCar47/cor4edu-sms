<?php

/**
 * COR4EDU SMS Admissions & Enrollment Reports CSV Export
 * Exports student enrollment list report
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
    http_response_code(403);
    die('Access Denied');
}

// Get filter parameters (same as reports_admissions.php)
$reportType = $_GET['reportType'] ?? 'student_list';
$programID = $_GET['programID'] ?? [];
$status = $_GET['status'] ?? [];
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';
$optionalFields = $_GET['optionalFields'] ?? [];

// Ensure arrays
if (!is_array($programID)) {
    $programID = $programID ? [$programID] : [];
}
if (!is_array($status)) {
    $status = $status ? [$status] : [];
}
if (!is_array($optionalFields)) {
    $optionalFields = $optionalFields ? [$optionalFields] : [];
}
$programID = array_filter($programID);
$status = array_filter($status);

// Default date range
if (empty($startDate) || empty($endDate)) {
    $endDate = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime('-12 months'));
}

try {
    $reportsGateway = getGateway('Cor4Edu\Reports\Domain\ReportsGateway');

    // Build filters array
    $filters = [];
    if (!empty($programID)) {
        $filters['programID'] = $programID;
    }
    if (!empty($status)) {
        $filters['status'] = $status;
    }
    if (!empty($startDate)) {
        $filters['startDate'] = $startDate;
    }
    if (!empty($endDate)) {
        $filters['endDate'] = $endDate;
    }

    // Get report data - student list report
    $reportData = $reportsGateway->getStudentDetailReport($filters, $optionalFields);

    // Set CSV headers
    $filename = 'student_enrollment_list_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // CSV Headers - Base fields
    $headers = [
        'Student ID',
        'First Name',
        'Last Name',
        'Email',
        'Phone',
        'Status',
        'Enrollment Date',
        'Anticipated Graduation Date',
        'Actual Graduation Date',
        'Program Name',
        'Program Code'
    ];

    // Add optional field headers
    if (in_array('address', $optionalFields)) {
        $headers[] = 'Address';
        $headers[] = 'City';
        $headers[] = 'State';
        $headers[] = 'Zip Code';
    }

    if (in_array('demographics', $optionalFields)) {
        $headers[] = 'Date of Birth';
        $headers[] = 'Gender';
    }

    if (in_array('financial', $optionalFields)) {
        $headers[] = 'Total Paid';
    }

    if (in_array('employment', $optionalFields)) {
        $headers[] = 'Employment Status';
        $headers[] = 'Employer Name';
        $headers[] = 'Employment Date';
    }

    fputcsv($output, $headers);

    // Add data rows
    foreach ($reportData as $row) {
        $csvRow = [
            $row['studentID'] ?? '',
            $row['firstName'] ?? '',
            $row['lastName'] ?? '',
            $row['email'] ?? '',
            $row['phone'] ?? '',
            $row['status'] ?? '',
            $row['enrollmentDate'] ?? '',
            $row['anticipatedGraduationDate'] ?? '',
            $row['actualGraduationDate'] ?? '',
            $row['programName'] ?? '',
            $row['programCode'] ?? ''
        ];

        // Add optional field data
        if (in_array('address', $optionalFields)) {
            $csvRow[] = $row['address'] ?? '';
            $csvRow[] = $row['city'] ?? '';
            $csvRow[] = $row['state'] ?? '';
            $csvRow[] = $row['zipCode'] ?? '';
        }

        if (in_array('demographics', $optionalFields)) {
            $csvRow[] = $row['dateOfBirth'] ?? '';
            $csvRow[] = $row['gender'] ?? '';
        }

        if (in_array('financial', $optionalFields)) {
            $csvRow[] = $row['totalPaid'] ?? '';
        }

        if (in_array('employment', $optionalFields)) {
            $csvRow[] = $row['employmentStatus'] ?? '';
            $csvRow[] = $row['employerName'] ?? '';
            $csvRow[] = $row['employmentDate'] ?? '';
        }

        fputcsv($output, $csvRow);
    }

    fclose($output);
    exit;
} catch (Exception $e) {
    error_log("Admissions Reports Export Error: " . $e->getMessage());
    http_response_code(500);
    die('Error generating export: ' . $e->getMessage());
}
