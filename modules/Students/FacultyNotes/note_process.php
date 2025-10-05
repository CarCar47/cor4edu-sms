<?php

/**
 * Faculty Note Process Module
 * Handles creation and updating of faculty notes
 */

// Initialize gateways - Gibbon style
$studentGateway = getGateway('Cor4Edu\\Domain\\Student\\StudentGateway');

// Get form data
$studentID = $_POST['studentID'] ?? '';
$noteID = $_POST['noteID'] ?? '';
$category = $_POST['category'] ?? '';
$content = trim($_POST['content'] ?? '');
$isPrivate = $_POST['isPrivate'] ?? 'N';

// Validation
$errors = [];

if (empty($studentID)) {
    $errors[] = 'Student ID is required.';
}

if (empty($category)) {
    $errors[] = 'Note category is required.';
}

if (!in_array($category, ['positive', 'concern', 'neutral', 'disciplinary'])) {
    $errors[] = 'Invalid note category.';
}

if (empty($content)) {
    $errors[] = 'Note content is required.';
}

if (strlen($content) > 5000) {
    $errors[] = 'Note content cannot exceed 5000 characters.';
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

    // Prepare note data
    $noteData = [
        'studentID' => (int)$studentID,
        'facultyID' => $_SESSION['cor4edu']['staffID'] ?? 1,
        'category' => $category,
        'content' => $content,
        'isPrivate' => $isPrivate,
        'createdBy' => $_SESSION['cor4edu']['staffID'] ?? 1
    ];

    if (!empty($noteID)) {
        // Update existing note
        $noteData['modifiedBy'] = $_SESSION['cor4edu']['staffID'] ?? 1;

        $sql = "UPDATE cor4edu_faculty_notes SET
                category = :category,
                content = :content,
                isPrivate = :isPrivate,
                modifiedBy = :modifiedBy,
                modifiedOn = NOW()
                WHERE noteID = :noteID AND studentID = :studentID AND facultyID = :facultyID";

        $stmt = $pdo->prepare($sql);
        $noteData['noteID'] = (int)$noteID;
        $success = $stmt->execute($noteData);
        $action = 'updated';
    } else {
        // Create new note
        $sql = "INSERT INTO cor4edu_faculty_notes
                (studentID, facultyID, category, content, isPrivate, createdBy)
                VALUES
                (:studentID, :facultyID, :category, :content, :isPrivate, :createdBy)";

        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($noteData);
        $action = 'created';
    }

    if ($success) {
        // Set success message based on category
        $categoryMessages = [
            'positive' => 'Positive faculty note',
            'concern' => 'Concern faculty note',
            'neutral' => 'Faculty note',
            'disciplinary' => 'Disciplinary faculty note'
        ];

        $categoryMessage = $categoryMessages[$category] ?? 'Faculty note';
        $_SESSION['flash_success'] = $categoryMessage . ' ' . $action . ' successfully.';

        // Redirect to academic tab
        header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=academics');
        exit;
    } else {
        throw new Exception('Failed to ' . $action . ' faculty note.');
    }
} catch (Exception $e) {
    $_SESSION['flash_errors'] = ['Error processing faculty note: ' . $e->getMessage()];
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=academics');
    exit;
}
