<?php

/**
 * Admin Staff Edit Module
 * Edit existing staff member information
 */

// Check if logged in and is admin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    header('Location: index.php?q=/login');
    exit;
}

// Initialize gateways
$staffProfileGateway = getGateway('Cor4Edu\Domain\Staff\StaffProfileGateway');

// Get staff ID from URL
$staffID = $_GET['staffID'] ?? '';

if (empty($staffID)) {
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Get staff details
global $container;
$pdo = $container['db'];

$stmt = $pdo->prepare("SELECT * FROM cor4edu_staff WHERE staffID = ?");
$stmt->execute([$staffID]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$staff) {
    $_SESSION['flash_errors'] = ['Staff member not found'];
    header('Location: index.php?q=/modules/Admin/Staff/staff_manage.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];

    // Get form data
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';
    $roleTypeID = $_POST['roleTypeID'] ?? '';
    $department = trim($_POST['department'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $active = $_POST['active'] ?? 'N';
    $isSuperAdmin = $_POST['isSuperAdmin'] ?? 'N';

    // Validation
    if (empty($firstName)) {
        $errors[] = 'First name is required';
    }
    if (empty($lastName)) {
        $errors[] = 'Last name is required';
    }
    if (empty($email)) {
        $errors[] = 'Email is required';
    }
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }

    // Password validation (only if changing)
    if (!empty($newPassword)) {
        if (strlen($newPassword) < 8) {
            $errors[] = 'Password must be at least 8 characters';
        }
        if ($newPassword !== $confirmPassword) {
            $errors[] = 'Passwords do not match';
        }
    }

    // Check for duplicate username/email (excluding current staff)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cor4edu_staff WHERE (username = ? OR email = ?) AND staffID != ?");
    $stmt->execute([$username, $email, $staffID]);
    if ($stmt->fetchColumn() > 0) {
        $errors[] = 'Username or email already exists';
    }

    if (empty($errors)) {
        try {
            // Prepare update query
            if (!empty($newPassword)) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE cor4edu_staff SET
                        firstName = ?, lastName = ?, email = ?, username = ?, passwordStrong = ?,
                        roleTypeID = ?, department = ?, position = ?, phone = ?, active = ?, isSuperAdmin = ?,
                        lastModifiedBy = ?, lastModifiedOn = NOW()
                    WHERE staffID = ?
                ");
                $stmt->execute([
                    $firstName, $lastName, $email, $username, $hashedPassword,
                    $roleTypeID ?: null, $department, $position, $phone, $active, $isSuperAdmin,
                    $_SESSION['cor4edu']['staffID'], $staffID
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE cor4edu_staff SET
                        firstName = ?, lastName = ?, email = ?, username = ?,
                        roleTypeID = ?, department = ?, position = ?, phone = ?, active = ?, isSuperAdmin = ?,
                        lastModifiedBy = ?, lastModifiedOn = NOW()
                    WHERE staffID = ?
                ");
                $stmt->execute([
                    $firstName, $lastName, $email, $username,
                    $roleTypeID ?: null, $department, $position, $phone, $active, $isSuperAdmin,
                    $_SESSION['cor4edu']['staffID'], $staffID
                ]);
            }

            $_SESSION['flash_success'] = 'Staff member updated successfully';
            header('Location: index.php?q=/modules/Admin/Staff/staff_manage_view.php&staffID=' . $staffID);
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
echo $twig->render('admin/staff/edit.twig.html', [
    'title' => 'Edit ' . $staff['firstName'] . ' ' . $staff['lastName'] . ' - COR4EDU SMS',
    'staff' => $staff,
    'roleTypes' => $roleTypes,
    'user' => array_merge($sessionData, ['permissions' => $reportPermissions])
]);
