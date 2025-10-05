<?php

/**
 * Admin Document Bulk Delete Process Module
 * Following Gibbon patterns exactly - bulk document cleanup with safety checks
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
$olderThan = $_POST['olderThan'] ?? '';
$onlyArchived = $_POST['onlyArchived'] ?? 'N';
$categories = $_POST['categories'] ?? [];

// Validate required fields
$errors = [];

if (empty($olderThan)) {
    $errors[] = 'Age threshold is required.';
}

// Validate age threshold format
$validThresholds = ['6 months', '1 year', '2 years', '5 years'];
if (!in_array($olderThan, $validThresholds)) {
    $errors[] = 'Invalid age threshold selected.';
}

// If there are validation errors, redirect back
if (!empty($errors)) {
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php?q=/modules/Admin/Documents/document_history_manage.php');
    exit;
}

try {
    // Initialize gateways
    $documentGateway = getGateway('Cor4Edu\\Domain\\Document\\DocumentGateway');

    // Verify admin permission (double-check)
    if (!$documentGateway->hasDocumentAdminPermission($_SESSION['cor4edu'])) {
        throw new Exception('Access denied. Super admin permissions required.');
    }

    // Convert age threshold to date
    $thresholdDate = '';
    switch ($olderThan) {
        case '6 months':
            $thresholdDate = date('Y-m-d', strtotime('-6 months'));
            break;
        case '1 year':
            $thresholdDate = date('Y-m-d', strtotime('-1 year'));
            break;
        case '2 years':
            $thresholdDate = date('Y-m-d', strtotime('-2 years'));
            break;
        case '5 years':
            $thresholdDate = date('Y-m-d', strtotime('-5 years'));
            break;
    }

    // Build filters for bulk deletion
    $filters = [
        'olderThan' => $thresholdDate
    ];

    // If only archived documents should be deleted
    if ($onlyArchived === 'Y') {
        $filters['isArchived'] = 'Y';
    }

    // Get documents that will be deleted for logging
    $documentsToDelete = $documentGateway->getDocumentsForAdmin($filters);

    // Filter by categories if specified
    if (!empty($categories)) {
        $documentsToDelete = array_filter($documentsToDelete, function ($doc) use ($categories) {
            return in_array($doc['category'], $categories);
        });
    }

    $totalDocuments = count($documentsToDelete);

    if ($totalDocuments === 0) {
        $_SESSION['flash_success'] = 'No documents found matching the specified criteria.';
        header('Location: index.php?q=/modules/Admin/Documents/document_history_manage.php');
        exit;
    }

    // Safety check - prevent accidental deletion of too many documents
    if ($totalDocuments > 1000) {
        throw new Exception('Bulk deletion limited to 1000 documents at a time for safety. Found ' . $totalDocuments . ' documents. Please use more restrictive filters.');
    }

    // Perform bulk deletion
    $deletedCount = 0;
    $failedCount = 0;
    $failedFiles = [];

    foreach ($documentsToDelete as $document) {
        if ($documentGateway->deleteDocumentPermanently((int)$document['documentID'])) {
            $deletedCount++;
        } else {
            $failedCount++;
            $failedFiles[] = $document['fileName'];
        }
    }

    // Prepare success message
    $message = "Bulk deletion completed: {$deletedCount} documents permanently deleted";

    if ($failedCount > 0) {
        $message .= ", {$failedCount} documents failed to delete";
        if (count($failedFiles) <= 5) {
            $message .= " (" . implode(', ', $failedFiles) . ")";
        }
    }

    $message .= ".";

    // Log the bulk deletion action for audit trail
    error_log("ADMIN BULK DELETE: User {$_SESSION['cor4edu']['username']} deleted {$deletedCount} documents older than {$olderThan}" .
              ($onlyArchived === 'Y' ? ' (archived only)' : '') .
              (!empty($categories) ? ' in categories: ' . implode(', ', $categories) : ''));

    $_SESSION['flash_success'] = $message;
    header('Location: index.php?q=/modules/Admin/Documents/document_history_manage.php');
    exit;
} catch (Exception $e) {
    $_SESSION['flash_errors'] = ['Error during bulk deletion: ' . $e->getMessage()];
    header('Location: index.php?q=/modules/Admin/Documents/document_history_manage.php');
    exit;
}
