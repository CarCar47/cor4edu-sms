<?php
/**
 * Admin Staff Add Module
 * Create new staff users with role assignments
 */

// Check if logged in and is admin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    header('Location: index.php?q=/login');
    exit;
}

// Initialize gateways
$staffProfileGateway = getGateway('Cor4Edu\Domain\Staff\StaffProfileGateway');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Get form data
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $roleTypeID = $_POST['roleTypeID'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $active = $_POST['active'] ?? 'Y';
    $isSuperAdmin = $_POST['isSuperAdmin'] ?? 'N';

    // Validation
    if (empty($firstName)) $errors[] = 'First name is required';
    if (empty($lastName)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($password)) $errors[] = 'Password is required';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';

    // Check for duplicate username/email
    global $container;
    $pdo = $container['db'];

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cor4edu_staff WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Username or email already exists';
    }

    if (empty($errors)) {
        try {
            // Create staff user
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Auto-generate staff code based on Super Admin status
            if ($isSuperAdmin === 'Y') {
                // Generate ADMIN### code for super admins
                $stmt = $pdo->prepare("SELECT staffCode FROM cor4edu_staff WHERE staffCode LIKE 'ADMIN%' ORDER BY staffCode DESC LIMIT 1");
                $stmt->execute();
                $lastCode = $stmt->fetchColumn();

                if ($lastCode) {
                    $number = (int)substr($lastCode, 5) + 1; // Extract number after "ADMIN"
                } else {
                    $number = 1;
                }
                $staffCode = 'ADMIN' . str_pad($number, 3, '0', STR_PAD_LEFT);
            } else {
                // Generate STAFF### code for regular staff
                $stmt = $pdo->prepare("SELECT staffCode FROM cor4edu_staff WHERE staffCode LIKE 'STAFF%' ORDER BY staffCode DESC LIMIT 1");
                $stmt->execute();
                $lastCode = $stmt->fetchColumn();

                if ($lastCode) {
                    $number = (int)substr($lastCode, 5) + 1; // Extract number after "STAFF"
                } else {
                    $number = 1;
                }
                $staffCode = 'STAFF' . str_pad($number, 3, '0', STR_PAD_LEFT);
            }

            $stmt = $pdo->prepare("
                INSERT INTO cor4edu_staff (
                    firstName, lastName, email, username, passwordStrong, staffCode,
                    roleTypeID, department, position, phone, active, isSuperAdmin,
                    createdBy, createdOn
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $firstName, $lastName, $email, $username, $hashedPassword, $staffCode,
                $roleTypeID ?: null, $department, $position, $phone, $active, $isSuperAdmin,
                $_SESSION['cor4edu']['staffID']
            ]);

            $_SESSION['flash_success'] = 'Staff member created successfully';
            header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
            exit;

        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    $_SESSION['flash_errors'] = $errors;
}

// Get role types for dropdown
$roleTypes = $staffProfileGateway->getAllRoleTypes();

// Get user permissions for navigation
$reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

// Get session messages
$sessionData = $_SESSION['cor4edu'];
$sessionData['flash_success'] = $_SESSION['flash_success'] ?? null;
$sessionData['flash_errors'] = $_SESSION['flash_errors'] ?? null;

// Clear session messages after capturing them
unset($_SESSION['flash_success']);
unset($_SESSION['flash_errors']);

// Render the template
echo $twig->render('admin/staff/add.twig.html', [
    'title' => 'Add Staff Member - COR4EDU SMS',
    'roleTypes' => $roleTypes,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);