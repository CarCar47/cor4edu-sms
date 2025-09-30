<?php
/**
 * Staff Document Upload Module
 * Upload documents for specific staff requirements
 */

// Check if logged in and is admin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    header('Location: index.php?q=/login');
    exit;
}

// Get parameters
$staffID = $_GET['staffID'] ?? '';
$requirementCode = $_GET['requirementCode'] ?? '';
$category = $_GET['category'] ?? '';

if (empty($staffID)) {
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Initialize gateways
$staffProfileGateway = getGateway('Cor4Edu\Domain\Staff\StaffProfileGateway');
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

// Get staff requirements
$staffRequirements = $documentGateway->getStaffRequirements((int)$staffID);

// If requirementCode is specified, find the specific requirement
$selectedRequirement = null;
if (!empty($requirementCode)) {
    foreach ($staffRequirements as $requirement) {
        if ($requirement['requirementCode'] === $requirementCode) {
            $selectedRequirement = $requirement;
            break;
        }
    }
}

// Get available categories and current category documents
$categories = $documentGateway->getStaffDocumentCategories();
$categoryDocuments = [];
if (!empty($category)) {
    $categoryDocuments = $documentGateway->getStaffDocumentsByCategory((int)$staffID, $category);
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
echo $twig->render('admin/staff/documents/upload.twig.html', [
    'title' => 'Upload Staff Documents - ' . $staff['fullName'],
    'staff' => $staff,
    'staffRequirements' => $staffRequirements,
    'selectedRequirement' => $selectedRequirement,
    'selectedRequirementCode' => $requirementCode,
    'categories' => $categories,
    'currentCategory' => $category,
    'categoryDocuments' => $categoryDocuments,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);