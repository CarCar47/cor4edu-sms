<?php

/**
 * Program Edit Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Get program ID
$programID = $_GET['programID'] ?? '';

if (empty($programID)) {
    header('Location: index.php?q=/modules/Programs/program_manage.php');
    exit;
}

// Initialize program gateway - Gibbon style
$programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');

// Get program data
$program = $programGateway->getProgramDetails((int)$programID);

if (!$program) {
    header('Location: index.php?q=/modules/Programs/program_manage.php');
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
echo $twig->render('programs/edit.twig.html', [
    'title' => 'Edit ' . $program['name'] . ' - Program Details',
    'program' => $program,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);
