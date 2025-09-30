<?php
/**
 * Program Add Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Initialize program gateway - Gibbon style
$programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');

// Get session messages and form data
$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;
$formData = $_SESSION['program_form_data'] ?? [];

// Clear session messages and form data
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);
unset($_SESSION['program_form_data']);

// Get user permissions for navigation
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Render the template
echo $twig->render('programs/create.twig.html', [
    'title' => 'Add Program - COR4EDU SMS',
    'formData' => $formData,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);