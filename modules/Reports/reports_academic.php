<?php

/**
 * COR4EDU SMS Academic Performance Reports
 * Faculty notes, at-risk students, engagement tracking, and intervention analytics
 */

// Check if logged in and has permission
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// Check permissions using new system
$navigationPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);
$hasAccess = (isset($navigationPermissions['view_reports_tab']) && $navigationPermissions['view_reports_tab']) &&
             (isset($navigationPermissions['generate_academic_reports']) && $navigationPermissions['generate_academic_reports']);

if (!$hasAccess) {
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Access Denied - Academic Performance Reports',
        'message' => 'You do not have permission to access academic performance reports.'
    ]);
    exit;
}

// Get filter parameters
$reportType = $_GET['reportType'] ?? 'notes_summary';
$programID = $_GET['programID'] ?? [];
$status = $_GET['status'] ?? [];
$category = $_GET['category'] ?? [];
$meetingType = $_GET['meetingType'] ?? [];
$facultyID = $_GET['facultyID'] ?? '';
$dateStart = $_GET['startDate'] ?? '';  // Using startDate from form
$dateEnd = $_GET['endDate'] ?? '';      // Using endDate from form

// Ensure arrays
if (!is_array($programID)) {
    $programID = $programID ? [$programID] : [];
}
if (!is_array($status)) {
    $status = $status ? [$status] : [];
}
if (!is_array($category)) {
    $category = $category ? [$category] : [];
}
if (!is_array($meetingType)) {
    $meetingType = $meetingType ? [$meetingType] : [];
}
$programID = array_filter($programID); // Remove empty values
$status = array_filter($status); // Remove empty values
$category = array_filter($category); // Remove empty values
$meetingType = array_filter($meetingType); // Remove empty values

// Default status (all statuses if none selected)
if (empty($status)) {
    $status = ['Prospective', 'Active', 'Graduated', 'Alumni', 'Withdrawn'];
}

// Default date range (last 12 months)
if (empty($dateStart) || empty($dateEnd)) {
    $dateEnd = date('Y-m-d');
    $dateStart = date('Y-m-d', strtotime('-12 months'));
}

// Available report types
$availableReportTypes = [
    ['value' => 'notes_summary', 'label' => 'Faculty Notes Summary by Program'],
    ['value' => 'at_risk_students', 'label' => 'At-Risk Students Report'],
    ['value' => 'student_engagement', 'label' => 'Student Engagement Details'],
    ['value' => 'intervention_log', 'label' => 'Intervention Activity Log'],
    ['value' => 'faculty_activity', 'label' => 'Faculty Activity Report']
];

// Clear any previous errors first
unset($_SESSION['cor4edu']['flash_errors']);

// Get data based on report type
try {
    $reportsGateway = getGateway('Cor4Edu\\Reports\\Domain\\ReportsGateway');
    $academicGateway = getGateway('Cor4Edu\\Reports\\Domain\\AcademicReportsGateway');

    // Build filters array
    $filters = [];
    if (!empty($programID)) {
        $filters['programID'] = $programID;
    }
    if (!empty($status)) {
        $filters['status'] = $status;
    }
    if (!empty($category)) {
        $filters['category'] = $category;
    }
    if (!empty($meetingType)) {
        $filters['meetingType'] = $meetingType;
    }
    if (!empty($facultyID)) {
        $filters['facultyID'] = $facultyID;
    }
    if (!empty($dateStart)) {
        $filters['dateStart'] = $dateStart;
    }
    if (!empty($dateEnd)) {
        $filters['dateEnd'] = $dateEnd;
    }

    $reportData = null;
    $reportTitle = '';

    switch ($reportType) {
        case 'notes_summary':
            $reportData = $academicGateway->getFacultyNotesSummaryByProgram($filters);
            $reportTitle = 'Faculty Notes Summary by Program';
            break;

        case 'at_risk_students':
            $reportData = $academicGateway->getAtRiskStudents($filters);
            $reportTitle = 'At-Risk Students Report';
            break;

        case 'student_engagement':
            $reportData = $academicGateway->getStudentEngagementDetails($filters);
            $reportTitle = 'Student Engagement Details';
            break;

        case 'intervention_log':
            $reportData = $academicGateway->getInterventionActivityLog($filters);
            $reportTitle = 'Intervention Activity Log';
            break;

        case 'faculty_activity':
            $reportData = $academicGateway->getFacultyActivityReport($filters);
            $reportTitle = 'Faculty Activity Report';
            break;

        default:
            $reportData = $academicGateway->getFacultyNotesSummaryByProgram($filters);
            $reportTitle = 'Faculty Notes Summary by Program';
    }

    // Get available filter options
    $availablePrograms = $reportsGateway->getAvailablePrograms();
    $availableStatuses = $reportsGateway->getAvailableStatuses();
    $availableCategories = $academicGateway->getAvailableNoteCategories();
    $availableMeetingTypes = $academicGateway->getAvailableMeetingTypes();
    $availableFaculty = $academicGateway->getAvailableFaculty();
} catch (Exception $e) {
    $reportData = null;
    $reportTitle = 'Error Loading Report';
    $availablePrograms = [];
    $availableStatuses = [];
    $availableCategories = [];
    $availableMeetingTypes = [];
    $availableFaculty = [];
    error_log("Academic Reports Error - FULL STACK: " . $e->getMessage() . "\n" . $e->getTraceAsString());

    // Set flash error message
    $_SESSION['cor4edu']['flash_errors'] = ['Error loading academic report data: ' . $e->getMessage()];
}

// Build export URL with all current filters
$exportParams = http_build_query([
    'reportType' => $reportType,
    'programID' => $programID,
    'status' => $status,
    'category' => $category,
    'meetingType' => $meetingType,
    'facultyID' => $facultyID,
    'dateStart' => $dateStart,
    'dateEnd' => $dateEnd
]);

// Prepare template data
$templateData = [
    'title' => 'Academic Performance Reports - COR4EDU SMS',
    'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $navigationPermissions]),
    'activeTab' => 'academic',
    'reportType' => $reportType,
    'reportTitle' => $reportTitle,
    'reportData' => $reportData,
    'selectedDateStart' => $dateStart,
    'selectedDateEnd' => $dateEnd,
    'selectedPrograms' => $programID,
    'selectedStatuses' => $status,
    'selectedCategories' => $category,
    'selectedMeetingTypes' => $meetingType,
    'selectedFacultyID' => $facultyID,
    'availableReportTypes' => $availableReportTypes,
    'availablePrograms' => $availablePrograms,
    'availableStatuses' => $availableStatuses,
    'availableCategories' => $availableCategories,
    'availableMeetingTypes' => $availableMeetingTypes,
    'availableFaculty' => $availableFaculty,
    'selectedReportType' => $reportType,

    // Filter form configuration
    'filterTitle' => 'Academic Performance Filters',
    'currentUrl' => 'index.php',
    'currentQuery' => '/modules/Reports/reports_academic.php',
    'showReportTypeFilter' => true,
    'showProgramFilter' => true,
    'showStatusFilter' => true,
    'showCategoryFilter' => true,
    'showMeetingTypeFilter' => true,
    'showFacultyFilter' => true,
    'showDateFilter' => true,
    'showExportButtons' => true,
    'dateFilterLabel' => 'Date Range',
    'exportUrl' => 'index.php?q=/modules/Reports/reports_academic_export.php',
    'exportParams' => $exportParams,

    // Additional special mappings for academic reports
    'selectedStartDate' => $dateStart,
    'selectedEndDate' => $dateEnd
];

// Render the template
echo $twig->render('reports/academic/academic.twig.html', $templateData);
