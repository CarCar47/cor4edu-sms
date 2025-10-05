<?php

/**
 * Student Meeting Process Module
 * Handles creation and updating of student meetings
 */

// Initialize gateways - Gibbon style
$studentGateway = getGateway('Cor4Edu\\Domain\\Student\\StudentGateway');

// Get form data
$studentID = $_POST['studentID'] ?? '';
$meetingID = $_POST['meetingID'] ?? '';
$meetingDate = $_POST['meetingDate'] ?? '';
$meetingType = $_POST['meetingType'] ?? '';
$topicsDiscussed = trim($_POST['topicsDiscussed'] ?? '');
$currentPerformance = trim($_POST['currentPerformance'] ?? '');
$upcomingAssessments = trim($_POST['upcomingAssessments'] ?? '');
$actionItems = trim($_POST['actionItems'] ?? '');
$nextMeetingDate = $_POST['nextMeetingDate'] ?? null;
$parentNotified = $_POST['parentNotified'] ?? 'N';

// Validation
$errors = [];

if (empty($studentID)) {
    $errors[] = 'Student ID is required.';
}

if (empty($meetingDate)) {
    $errors[] = 'Meeting date is required.';
}

if (empty($meetingType)) {
    $errors[] = 'Meeting type is required.';
}

if (!in_array($meetingType, ['concerned', 'potential_failure', 'normal', 'disciplinary'])) {
    $errors[] = 'Invalid meeting type.';
}

if (empty($topicsDiscussed)) {
    $errors[] = 'Topics discussed is required.';
}

// Validate date format
if (!empty($meetingDate) && !DateTime::createFromFormat('Y-m-d', $meetingDate)) {
    $errors[] = 'Invalid meeting date format.';
}

if (!empty($nextMeetingDate) && !DateTime::createFromFormat('Y-m-d', $nextMeetingDate)) {
    $errors[] = 'Invalid next meeting date format.';
}

if (!empty($errors)) {
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=academics');
    exit;
}

try {
    // Get database connection
    global $container;
    $pdo = $container['db'];

    // Verify student exists
    $student = $studentGateway->getByID((int)$studentID);
    if (!$student) {
        throw new Exception('Student not found.');
    }

    // Prepare meeting data
    $meetingData = [
        'studentID' => (int)$studentID,
        'facultyID' => $_SESSION['cor4edu']['staffID'] ?? 1,
        'meetingDate' => $meetingDate,
        'meetingType' => $meetingType,
        'topicsDiscussed' => $topicsDiscussed,
        'currentPerformance' => $currentPerformance ?: null,
        'upcomingAssessments' => $upcomingAssessments ?: null,
        'actionItems' => $actionItems ?: null,
        'nextMeetingDate' => $nextMeetingDate ?: null,
        'parentNotified' => $parentNotified,
        'parentNotificationDate' => ($parentNotified === 'Y') ? date('Y-m-d') : null,
        'createdBy' => $_SESSION['cor4edu']['staffID'] ?? 1
    ];

    if (!empty($meetingID)) {
        // Update existing meeting
        $meetingData['modifiedBy'] = $_SESSION['cor4edu']['staffID'] ?? 1;

        $sql = "UPDATE cor4edu_student_meetings SET
                meetingDate = :meetingDate,
                meetingType = :meetingType,
                topicsDiscussed = :topicsDiscussed,
                currentPerformance = :currentPerformance,
                upcomingAssessments = :upcomingAssessments,
                actionItems = :actionItems,
                nextMeetingDate = :nextMeetingDate,
                parentNotified = :parentNotified,
                parentNotificationDate = :parentNotificationDate,
                modifiedBy = :modifiedBy,
                modifiedOn = NOW()
                WHERE meetingID = :meetingID AND studentID = :studentID";

        $stmt = $pdo->prepare($sql);
        $meetingData['meetingID'] = (int)$meetingID;
        $success = $stmt->execute($meetingData);
        $action = 'updated';
    } else {
        // Create new meeting
        $sql = "INSERT INTO cor4edu_student_meetings
                (studentID, facultyID, meetingDate, meetingType, topicsDiscussed,
                 currentPerformance, upcomingAssessments, actionItems, nextMeetingDate,
                 parentNotified, parentNotificationDate, createdBy)
                VALUES
                (:studentID, :facultyID, :meetingDate, :meetingType, :topicsDiscussed,
                 :currentPerformance, :upcomingAssessments, :actionItems, :nextMeetingDate,
                 :parentNotified, :parentNotificationDate, :createdBy)";

        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($meetingData);
        $action = 'scheduled';
    }

    if ($success) {
        // Set success message based on meeting type
        $typeMessages = [
            'concerned' => 'Concerned student meeting',
            'potential_failure' => 'Potential failure intervention meeting',
            'normal' => 'Regular check-in meeting',
            'disciplinary' => 'Disciplinary meeting'
        ];

        $typeMessage = $typeMessages[$meetingType] ?? 'Student meeting';
        $_SESSION['flash_success'] = $typeMessage . ' ' . $action . ' successfully for ' . $meetingDate . '.';

        // Redirect to academic tab
        header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=academics');
        exit;
    } else {
        throw new Exception('Failed to ' . $action . ' meeting.');
    }
} catch (Exception $e) {
    $_SESSION['flash_errors'] = ['Error processing meeting: ' . $e->getMessage()];
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=academics');
    exit;
}
