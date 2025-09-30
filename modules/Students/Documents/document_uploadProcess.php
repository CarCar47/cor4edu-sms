<?php
/**
 * Student Document Upload Process Module
 * Following Gibbon patterns exactly - handles file upload
 */

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Get form data
$studentID = $_POST['studentID'] ?? '';
$category = $_POST['category'] ?? '';
$subcategory = trim($_POST['subcategory'] ?? '');
$notes = trim($_POST['notes'] ?? '');

// Validate required fields
$errors = [];

if (empty($studentID)) {
    $errors[] = 'Student ID is required.';
}

if (empty($category)) {
    $errors[] = 'Document category is required.';
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
    header('Location: index.php?q=/modules/Students/Documents/document_upload.php&studentID=' . urlencode($studentID) . '&category=' . urlencode($category));
    exit;
}

try {
    // Initialize gateways
    $studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');
    $documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');

    // Verify student exists
    $student = $studentGateway->selectStudentWithProgram($studentID);
    if (!$student) {
        throw new Exception('Student not found.');
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

    // Check if filename already exists
    if ($documentGateway->fileNameExistsForEntity('student', (int)$studentID, $originalFileName)) {
        throw new Exception('A document with this filename already exists for this student.');
    }

    // Move uploaded file
    if (!move_uploaded_file($tempPath, $filePath)) {
        throw new Exception('Failed to save uploaded file.');
    }

    // Create document record
    $documentData = [
        'entityType' => 'student',
        'entityID' => (int)$studentID,
        'category' => $category,
        'subcategory' => $subcategory ?: null,
        'fileName' => $originalFileName,
        'filePath' => $relativeFilePath,
        'fileSize' => $fileSize,
        'mimeType' => $mimeType,
        'uploadedBy' => $_SESSION['cor4edu']['staffID'],
        'notes' => $notes ?: null
    ];

    $documentID = $documentGateway->createDocument($documentData);

    if ($documentID) {
        $_SESSION['flash_success'] = 'Document uploaded successfully!';
        header('Location: index.php?q=/modules/Students/Documents/document_upload.php&studentID=' . urlencode($studentID) . '&category=' . urlencode($category));
        exit;
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
    header('Location: index.php?q=/modules/Students/Documents/document_upload.php&studentID=' . urlencode($studentID) . '&category=' . urlencode($category));
    exit;
}