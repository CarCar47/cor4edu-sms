<?php
require_once __DIR__ . '/../../../bootstrap.php';

// Basic session check for direct access protection
// (Main security is handled by index.php routing with SuperAdmin bypass)
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

$staffID = $_SESSION['cor4edu']['staffID'];
$username = $_SESSION['cor4edu']['username'];

$staffGateway = getGateway('Cor4Edu\Domain\Staff\StaffGateway');
$currentStaff = $staffGateway->getStaffById($staffID);
$isSuper = ($currentStaff && $currentStaff['isSuperAdmin'] === 'Y');

$pdo = $container['db'];

// Initialize variables with default values
$staffList = [];
$systemPermissions = [];
$flashErrors = [];

try {
    $staffListQuery = "SELECT s.staffID, s.position, s.firstName, s.lastName, s.username,
                              s.isSuperAdmin, s.active
                       FROM cor4edu_staff s
                       WHERE s.active = 'Y'";

    if (!$isSuper) {
        $staffListQuery .= " AND s.isSuperAdmin = 'N'";
    }

    $staffListQuery .= " ORDER BY s.lastName, s.firstName";

    $staffList = $pdo->query($staffListQuery)->fetchAll(PDO::FETCH_ASSOC);

    $systemPermQuery = "SELECT * FROM cor4edu_system_permissions WHERE active = 'Y' ORDER BY category, displayOrder";
    $systemPermissions = $pdo->query($systemPermQuery)->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $flashErrors = ['Database error: Unable to load permissions data. ' . $e->getMessage()];
    // Set session flash errors for user feedback
    $_SESSION['cor4edu']['flash_errors'] = $flashErrors;
}

$sessionData = [
    'staffID' => $staffID,
    'username' => $username,
    'flash_success' => $_SESSION['cor4edu']['flash_success'] ?? null,
    'flash_errors' => $_SESSION['cor4edu']['flash_errors'] ?? [],
    'is_super_admin' => $isSuper
];

unset($_SESSION['cor4edu']['flash_success']);
unset($_SESSION['cor4edu']['flash_errors']);

$permissions = getUserPermissionsForNavigation($staffID);

$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../../resources/templates');
$twig = new \Twig\Environment($loader);

echo $twig->render('permissions_manage.twig.html', [
    'title' => 'Permission Management',
    'app_name' => 'COR4EDU SMS',
    'user' => array_merge($sessionData, ['permissions' => $permissions]),
    'staff_list' => $staffList,
    'system_permissions' => $systemPermissions,
    'grouped_permissions' => is_array($systemPermissions) && !empty($systemPermissions)
        ? array_reduce($systemPermissions, function($carry, $perm) {
            $carry[$perm['category']][] = $perm;
            return $carry;
        }, [])
        : [],
    'is_super_admin' => $isSuper
]);
?>