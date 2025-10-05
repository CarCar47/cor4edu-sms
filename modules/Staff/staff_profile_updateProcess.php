<?php

/**
 * Staff Profile Update Process Module
 * Handles staff profile information updates
 */

// Check if logged in
if (!isset($_SESSION['cor4edu'])) {
    header('Location: index.php?q=/login');
    exit;
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?q=/modules/Staff/staff_profile_view.php');
    exit;
}

// Initialize gateways
$staffProfileGateway = getGateway('Cor4Edu\Domain\Staff\StaffProfileGateway');

// Get current staff member's ID from session
$staffID = $_SESSION['cor4edu']['staffID'];

try {
    // Collect form data
    $profileData = [
        'firstName' => trim($_POST['firstName'] ?? ''),
        'lastName' => trim($_POST['lastName'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'address' => trim($_POST['address'] ?? ''),
        'city' => trim($_POST['city'] ?? ''),
        'state' => trim($_POST['state'] ?? ''),
        'zipCode' => trim($_POST['zipCode'] ?? ''),
        'country' => trim($_POST['country'] ?? ''),
        'dateOfBirth' => $_POST['dateOfBirth'] ?: null,
        'emergencyContact' => trim($_POST['emergencyContact'] ?? ''),
        'emergencyPhone' => trim($_POST['emergencyPhone'] ?? ''),
        'position' => trim($_POST['position'] ?? ''),
        'department' => trim($_POST['department'] ?? ''),
        'modifiedBy' => $staffID
    ];

    // Handle teaching programs (multiple select)
    $teachingPrograms = $_POST['teachingPrograms'] ?? [];
    $profileData['teachingPrograms'] = !empty($teachingPrograms) ? json_encode(array_map('intval', $teachingPrograms)) : null;

    // Notes field is not editable by staff themselves, preserve existing value
    $currentProfile = $staffProfileGateway->getStaffProfile($staffID);
    $profileData['notes'] = $currentProfile['notes'] ?? null;

    // Validation
    $errors = [];

    if (empty($profileData['firstName'])) {
        $errors[] = 'First name is required.';
    }

    if (empty($profileData['lastName'])) {
        $errors[] = 'Last name is required.';
    }

    if (!empty($profileData['email']) && !filter_var($profileData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (!empty($profileData['dateOfBirth'])) {
        $birthDate = DateTime::createFromFormat('Y-m-d', $profileData['dateOfBirth']);
        if (!$birthDate) {
            $errors[] = 'Please enter a valid date of birth.';
        } elseif ($birthDate > new DateTime()) {
            $errors[] = 'Date of birth cannot be in the future.';
        }
    }

    if (!empty($errors)) {
        $_SESSION['flash_errors'] = $errors;
        header('Location: index.php?q=/modules/Staff/staff_profile_view.php');
        exit;
    }

    // Update profile
    $success = $staffProfileGateway->updateStaffProfile($staffID, $profileData);

    if ($success) {
        $_SESSION['flash_success'] = 'Profile updated successfully.';

        // Update session data with new name if changed
        $_SESSION['cor4edu']['firstName'] = $profileData['firstName'];
        $_SESSION['cor4edu']['lastName'] = $profileData['lastName'];
        if (!empty($profileData['email'])) {
            $_SESSION['cor4edu']['email'] = $profileData['email'];
        }

        header('Location: index.php?q=/modules/Staff/staff_profile_view.php');
        exit;
    } else {
        throw new Exception('Failed to update profile information.');
    }
} catch (Exception $e) {
    $_SESSION['flash_errors'] = ['Error updating profile: ' . $e->getMessage()];
    header('Location: index.php?q=/modules/Staff/staff_profile_view.php');
    exit;
}
