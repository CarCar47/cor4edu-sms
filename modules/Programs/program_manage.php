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

// Get tab parameter (default to 'active')
$tab = $_GET['tab'] ?? 'active';

// Get search parameter
$search = $_GET['search'] ?? '';

// Get programs based on tab
if ($tab === 'inactive') {
    if (!empty($search)) {
        $programs = $programGateway->searchPrograms($search, 'N');
    } else {
        $programs = $programGateway->selectInactivePrograms();
    }
} else {
    // Active tab (default)
    if (!empty($search)) {
        $programs = $programGateway->searchPrograms($search, 'Y');
    } else {
        $programs = $programGateway->selectActivePrograms();
    }
}

// Get status counts for tabs
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
    'currentTab' => $tab,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);