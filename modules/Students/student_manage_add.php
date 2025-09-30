<?php
/**
 * Student Add Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Initialize program gateway - Gibbon style
$programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');
$programs = $programGateway->getActiveProgramsForDropdown();

// Get user permissions for navigation
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);
$sessionData = $_SESSION['cor4edu'];

// Render the template
echo $twig->render('students/create.twig.html', [
    'title' => 'Create Student - COR4EDU SMS',
    'programs' => $programs,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);