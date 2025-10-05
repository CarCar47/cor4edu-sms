<?php

/**
 * Student Document Requirement Upload Process Module
 * Following Gibbon patterns exactly - handles requirement file upload with dual-storage
 */

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Get form data
$studentID = $_POST['studentID'] ?? '';
$requirementCode = $_POST['requirementCode'] ?? '';
$notes = trim($_POST['notes'] ?? '');

// Validate required fields
$errors = [];

// CRITICAL SECURITY CHECK: Verify user has EDIT permission for this requirement
// Map requirement code to tab name for permission checking
$requirementTabMap = [
    'id_verification' => 'information',
    'enrollment_agreement' => 'admissions',
    'hs_diploma_transcripts' => 'admissions',
    'payment_plan_agreement' => 'bursar',
    'current_resume' => 'career',
    'school_degree' => 'graduation',
    'school_transcript' => 'graduation'
];

$tabName = $requirementTabMap[$requirementCode] ?? 'information';

// Check if user has EDIT permission for this tab
if (!$_SESSION['cor4edu']['is_super_admin'] && !canUserEditTab($_SESSION['cor4edu']['staffID'], $tabName)) {
    $errors[] = 'You do not have permission to upload documents in this section.';
}

if (empty($studentID)) {
    $errors[] = 'Student ID is required.';
}

if (empty($requirementCode)) {
    $errors[] = 'Requirement code is required.';
}

// Check if file was uploaded
if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    switch ($_FILES['document']['error'] ?? UPLOAD_ERR_NO_FILE) {
        case UPLOAD_ERR_NO_FILE:
            $errors[] = 'No file was uploaded.';
            break;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $errors[] = 'File size exceeds the maximum allowed size.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $errors[] = 'File was only partially uploaded.';
            break;
        default:
            $errors[] = 'File upload failed.';
            break;
    }
}

// If there are validation errors, redirect back
if (!empty($errors)) {
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php?q=/modules/Students/Requirements/requirement_upload.php&studentID=' . urlencode($studentID) . '&requirementCode=' . urlencode($requirementCode));
    exit;
}

try {
    // Initialize gateways
    $studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');
    $documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');
    $requirementGateway = getGateway('Cor4Edu\Domain\Document\DocumentRequirementGateway');

    // Verify student exists
    $student = $studentGateway->selectStudentWithProgram($studentID);
    if (!$student) {
        throw new Exception('Student not found.');
    }

    // Verify requirement exists
    $requirement = $requirementGateway->getRequirementByCode($requirementCode);
    if (!$requirement) {
        throw new Exception('Requirement not found.');
    }

    // Get file info
    $uploadedFile = $_FILES['document'];
    $originalFileName = $uploadedFile['name'];
    $fileSize = $uploadedFile['size'];
    $mimeType = $uploadedFile['type'];
    $tempPath = $uploadedFile['tmp_name'];

    // Validate file type and size
    $allowedMimeTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain'
    ];

    if (!in_array($mimeType, $allowedMimeTypes)) {
        throw new Exception('File type not allowed. Please upload PDF, Word, image, or text files only.');
    }

    // Max file size: 10MB
    $maxFileSize = 10 * 1024 * 1024;
    if ($fileSize > $maxFileSize) {
        throw new Exception('File size exceeds 10MB limit.');
    }

    // Generate unique filename to prevent conflicts
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $baseFileName = pathinfo($originalFileName, PATHINFO_FILENAME);
    $uniqueFileName = $baseFileName . '_' . time() . '_' . uniqid() . '.' . $fileExtension;

    // Create upload path
    $uploadDir = __DIR__ . '/../../../storage/uploads/students/' . $studentID . '/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory.');
        }
    }

    $filePath = $uploadDir . $uniqueFileName;
    $relativeFilePath = 'storage/uploads/students/' . $studentID . '/' . $uniqueFileName;

    // Move uploaded file
    if (!move_uploaded_file($tempPath, $filePath)) {
        throw new Exception('Failed to save uploaded file.');
    }

    // Create document record with requirement linking (dual-storage)
    $documentData = [
        'entityType' => 'student',
        'entityID' => (int)$studentID,
        'category' => $requirement['tabName'], // Use tab name as category for consistency
        'subcategory' => $requirement['displayName'],
        'fileName' => $originalFileName,
        'filePath' => $relativeFilePath,
        'fileSize' => $fileSize,
        'mimeType' => $mimeType,
        'uploadedBy' => $_SESSION['cor4edu']['staffID'],
        'notes' => $notes ?: null,
        'linkedRequirementCode' => $requirementCode // Link to requirement
    ];

    $documentID = $documentGateway->createDocument($documentData);

    if ($documentID) {
        // Update student requirement status
        $updateSuccess = $documentGateway->updateStudentRequirement(
            (int)$studentID,
            $requirementCode,
            $documentID,
            $_SESSION['cor4edu']['staffID']
        );

        if ($updateSuccess) {
            $_SESSION['flash_success'] = $requirement['displayName'] . ' uploaded successfully!';
            // Include tab parameter to preserve tab context
            $tabName = $requirement['tabName'];
            header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . urlencode($studentID) . '&tab=' . urlencode($tabName));
            exit;
        } else {
            // Clean up uploaded file if requirement update failed
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            throw new Exception('Failed to update requirement status.');
        }
    } else {
        // Clean up uploaded file if database insert failed
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        throw new Exception('Failed to save document record.');
    }
} catch (Exception $e) {
    // Clean up uploaded file on error
    if (isset($filePath) && file_exists($filePath)) {
        unlink($filePath);
    }

    $_SESSION['flash_errors'] = ['Error uploading document: ' . $e->getMessage()];
    header('Location: index.php?q=/modules/Students/Requirements/requirement_upload.php&studentID=' . urlencode($studentID) . '&requirementCode=' . urlencode($requirementCode));
    exit;
}
