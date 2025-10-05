<?php

/**
 * Academic Information Update Process Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Initialize gateways - Gibbon style
$studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');

// Get form data
$studentID = $_POST['studentID'] ?? '';
$programID = $_POST['programID'] ?? '';
$enrollmentDate = $_POST['enrollmentDate'] ?? '';
$anticipatedGraduationDate = $_POST['anticipatedGraduationDate'] ?? '';
$actualGraduationDate = $_POST['actualGraduationDate'] ?? '';
$lastDayOfAttendance = $_POST['lastDayOfAttendance'] ?? '';
$withdrawnDate = $_POST['withdrawnDate'] ?? '';
$status = $_POST['status'] ?? '';

// Validation
$errors = [];

if (empty($studentID)) {
    $errors[] = 'Student ID is required.';
}

if (empty($programID)) {
    $errors[] = 'Program is required.';
}

if (empty($enrollmentDate)) {
    $errors[] = 'Enrollment start date is required.';
}

if (empty($status)) {
    $errors[] = 'Academic status is required.';
}

// Validate date formats
if (!empty($enrollmentDate) && !DateTime::createFromFormat('Y-m-d', $enrollmentDate)) {
    $errors[] = 'Invalid enrollment date format.';
}

if (!empty($anticipatedGraduationDate) && !DateTime::createFromFormat('Y-m-d', $anticipatedGraduationDate)) {
    $errors[] = 'Invalid anticipated graduation date format.';
}

if (!empty($actualGraduationDate) && !DateTime::createFromFormat('Y-m-d', $actualGraduationDate)) {
    $errors[] = 'Invalid actual graduation date format.';
}

if (!empty($lastDayOfAttendance) && !DateTime::createFromFormat('Y-m-d', $lastDayOfAttendance)) {
    $errors[] = 'Invalid last day of attendance format.';
}

if (!empty($withdrawnDate) && !DateTime::createFromFormat('Y-m-d', $withdrawnDate)) {
    $errors[] = 'Invalid withdrawn date format.';
}

// Validate date logic
if (!empty($enrollmentDate)) {
    $enrollmentDateTime = new DateTime($enrollmentDate);

    // Anticipated graduation date must be after enrollment
    if (!empty($anticipatedGraduationDate)) {
        $anticipatedGraduationDateTime = new DateTime($anticipatedGraduationDate);
        if ($anticipatedGraduationDateTime <= $enrollmentDateTime) {
            $errors[] = 'Anticipated graduation date must be after enrollment date.';
        }
    }

    // Actual graduation date must be after enrollment
    if (!empty($actualGraduationDate)) {
        $actualGraduationDateTime = new DateTime($actualGraduationDate);
        if ($actualGraduationDateTime <= $enrollmentDateTime) {
            $errors[] = 'Actual graduation date must be after enrollment date.';
        }
    }

    // Last day of attendance cannot be before enrollment
    if (!empty($lastDayOfAttendance)) {
        $lastDayDateTime = new DateTime($lastDayOfAttendance);
        if ($lastDayDateTime < $enrollmentDateTime) {
            $errors[] = 'Last day of attendance cannot be before enrollment date.';
        }
    }

    // Withdrawn date cannot be before enrollment
    if (!empty($withdrawnDate)) {
        $withdrawnDateTime = new DateTime($withdrawnDate);
        if ($withdrawnDateTime < $enrollmentDateTime) {
            $errors[] = 'Withdrawn date cannot be before enrollment date.';
        }
    }
}

// Additional date logic validations
if (!empty($actualGraduationDate) && !empty($lastDayOfAttendance)) {
    $actualGraduationDateTime = new DateTime($actualGraduationDate);
    $lastDayDateTime = new DateTime($lastDayOfAttendance);

    if ($lastDayDateTime > $actualGraduationDateTime) {
        $errors[] = 'Last day of attendance cannot be after actual graduation date.';
    }
}

// Validate status values
$validStatuses = ['prospective', 'active', 'graduated', 'alumni', 'withdrawn'];
if (!in_array($status, $validStatuses)) {
    $errors[] = 'Invalid academic status.';
}

if (!empty($errors)) {
    // Store errors in session and redirect back
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=registrar');
    exit;
}

try {
    // Verify student exists
    $student = $studentGateway->getByID((int)$studentID);
    if (!$student) {
        throw new Exception('Student not found.');
    }

    // Prepare update data
    $updateData = [
        'programID' => (int)$programID,
        'enrollmentDate' => $enrollmentDate,
        'status' => $status
    ];

    // Handle anticipated graduation date - can be explicitly set to NULL
    if (!empty($anticipatedGraduationDate) && trim($anticipatedGraduationDate) !== '') {
        $updateData['anticipatedGraduationDate'] = $anticipatedGraduationDate;
    } else {
        $updateData['anticipatedGraduationDate'] = null;
    }

    // Handle actual graduation date - can be explicitly set to NULL
    if (!empty($actualGraduationDate) && trim($actualGraduationDate) !== '') {
        $updateData['actualGraduationDate'] = $actualGraduationDate;
    } else {
        $updateData['actualGraduationDate'] = null;
    }

    // Handle lastDayOfAttendance - can be explicitly set to NULL
    if (!empty($lastDayOfAttendance) && trim($lastDayOfAttendance) !== '') {
        $updateData['lastDayOfAttendance'] = $lastDayOfAttendance;
    } else {
        $updateData['lastDayOfAttendance'] = null;
    }

    // Handle withdrawn date - can be explicitly set to NULL
    if (!empty($withdrawnDate) && trim($withdrawnDate) !== '') {
        $updateData['withdrawnDate'] = $withdrawnDate;
    } else {
        $updateData['withdrawnDate'] = null;
    }

    // Update student record
    $success = $studentGateway->updateByID((int)$studentID, $updateData);

    if ($success) {
        $_SESSION['flash_success'] = 'Academic information updated successfully.';
        header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=registrar');
        exit;
    } else {
        throw new Exception('Failed to update academic information.');
    }
} catch (Exception $e) {
    // Store error in session and redirect back
    $_SESSION['flash_errors'] = ['Error updating academic information: ' . $e->getMessage()];
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=registrar');
    exit;
}
