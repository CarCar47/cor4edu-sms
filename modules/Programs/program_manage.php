<?php
/**
 * Program Management Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Security check - ensure user has permission to access programs
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// DEBUG OUTPUT - TEMPORARY
echo "<div style='background: #ff0; padding: 20px; border: 3px solid red; margin: 20px;'>";
echo "<h2>DEBUG SESSION INFO:</h2>";
echo "<pre>";
echo "SESSION DATA:\n";
print_r($_SESSION['cor4edu']);
echo "\n\nKEY VALUES:\n";
echo "is_super_admin isset: " . (isset($_SESSION['cor4edu']['is_super_admin']) ? 'YES' : 'NO') . "\n";
echo "is_super_admin value: " . (isset($_SESSION['cor4edu']['is_super_admin']) ? var_export($_SESSION['cor4edu']['is_super_admin'], true) : 'NOT SET') . "\n";
echo "is_super_admin type: " . (isset($_SESSION['cor4edu']['is_super_admin']) ? gettype($_SESSION['cor4edu']['is_super_admin']) : 'N/A') . "\n";
echo "</pre>";
echo "</div>";
// END DEBUG

// SuperAdmin bypass - always allow SuperAdmin access
if (!$_SESSION['cor4edu']['is_super_admin']) {
    // Check if user has permission to view programs
    if (!hasPermission($_SESSION['cor4edu']['staffID'], 'programs', 'view_programs')) {
        header('Location: index.php?q=/access_denied');
        exit;
    }
}

// Initialize program gateway - Gibbon style
$programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');

// Load user permissions for navigation consistency
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$active = $_GET['active'] ?? '';

// Get programs based on filters
if (!empty($search) || !empty($active)) {
    $programs = $programGateway->searchPrograms($search, $active);
} else {
    $programs = $programGateway->selectAllPrograms();
}

// Get status counts for filter buttons
$statusCounts = $programGateway->getStatusCounts();

// Get session messages
$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Render the template
echo $twig->render('programs/index.twig.html', [
    'title' => 'Programs - COR4EDU SMS',
    'programs' => $programs,
    'statusCounts' => $statusCounts,
    'currentSearch' => $search,
    'currentActive' => $active,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);