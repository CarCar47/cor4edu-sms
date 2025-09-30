<?php
/**
 * Academic Support Session Process Module
 * Handles creation and updating of academic support sessions
 */

// Initialize gateways - Gibbon style
$studentGateway = getGateway('Cor4Edu\\Domain\\Student\\StudentGateway');

// Get form data
$studentID = $_POST['studentID'] ?? '';
$sessionID = $_POST['sessionID'] ?? '';
$sessionType = $_POST['sessionType'] ?? '';
$sessionDate = $_POST['sessionDate'] ?? '';
$duration = $_POST['duration'] ?? null;
$subject = trim($_POST['subject'] ?? '');
$description = trim($_POST['description'] ?? '');
$participants = trim($_POST['participants'] ?? '');
$followUpRequired = $_POST['followUpRequired'] ?? 'N';
$followUpDate = $_POST['followUpDate'] ?? null;
$followUpNotes = trim($_POST['followUpNotes'] ?? '');

// Validation
$errors = [];

if (empty($studentID)) {
    $errors[] = 'Student ID is required.';
}

if (empty($sessionType)) {
    $errors[] = 'Session type is required.';
}

if (!in_array($sessionType, ['tutoring', 'study_group', 'counseling', 'other'])) {
    $errors[] = 'Invalid session type.';
}

if (empty($sessionDate)) {
    $errors[] = 'Session date is required.';
}

if (empty($description)) {
    $errors[] = 'Session description is required.';
}

// Validate date format
if (!empty($sessionDate) && !DateTime::createFromFormat('Y-m-d', $sessionDate)) {
    $errors[] = 'Invalid session date format.';
}

if (!empty($followUpDate) && !DateTime::createFromFormat('Y-m-d', $followUpDate)) {
    $errors[] = 'Invalid follow-up date format.';
}

// Validate duration if provided
if (!empty($duration) && (!is_numeric($duration) || $duration < 1 || $duration > 480)) {
    $errors[] = 'Duration must be between 1 and 480 minutes.';
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

    // Prepare session data
    $sessionData = [
        'studentID' => (int)$studentID,
        'facultyID' => $_SESSION['cor4edu']['staffID'] ?? 1,
        'sessionType' => $sessionType,
        'sessionDate' => $sessionDate,
        'duration' => $duration ? (int)$duration : null,
        'subject' => $subject ?: null,
        'description' => $description,
        'participants' => $participants ?: null,
        'followUpRequired' => $followUpRequired,
        'followUpDate' => $followUpDate ?: null,
        'followUpNotes' => $followUpNotes ?: null,
        'createdBy' => $_SESSION['cor4edu']['staffID'] ?? 1
    ];

    if (!empty($sessionID)) {
        // Update existing session
        $sessionData['modifiedBy'] = $_SESSION['cor4edu']['staffID'] ?? 1;

        $sql = "UPDATE cor4edu_academic_support_sessions SET
                sessionType = :sessionType,
                sessionDate = :sessionDate,
                duration = :duration,
                subject = :subject,
                description = :description,
                participants = :participants,
                followUpRequired = :followUpRequired,
                followUpDate = :followUpDate,
                followUpNotes = :followUpNotes,
                modifiedBy = :modifiedBy,
                modifiedOn = NOW()
                WHERE sessionID = :sessionID AND studentID = :studentID";

        $stmt = $pdo->prepare($sql);
        $sessionData['sessionID'] = (int)$sessionID;
        $success = $stmt->execute($sessionData);
        $action = 'updated';
    } else {
        // Create new session
        $sql = "INSERT INTO cor4edu_academic_support_sessions
                (studentID, facultyID, sessionType, sessionDate, duration, subject,
                 description, participants, followUpRequired, followUpDate, followUpNotes, createdBy)
                VALUES
                (:studentID, :facultyID, :sessionType, :sessionDate, :duration, :subject,
                 :description, :participants, :followUpRequired, :followUpDate, :followUpNotes, :createdBy)";

        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($sessionData);
        $action = 'recorded';
    }

    if ($success) {
        // Set success message based on session type
        $typeMessages = [
            'tutoring' => 'Tutoring session',
            'study_group' => 'Study group session',
            'counseling' => 'Counseling session',
            'other' => 'Academic support session'
        ];

        $typeMessage = $typeMessages[$sessionType] ?? 'Academic support session';
        $_SESSION['flash_success'] = $typeMessage . ' ' . $action . ' successfully for ' . $sessionDate . '.';

        // Redirect to academic tab
        header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=academics');
        exit;
    } else {
        throw new Exception('Failed to ' . $action . ' support session.');
    }

} catch (Exception $e) {
    $_SESSION['flash_errors'] = ['Error processing support session: ' . $e->getMessage()];
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=academics');
    exit;
}