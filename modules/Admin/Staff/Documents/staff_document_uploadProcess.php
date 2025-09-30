<?php
/**
 * Staff Document Upload Process Module
 * Handle staff document file upload
 */

// Check if logged in and is admin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    header('Location: index.php?q=/login');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Get form data
$staffID = $_POST['staffID'] ?? '';
$requirementCode = $_POST['requirementCode'] ?? '';
$category = $_POST['category'] ?? '';
$notes = trim($_POST['notes'] ?? '');

// Validate required fields
$errors = [];

if (empty($staffID)) {
    $errors[] = 'Staff ID is required.';
}

// Either requirementCode OR category must be provided
if (empty($requirementCode) && empty($category)) {
    $errors[] = 'Document requirement or category is required.';
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
    $redirectParams = 'staffID=' . urlencode($staffID);
    if (!empty($requirementCode)) {
        $redirectParams .= '&requirementCode=' . urlencode($requirementCode);
    }
    if (!empty($category)) {
        $redirectParams .= '&category=' . urlencode($category);
    }
    header('Location: index.php?q=/modules/Admin/Staff/Documents/staff_document_upload.php&' . $redirectParams);
    exit;
}

try {
    // Initialize gateways
    $documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');

    // Verify staff exists
    global $container;
    $pdo = $container['db'];

    $stmt = $pdo->prepare("SELECT staffID, firstName, lastName FROM cor4edu_staff WHERE staffID = ?");
    $stmt->execute([$staffID]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        throw new Exception('Staff member not found.');
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
    $uploadDir = __DIR__ . '/../../../../storage/uploads/staff/' . $staffID . '/';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            throw new Exception('Failed to create upload directory.');
        }
    }

    $filePath = $uploadDir . $uniqueFileName;
    $relativeFilePath = 'storage/uploads/staff/' . $staffID . '/' . $uniqueFileName;

    // Check if filename already exists
    if ($documentGateway->fileNameExistsForEntity('staff', (int)$staffID, $originalFileName)) {
        throw new Exception('A document with this filename already exists for this staff member.');
    }

    // Move uploaded file
    if (!move_uploaded_file($tempPath, $filePath)) {
        throw new Exception('Failed to save uploaded file.');
    }

    // Archive any existing documents for this requirement (only for requirement uploads)
    if (!empty($requirementCode)) {
        $stmt = $pdo->prepare("
            UPDATE cor4edu_documents
            SET isArchived = 'Y', archivedOn = NOW(), archivedBy = ?
            WHERE entityType = 'staff'
              AND entityID = ?
              AND linkedRequirementCode = ?
              AND isArchived = 'N'
        ");
        $stmt->execute([$_SESSION['cor4edu']['staffID'], (int)$staffID, $requirementCode]);
    }

    // Create document record
    $documentData = [
        'entityType' => 'staff',
        'entityID' => (int)$staffID,
        'category' => !empty($requirementCode) ? 'requirement' : $category,
        'subcategory' => null,
        'fileName' => $originalFileName,
        'filePath' => $relativeFilePath,
        'fileSize' => $fileSize,
        'mimeType' => $mimeType,
        'uploadedBy' => $_SESSION['cor4edu']['staffID'],
        'notes' => $notes ?: null,
        'linkedRequirementCode' => !empty($requirementCode) ? $requirementCode : null
    ];

    $documentID = $documentGateway->createDocument($documentData);

    if ($documentID) {
        $_SESSION['flash_success'] = 'Document uploaded successfully!';

        // Redirect back appropriately based on upload type
        if (!empty($requirementCode)) {
            // Requirement upload - redirect back to requirement upload page
            header('Location: index.php?q=/modules/Admin/Staff/Documents/staff_document_upload.php&staffID=' . urlencode($staffID) . '&requirementCode=' . urlencode($requirementCode));
        } elseif (!empty($category)) {
            // Category upload - redirect back to category upload page
            header('Location: index.php?q=/modules/Admin/Staff/Documents/staff_document_upload.php&staffID=' . urlencode($staffID) . '&category=' . urlencode($category));
        } else {
            // Default - redirect to staff view
            header('Location: index.php?q=/modules/Admin/Staff/staff_manage_view.php&staffID=' . urlencode($staffID));
        }
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
    $redirectParams = 'staffID=' . urlencode($staffID);
    if (!empty($requirementCode)) {
        $redirectParams .= '&requirementCode=' . urlencode($requirementCode);
    }
    if (!empty($category)) {
        $redirectParams .= '&category=' . urlencode($category);
    }
    header('Location: index.php?q=/modules/Admin/Staff/Documents/staff_document_upload.php&' . $redirectParams);
    exit;
}