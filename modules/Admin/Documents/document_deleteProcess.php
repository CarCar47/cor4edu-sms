<?php
/**
 * Admin Document Permanent Delete Process Module
 * Following Gibbon patterns exactly - permanent document deletion with file cleanup
 */

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?q=/modules/Admin/Documents/document_history_manage.php');
    exit;
}

// Check if logged in and is super admin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    header('Location: index.php?q=/login');
    exit;
}

// Get form data
$documentID = $_POST['documentID'] ?? '';
$confirmDelete = $_POST['confirmDelete'] ?? '';

// Validate required fields
$errors = [];

if (empty($documentID)) {
    $errors[] = 'Document ID is required.';
}

if ($confirmDelete !== 'DELETE') {
    $errors[] = 'You must type "DELETE" to confirm permanent deletion.';
}

// If there are validation errors, redirect back
if (!empty($errors)) {
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php?q=/modules/Admin/Documents/document_history_manage.php');
    exit;
}

try {
    // Initialize gateways
    $documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');

    // Verify admin permission
    if (!$documentGateway->hasDocumentAdminPermission($_SESSION['cor4edu'])) {
        throw new Exception('Access denied. Admin permissions required.');
    }

    // Get document details for logging
    $document = $documentGateway->getDocumentDetails((int)$documentID);
    if (!$document) {
        throw new Exception('Document not found.');
    }

    // Permanently delete document and file
    $deleteSuccess = $documentGateway->deleteDocumentPermanently((int)$documentID);

    if ($deleteSuccess) {
        $_SESSION['flash_success'] = 'Document "' . $document['fileName'] . '" permanently deleted from system and storage.';
        header('Location: index.php?q=/modules/Admin/Documents/document_history_manage.php');
        exit;
    } else {
        throw new Exception('Failed to delete document from system.');
    }

} catch (Exception $e) {
    $_SESSION['flash_errors'] = ['Error deleting document: ' . $e->getMessage()];
    header('Location: index.php?q=/modules/Admin/Documents/document_history_manage.php');
    exit;
}