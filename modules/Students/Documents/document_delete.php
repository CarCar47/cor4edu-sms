<?php

/**
 * COR4EDU SMS - Document Delete Process
 * Handles document deletion following Gibbon patterns
 */

// Validate user session and permissions
if (!isset($_SESSION['cor4edu']) || !isset($_SESSION['cor4edu']['staffID'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$staffID = $_SESSION['cor4edu']['staffID'];

// Check for document management permissions
$staffGateway = getGateway('Cor4Edu\\Domain\\Staff\\StaffGateway');
$hasDocumentAccess = $staffGateway->hasPermission($staffID, 'documents.delete');

if (!$hasDocumentAccess) {
    header('HTTP/1.1 403 Forbidden');
    exit('Insufficient permissions');
}

try {
    // Validate required POST data
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $documentID = filter_input(INPUT_POST, 'documentID', FILTER_VALIDATE_INT);
    $studentID = filter_input(INPUT_POST, 'studentID', FILTER_VALIDATE_INT);
    $category = filter_var($_POST['category'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validation
    $errors = [];

    if (!$documentID) {
        $errors[] = 'Invalid document ID';
    }

    if (!$studentID) {
        $errors[] = 'Invalid student ID';
    }

    if (empty($category)) {
        $errors[] = 'Category is required';
    }

    // If validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['flash_errors'] = $errors;
        header("Location: index.php?q=/modules/Students/Documents/document_upload.php&studentID=$studentID&category=$category");
        exit;
    }

    // Initialize gateways
    $documentGateway = getGateway('Cor4Edu\\Domain\\Document\\DocumentGateway');
    $studentGateway = getGateway('Cor4Edu\\Domain\\Student\\StudentGateway');

    // Verify student exists
    $student = $studentGateway->selectStudentWithProgram($studentID);
    if (!$student) {
        $_SESSION['flash_errors'] = ['Student not found'];
        header("Location: index.php?q=/modules/Students/Documents/document_upload.php&studentID=$studentID&category=$category");
        exit;
    }

    // Get document details to verify it exists and belongs to this student
    $document = $documentGateway->getDocumentDetails($documentID);

    if (!$document) {
        $_SESSION['flash_errors'] = ['Document not found'];
        header("Location: index.php?q=/modules/Students/Documents/document_upload.php&studentID=$studentID&category=$category");
        exit;
    }

    // Verify document belongs to this student
    if ($document['entityType'] !== 'student' || $document['entityID'] != $studentID) {
        $_SESSION['flash_errors'] = ['You do not have permission to delete this document'];
        header("Location: index.php?q=/modules/Students/Documents/document_upload.php&studentID=$studentID&category=$category");
        exit;
    }

    // Archive the document (soft delete following Gibbon pattern)
    $success = $documentGateway->archiveDocument($documentID, $staffID);

    if ($success) {
        // Optional: Delete physical file from disk
        $filePath = __DIR__ . '/../../../' . $document['filePath'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }

        $_SESSION['flash_success'] = 'Document deleted successfully';
        header("Location: index.php?q=/modules/Students/Documents/document_upload.php&studentID=$studentID&category=$category");
        exit;
    } else {
        $_SESSION['flash_errors'] = ['Failed to delete document'];
        header("Location: index.php?q=/modules/Students/Documents/document_upload.php&studentID=$studentID&category=$category");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['flash_errors'] = ['An error occurred: ' . $e->getMessage()];
    $studentID = $studentID ?? '';
    $category = $category ?? '';
    header("Location: index.php?q=/modules/Students/Documents/document_upload.php&studentID=$studentID&category=$category");
    exit;
}
