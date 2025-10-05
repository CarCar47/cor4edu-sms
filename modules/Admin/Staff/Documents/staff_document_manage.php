<?php

/**
 * Staff Document Management Module
 * Manage all documents for a staff member
 */

// Check if logged in and is admin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    header('Location: index.php?q=/login');
    exit;
}

// Get parameters
$staffID = $_GET['staffID'] ?? '';

if (empty($staffID)) {
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Initialize gateways
$documentGateway = getGateway('Cor4Edu\Domain\Document\DocumentGateway');

// Get staff details
global $container;
$pdo = $container['db'];

$stmt = $pdo->prepare("
    SELECT s.*, CONCAT(s.firstName, ' ', s.lastName) as fullName
    FROM cor4edu_staff s
    WHERE s.staffID = ?
");
$stmt->execute([$staffID]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    $_SESSION['flash_errors'] = ['Staff member not found'];
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Get all staff documents
$stmt = $pdo->prepare("
    SELECT d.*,
           CONCAT(uploader.firstName, ' ', uploader.lastName) as uploaderName
    FROM cor4edu_documents d
    LEFT JOIN cor4edu_staff uploader ON d.uploadedBy = uploader.staffID
    WHERE d.entityType = 'staff'
      AND d.entityID = ?
      AND d.isArchived = 'N'
    ORDER BY d.uploadedOn DESC
");
$stmt->execute([$staffID]);
$allDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get staff requirements for context
$staffRequirements = $documentGateway->getStaffRequirements((int)$staffID);

// Get staff document categories
$categories = $documentGateway->getStaffDocumentCategories();

// Create a lookup map for requirement titles
$requirementTitles = [];
foreach ($staffRequirements as $req) {
    $requirementTitles[$req['requirementCode']] = $req['title'];
}

// Group documents by requirement and other documents
$requiredDocuments = [];
$otherDocuments = [];

foreach ($allDocuments as $document) {
    if (!empty($document['linkedRequirementCode']) && isset($requirementTitles[$document['linkedRequirementCode']])) {
        $requiredDocuments[$document['linkedRequirementCode']][] = $document;
    } else {
        $otherDocuments[] = $document;
    }
}

// Get session messages
// Get user permissions for navigation
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Render the template
echo $twig->render('admin/staff/documents/manage.twig.html', [
    'title' => 'Manage Staff Documents - ' . $staff['fullName'],
    'staff' => $staff,
    'staffRequirements' => $staffRequirements,
    'requiredDocuments' => $requiredDocuments,
    'otherDocuments' => $otherDocuments,
    'requirementTitles' => $requirementTitles,
    'categories' => $categories,
    'documentCount' => count($allDocuments),
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);
