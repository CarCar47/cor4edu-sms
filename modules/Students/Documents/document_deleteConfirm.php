<?php
/**
 * COR4EDU SMS - Document Delete Confirmation Page
 * Following Gibbon patterns for professional delete confirmation
 */

// Validate user session and permissions
if (!isset($_SESSION['cor4edu']) || !isset($_SESSION['cor4edu']['staffID'])) {
    header('Location: index.php?q=/login');
    exit;
}

$staffID = $_SESSION['cor4edu']['staffID'];

// Check for document management permissions
$staffGateway = getGateway('Cor4Edu\\Domain\\Staff\\StaffGateway');
$hasDocumentAccess = $staffGateway->hasPermission($staffID, 'documents.delete');

if (!$hasDocumentAccess) {
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Access Denied',
        'message' => 'You do not have permission to delete documents.',
        'user' => $_SESSION['cor4edu']
    ]);
    exit;
}

// Get parameters
$documentID = filter_input(INPUT_GET, 'documentID', FILTER_VALIDATE_INT);
$studentID = filter_input(INPUT_GET, 'studentID', FILTER_VALIDATE_INT);
$category = filter_var($_GET['category'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

if (!$documentID || !$studentID || empty($category)) {
    header('Location: index.php?q=/modules/Students/student_manage.php');
    exit;
}

// Initialize gateways
$documentGateway = getGateway('Cor4Edu\\Domain\\Document\\DocumentGateway');
$studentGateway = getGateway('Cor4Edu\\Domain\\Student\\StudentGateway');

// Get student details
$student = $studentGateway->selectStudentWithProgram($studentID);
if (!$student) {
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Student Not Found',
        'message' => 'The requested student could not be found.',
        'user' => $_SESSION['cor4edu']
    ]);
    exit;
}

// Get document details
$document = $documentGateway->getDocumentDetails($documentID);
if (!$document || $document['entityType'] !== 'student' || $document['entityID'] != $studentID) {
    echo $twig->render('errors/404.twig.html', [
        'title' => 'Document Not Found',
        'message' => 'The requested document could not be found.',
        'user' => $_SESSION['cor4edu']
    ]);
    exit;
}

// Get session messages
$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Get user permissions for navigation
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Render the template
echo $twig->render('students/documents/delete_confirm.twig.html', [
    'title' => 'Delete Document - ' . $student['firstName'] . ' ' . $student['lastName'],
    'student' => $student,
    'document' => $document,
    'category' => $category,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);