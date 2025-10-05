<?php

/**
 * Student Edit Process Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Initialize gateways - Gibbon style
$studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');

// Get form data
$studentID = $_POST['studentID'] ?? '';
$firstName = trim($_POST['firstName'] ?? '');
$lastName = trim($_POST['lastName'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$dateOfBirth = $_POST['dateOfBirth'] ?? null;
$gender = $_POST['gender'] ?? 'Unspecified';
$status = $_POST['status'] ?? 'prospective';
$address = trim($_POST['address'] ?? '');
$city = trim($_POST['city'] ?? '');
$state = trim($_POST['state'] ?? '');
$zipCode = trim($_POST['zipCode'] ?? '');
$country = trim($_POST['country'] ?? 'USA');
$programID = $_POST['programID'] ?? null;
$notes = trim($_POST['notes'] ?? '');

// Validation
$errors = [];

if (empty($studentID)) {
    $errors[] = 'Student ID is required.';
}

if (empty($firstName)) {
    $errors[] = 'First name is required.';
}

if (empty($lastName)) {
    $errors[] = 'Last name is required.';
}

// Check if email already exists for another student
if (!empty($email)) {
    if ($studentGateway->emailExistsForOtherStudent($email, (int)$studentID)) {
        $errors[] = 'This email address is already registered to another student.';
    }
}

if (!empty($errors)) {
    // Get student data for form repopulation
    $student = $studentGateway->selectStudentWithProgram($studentID);
    if (!$student) {
        header('Location: index.php?q=/modules/Students/student_manage.php');
        exit;
    }

    // Get programs for dropdown
    $programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');
    $programs = $programGateway->getActiveProgramsForDropdown();

    // Get user permissions for navigation
    $reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

    // Return to form with errors
    echo $twig->render('students/edit.twig.html', [
        'title' => 'Edit ' . $student['firstName'] . ' ' . $student['lastName'] . ' - Student Details',
        'student' => $student,
        'programs' => $programs,
        'errors' => $errors,
        'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $reportPermissions])
    ]);
    exit;
}

try {
    // Prepare data for update
    $data = [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => $email ?: null,
        'phone' => $phone ?: null,
        'dateOfBirth' => $dateOfBirth ?: null,
        'gender' => $gender,
        'status' => $status,
        'address' => $address ?: null,
        'city' => $city ?: null,
        'state' => $state ?: null,
        'zipCode' => $zipCode ?: null,
        'country' => $country ?: null,
        'programID' => $programID ?: null,
        'notes' => $notes ?: null
    ];

    // Update student
    $affectedRows = $studentGateway->updateByID((int)$studentID, $data);
    $success = $affectedRows > 0;

    if ($success) {
        // Set success message
        $_SESSION['flash_success'] = 'Student updated successfully.';

        // Redirect to student view
        header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID);
        exit;
    } else {
        throw new Exception('Failed to update student.');
    }
} catch (Exception $e) {
    // Get student data for form repopulation
    $student = $studentGateway->selectStudentWithProgram($studentID);
    if (!$student) {
        header('Location: index.php?q=/modules/Students/student_manage.php');
        exit;
    }

    // Get programs for dropdown
    $programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');
    $programs = $programGateway->getActiveProgramsForDropdown();

    // Get user permissions for navigation
    $reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

    // Return to form with error
    echo $twig->render('students/edit.twig.html', [
        'title' => 'Edit ' . $student['firstName'] . ' ' . $student['lastName'] . ' - Student Details',
        'student' => $student,
        'programs' => $programs,
        'error' => 'Error updating student: ' . $e->getMessage(),
        'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $reportPermissions])
    ]);
}
