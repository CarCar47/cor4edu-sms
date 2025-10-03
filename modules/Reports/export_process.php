<?php
/**
 * COR4EDU SMS Report Export Process
 * Handles CSV export functionality with permission controls
 */

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Check if logged in and has permission
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// Check permissions
$staffGateway = getGateway('Cor4Edu\Domain\Staff\StaffGateway');
$userPermissions = $staffGateway->getStaffPermissionsDetailed($_SESSION['cor4edu']['staffID']);

$reportPermissions = [];
foreach ($userPermissions as $permission) {
    if (is_array($permission) &&
        isset($permission['module']) &&
        isset($permission['allowed']) &&
        isset($permission['action']) &&
        $permission['module'] === 'reports' &&
        $permission['allowed'] === 'Y') {
        $reportPermissions[$permission['action']] = true;
    }
}

// Check if user has general reports access
if (!isset($reportPermissions['view_reports_tab'])) {
    http_response_code(403);
    echo "Access denied";
    exit;
}

// Get export parameters
$reportType = $_GET['reportType'] ?? '';
$format = $_GET['format'] ?? 'csv';
$programID = $_GET['programID'] ?? [];
$status = $_GET['status'] ?? [];
$startDate = $_GET['startDate'] ?? '';
$endDate = $_GET['endDate'] ?? '';
$optionalFields = $_GET['optionalFields'] ?? [];

// Ensure arrays
if (!is_array($programID)) $programID = $programID ? [$programID] : [];
if (!is_array($status)) $status = $status ? [$status] : [];
if (!is_array($optionalFields)) $optionalFields = $optionalFields ? [$optionalFields] : [];

// Check format permissions
if ($format === 'excel' && !isset($reportPermissions['export_reports_excel'])) {
    http_response_code(403);
    echo "Excel export not permitted";
    exit;
}

if ($format === 'csv' && !isset($reportPermissions['export_reports_csv'])) {
    http_response_code(403);
    echo "CSV export not permitted";
    exit;
}

// Check report type permissions
$reportTypePermissions = [
    'overview' => 'generate_overview_reports',
    'admissions' => 'generate_admissions_reports',
    'financial' => 'generate_financial_reports',
    'career' => 'generate_career_reports',
    'academic' => 'generate_academic_reports'
];

$requiredPermission = $reportTypePermissions[$reportType] ?? '';
if ($requiredPermission && !isset($reportPermissions[$requiredPermission])) {
    http_response_code(403);
    echo "Report type not permitted";
    exit;
}

try {
    // Initialize gateways based on report type
    $reportsGateway = getGateway('Cor4Edu\Reports\Domain\ReportsGateway');
    $data = [];
    $filename = '';
    $sheetName = '';

    // Build filters
    $filters = [];
    if (!empty($programID)) $filters['programID'] = $programID;
    if (!empty($status)) $filters['status'] = $status;
    if (!empty($startDate)) $filters['startDate'] = $startDate;
    if (!empty($endDate)) $filters['endDate'] = $endDate;

    // Get data based on report type
    switch ($reportType) {
        case 'overview':
            if (strpos($_SERVER['HTTP_REFERER'], 'enrollment_summary') !== false) {
                $data = $reportsGateway->getProgramEnrollmentSummary($filters);
                $filename = 'program_enrollment_summary_' . date('Y-m-d');
                $sheetName = 'Program Enrollment';
            } else {
                $data = [$reportsGateway->getInstitutionOverview()];
                $filename = 'institution_overview_' . date('Y-m-d');
                $sheetName = 'Institution Overview';
            }
            break;

        case 'admissions':
            if (strpos($_SERVER['HTTP_REFERER'], 'student_list') !== false) {
                $data = $reportsGateway->getStudentDetailReport($filters, $optionalFields);
                $filename = 'student_enrollment_list_' . date('Y-m-d');
                $sheetName = 'Student List';
            } else {
                $data = $reportsGateway->getProgramEnrollmentSummary($filters);
                $filename = 'enrollment_summary_' . date('Y-m-d');
                $sheetName = 'Enrollment Summary';
            }
            break;

        case 'student_list':
            $data = $reportsGateway->getStudentDetailReport($filters, $optionalFields);
            $filename = 'student_enrollment_list_' . date('Y-m-d');
            $sheetName = 'Student List';
            break;

        // Financial report sub-types
        case 'financial':
        case 'financial_summary':
        case 'revenue_analysis':
            $financialGateway = getGateway('Cor4Edu\Reports\Domain\FinancialReportsGateway');
            $data = $financialGateway->getFinancialSummaryByProgram($filters);
            $filename = 'financial_summary_' . date('Y-m-d');
            $sheetName = 'Financial Summary';
            break;

        case 'student_financial':
            $financialGateway = getGateway('Cor4Edu\Reports\Domain\FinancialReportsGateway');
            $data = $financialGateway->getStudentFinancialDetails($filters);
            $filename = 'student_financial_details_' . date('Y-m-d');
            $sheetName = 'Student Financial';
            break;

        case 'payment_history':
            $financialGateway = getGateway('Cor4Edu\Reports\Domain\FinancialReportsGateway');
            $data = $financialGateway->getPaymentHistory($filters);
            $filename = 'payment_history_' . date('Y-m-d');
            $sheetName = 'Payment History';
            break;

        case 'outstanding_balances':
            $financialGateway = getGateway('Cor4Edu\Reports\Domain\FinancialReportsGateway');
            $data = $financialGateway->getOutstandingBalancesSummary($filters);
            $filename = 'outstanding_balances_' . date('Y-m-d');
            $sheetName = 'Outstanding Balances';
            break;

        // Career report sub-types
        case 'career':
        case 'placement_rate':
            $careerGateway = getGateway('Cor4Edu\Reports\Domain\CareerReportsGateway');
            $data = $careerGateway->getPlacementSummaryByProgram($filters);
            $filename = 'placement_rate_by_program_' . date('Y-m-d');
            $sheetName = 'Placement Rate';
            break;

        case 'verification_report':
            $careerGateway = getGateway('Cor4Edu\Reports\Domain\CareerReportsGateway');
            $data = $careerGateway->getJobPlacementVerificationReport($filters);
            $filename = 'job_placement_verification_' . date('Y-m-d');
            $sheetName = 'Verification Report';
            break;

        case 'outcomes_summary':
            $careerGateway = getGateway('Cor4Edu\Reports\Domain\CareerReportsGateway');
            $data = $careerGateway->getStudentCareerDetails($filters);
            $filename = 'employment_outcomes_summary_' . date('Y-m-d');
            $sheetName = 'Outcomes Summary';
            break;

        case 'unverified_placements':
            $careerGateway = getGateway('Cor4Edu\Reports\Domain\CareerReportsGateway');
            $data = $careerGateway->getUnverifiedPlacements($filters);
            $filename = 'unverified_placements_' . date('Y-m-d');
            $sheetName = 'Unverified Placements';
            break;

        // Academic report sub-types
        case 'academic':
        case 'notes_summary':
            $academicGateway = getGateway('Cor4Edu\Reports\Domain\AcademicReportsGateway');
            if (!empty($_GET['category'])) $filters['category'] = $_GET['category'];
            if (!empty($_GET['facultyID'])) $filters['facultyID'] = $_GET['facultyID'];
            $data = $academicGateway->getFacultyNotesSummaryByProgram($filters);
            $filename = 'faculty_notes_summary_' . date('Y-m-d');
            $sheetName = 'Faculty Notes Summary';
            break;

        case 'at_risk_students':
            $academicGateway = getGateway('Cor4Edu\Reports\Domain\AcademicReportsGateway');
            if (!empty($_GET['category'])) $filters['category'] = $_GET['category'];
            if (!empty($_GET['meetingType'])) $filters['meetingType'] = $_GET['meetingType'];
            $data = $academicGateway->getAtRiskStudents($filters);
            $filename = 'at_risk_students_' . date('Y-m-d');
            $sheetName = 'At-Risk Students';
            break;

        case 'student_engagement':
            $academicGateway = getGateway('Cor4Edu\Reports\Domain\AcademicReportsGateway');
            if (!empty($_GET['category'])) $filters['category'] = $_GET['category'];
            if (!empty($_GET['meetingType'])) $filters['meetingType'] = $_GET['meetingType'];
            $data = $academicGateway->getStudentEngagementDetails($filters);
            $filename = 'student_engagement_details_' . date('Y-m-d');
            $sheetName = 'Student Engagement';
            break;

        case 'intervention_log':
            $academicGateway = getGateway('Cor4Edu\Reports\Domain\AcademicReportsGateway');
            if (!empty($_GET['category'])) $filters['category'] = $_GET['category'];
            if (!empty($_GET['meetingType'])) $filters['meetingType'] = $_GET['meetingType'];
            if (!empty($_GET['facultyID'])) $filters['facultyID'] = $_GET['facultyID'];
            $data = $academicGateway->getInterventionActivityLog($filters);
            $filename = 'intervention_activity_log_' . date('Y-m-d');
            $sheetName = 'Intervention Log';
            break;

        case 'faculty_activity':
            $academicGateway = getGateway('Cor4Edu\Reports\Domain\AcademicReportsGateway');
            if (!empty($_GET['category'])) $filters['category'] = $_GET['category'];
            if (!empty($_GET['meetingType'])) $filters['meetingType'] = $_GET['meetingType'];
            if (!empty($_GET['facultyID'])) $filters['facultyID'] = $_GET['facultyID'];
            $data = $academicGateway->getFacultyActivityReport($filters);
            $filename = 'faculty_activity_report_' . date('Y-m-d');
            $sheetName = 'Faculty Activity';
            break;

        default:
            throw new Exception("Invalid report type: $reportType");
    }

    if (empty($data)) {
        throw new Exception("No data available for export");
    }

    // Export based on format
    if ($format === 'excel') {
        exportToExcel($data, $filename, $sheetName, $reportType, $userPermissions);
    } else {
        exportToCSV($data, $filename);
    }

} catch (Exception $e) {
    error_log("Export Error: " . $e->getMessage());
    http_response_code(500);
    echo "Export failed: " . $e->getMessage();
    exit;
}

/**
 * Export data to CSV format
 */
function exportToCSV($data, $filename) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');

    $output = fopen('php://output', 'w');

    if (!empty($data)) {
        // Write header row
        fputcsv($output, array_keys($data[0]));

        // Write data rows
        foreach ($data as $row) {
            // Flatten any nested arrays or objects
            $flatRow = [];
            foreach ($row as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $flatRow[$key] = json_encode($value);
                } else {
                    $flatRow[$key] = $value;
                }
            }
            fputcsv($output, $flatRow);
        }
    }

    fclose($output);
    exit;
}

/**
 * Export data to Excel format
 */
function exportToExcel($data, $filename, $sheetName, $reportType, $userPermissions) {
    // Check if PhpSpreadsheet is available
    if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
        throw new Exception("PhpSpreadsheet library not available");
    }

    $spreadsheet = new Spreadsheet();

    // Set document properties
    $spreadsheet->getProperties()
        ->setCreator($_SESSION['cor4edu']['firstName'] . ' ' . $_SESSION['cor4edu']['lastName'])
        ->setTitle($filename)
        ->setDescription('COR4EDU SMS Report Export - Generated by Claude Code SMS System')
        ->setKeywords('COR4EDU, SMS, Report, ' . ucfirst($reportType))
        ->setCategory('Reports');

    // Create main data sheet
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($sheetName);

    if (!empty($data)) {
        // Write headers
        $headers = array_keys($data[0]);
        $sheet->fromArray([$headers], null, 'A1');

        // Style headers
        $headerRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . '1';
        $sheet->getStyle($headerRange)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E5E7EB']
            ],
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '374151']
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'D1D5DB']
                ]
            ]
        ]);

        // Write data
        $rowIndex = 2;
        foreach ($data as $row) {
            $flatRow = [];
            foreach ($row as $value) {
                if (is_array($value) || is_object($value)) {
                    $flatRow[] = json_encode($value);
                } else {
                    $flatRow[] = $value;
                }
            }
            $sheet->fromArray([$flatRow], null, 'A' . $rowIndex);
            $rowIndex++;
        }

        // Auto-size columns
        foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add data borders
        $dataRange = 'A1:' . \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers)) . ($rowIndex - 1);
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => 'E5E7EB']
                ]
            ]
        ]);
    }

    // Add metadata sheet
    $metadataSheet = $spreadsheet->createSheet();
    $metadataSheet->setTitle('Report Metadata');

    $metadata = [
        ['Report Type', ucfirst($reportType) . ' Report'],
        ['Generated On', date('Y-m-d H:i:s')],
        ['Generated By', $_SESSION['cor4edu']['firstName'] . ' ' . $_SESSION['cor4edu']['lastName']],
        ['User ID', $_SESSION['cor4edu']['staffID']],
        ['Total Records', count($data)],
        ['Export Format', 'Excel (XLSX)'],
        ['System', 'COR4EDU SMS - Claude Code Implementation'],
        ['', ''],
        ['Filters Applied', ''],
    ];

    // Add filter information
    if (!empty($GLOBALS['programID'])) {
        $metadata[] = ['Programs', implode(', ', $GLOBALS['programID'])];
    }
    if (!empty($GLOBALS['status'])) {
        $metadata[] = ['Status', implode(', ', $GLOBALS['status'])];
    }
    if (!empty($GLOBALS['startDate'])) {
        $metadata[] = ['Start Date', $GLOBALS['startDate']];
    }
    if (!empty($GLOBALS['endDate'])) {
        $metadata[] = ['End Date', $GLOBALS['endDate']];
    }

    $metadataSheet->fromArray($metadata, null, 'A1');
    $metadataSheet->getColumnDimension('A')->setWidth(20);
    $metadataSheet->getColumnDimension('B')->setWidth(30);

    // Set headers for download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '.xlsx"');
    header('Cache-Control: max-age=0');

    // Write file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
?>