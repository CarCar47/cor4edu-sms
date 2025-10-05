<?php

/**
 * Admin Document History Management Module
 * Following Gibbon patterns exactly - admin-only document cleanup
 */

// Check if logged in and is super admin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    header('Location: index.php?q=/login');
    exit;
}

// Initialize gateways
$documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');

// Load user permissions for navigation consistency
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Get filters from query parameters
$filters = [
    'entityType' => $_GET['entityType'] ?? '',
    'category' => $_GET['category'] ?? '',
    'isArchived' => $_GET['isArchived'] ?? '',
    'olderThan' => $_GET['olderThan'] ?? ''
];

// Get documents for admin management
$documents = $documentGateway->getDocumentsForAdmin(array_filter($filters));

// Get storage statistics
$storageStats = $documentGateway->getStorageStatistics();

// Get documents by category
$categoryStats = $documentGateway->getDocumentsByCategory();

// Get available categories
$categories = $documentGateway->getStudentDocumentCategories();

// Get session messages
$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Render the template
echo $twig->render('admin/documents/history_manage.twig.html', [
    'title' => 'Document History Management - Admin',
    'documents' => $documents,
    'storageStats' => $storageStats,
    'categoryStats' => $categoryStats,
    'categories' => $categories,
    'filters' => $filters,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions]),
    'success' => $sessionData['flash_success'],
    'errors' => $sessionData['flash_errors']
]);
