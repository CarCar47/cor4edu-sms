<?php

/**
 * Student Add Process Module
 * Following Gibbon patterns exactly - handles form submission
 */

// Initialize gateways - Gibbon style

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?q=/modules/Students/student_manage_add.php');
    exit;
}

// Initialize gateways
$studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');
$programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');

try {
    // Collect form data
    $studentData = [
        'firstName' => $_POST['firstName'] ?? '',
        'lastName' => $_POST['lastName'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'dateOfBirth' => !empty($_POST['dateOfBirth']) ? $_POST['dateOfBirth'] : null,
        'gender' => $_POST['gender'] ?? 'Unspecified',
        'address' => $_POST['address'] ?? '',
        'city' => $_POST['city'] ?? '',
        'state' => $_POST['state'] ?? '',
        'zipCode' => $_POST['zipCode'] ?? '',
        'country' => $_POST['country'] ?? 'USA',
        'programID' => !empty($_POST['programID']) ? (int) $_POST['programID'] : null,
        'enrollmentDate' => null, // Set by admissions staff in Registrar tab when classes actually start
        'status' => 'prospective',
        'notes' => $_POST['notes'] ?? '',
        'createdBy' => $_SESSION['cor4edu']['staffID']
    ];

    // Validate required fields
    $errors = [];
    if (empty($studentData['firstName'])) {
        $errors[] = 'First name is required';
    }
    if (empty($studentData['lastName'])) {
        $errors[] = 'Last name is required';
    }

    if (!empty($errors)) {
        // Render form with errors
        $programs = $programGateway->getActiveProgramsForDropdown();

        // Get user permissions for navigation
        $reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

        echo $twig->render('students/create.twig.html', [
            'title' => 'Create Student - COR4EDU SMS',
            'programs' => $programs,
            'errors' => $errors,
            'formData' => $_POST,
            'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $reportPermissions])
        ]);
        exit;
    }

    // If student is enrolling in a program, lock in current pricing
    if (!empty($studentData['programID'])) {
        $program = $programGateway->getProgramDetails($studentData['programID']);
        if ($program && !empty($program['currentPriceId'])) {
            $studentData['enrollmentPriceId'] = $program['currentPriceId'];
            $studentData['contractLockedAt'] = date('Y-m-d H:i:s');
        }
    }

    // Create the student
    $studentID = $studentGateway->createStudent($studentData);

    if ($studentID) {
        // Success - redirect to student list with success message
        $_SESSION['flash_success'] = 'Student created successfully!';
        header('Location: index.php?q=/modules/Students/student_manage.php');
        exit;
    } else {
        throw new Exception('Failed to create student');
    }
} catch (Exception $e) {
    // Error - render form with error message
    $programs = $programGateway->getActiveProgramsForDropdown();

    // Get user permissions for navigation
    $reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

    echo $twig->render('students/create.twig.html', [
        'title' => 'Create Student - COR4EDU SMS',
        'programs' => $programs,
        'error' => 'Failed to create student: ' . $e->getMessage(),
        'formData' => $_POST,
        'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $reportPermissions])
    ]);
}
