<?php

/**
 * Placement Record Process Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Initialize gateways - Gibbon style
$careerPlacementGateway = getGateway('Cor4Edu\Domain\CareerPlacement\CareerPlacementGateway');
$studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');

// Get form data
$studentID = $_POST['studentID'] ?? '';
$placementID = $_POST['placementID'] ?? '';
$employmentStatus = $_POST['employmentStatus'] ?? '';

// Employment details
$employmentDate = $_POST['employmentDate'] ?? null;
$jobTitle = trim($_POST['jobTitle'] ?? '');
$employerName = trim($_POST['employerName'] ?? '');
$employerAddress = trim($_POST['employerAddress'] ?? '');
$employerContactName = trim($_POST['employerContactName'] ?? '');
$employerContactPhone = trim($_POST['employerContactPhone'] ?? '');
$employerContactEmail = trim($_POST['employerContactEmail'] ?? '');
$employmentType = $_POST['employmentType'] ?? null;
$isEntryLevel = $_POST['isEntryLevel'] ?? null;
$salaryRange = $_POST['salaryRange'] ?? null;
$salaryExact = $_POST['salaryExact'] ?? null;

// Continuing education details
$continuingEducationInstitution = trim($_POST['continuingEducationInstitution'] ?? '');
$continuingEducationProgram = trim($_POST['continuingEducationProgram'] ?? '');

// Verification details
$verificationSource = $_POST['verificationSource'] ?? null;
$verificationDate = $_POST['verificationDate'] ?? null;
$verificationNotes = trim($_POST['verificationNotes'] ?? '');

// Licensure details
$requiresLicense = $_POST['requiresLicense'] ?? 'N';
$licenseType = trim($_POST['licenseType'] ?? '');
$licenseObtained = $_POST['licenseObtained'] ?? null;
$licenseNumber = trim($_POST['licenseNumber'] ?? '');

// General
$comments = trim($_POST['comments'] ?? '');

// Validation
$errors = [];

if (empty($studentID)) {
    $errors[] = 'Student ID is required.';
}

// Employment status is required and must not be empty string
if (empty($employmentStatus) || trim($employmentStatus) === '') {
    $errors[] = 'Employment status is required.';
} elseif (
    !in_array($employmentStatus, [
    'not_graduated', 'employed_related', 'employed_unrelated', 'self_employed_related',
    'self_employed_unrelated', 'not_employed_seeking', 'not_employed_not_seeking',
    'continuing_education'
    ])
) {
    $errors[] = 'Invalid employment status.';
}

// Validate employment details for employed statuses (make optional for simple status updates)
if (in_array($employmentStatus, ['employed_related', 'employed_unrelated', 'self_employed_related', 'self_employed_unrelated'])) {
    // Only require details if this is a full placement record (has placementID or detailed fields)
    $hasDetailedInfo = !empty($placementID) || !empty($employmentDate) || !empty($verificationSource);

    if ($hasDetailedInfo) {
        if (empty($jobTitle)) {
            $errors[] = 'Job title is required for detailed employment records.';
        }
        if (empty($employerName)) {
            $errors[] = 'Employer name is required for detailed employment records.';
        }
    }
}

// Validate continuing education details (make optional for simple status updates)
if ($employmentStatus === 'continuing_education') {
    // Only require details if this is a full placement record (has placementID or detailed fields)
    $hasDetailedInfo = !empty($placementID) || !empty($continuingEducationInstitution) || !empty($continuingEducationProgram) || !empty($verificationSource);

    if ($hasDetailedInfo) {
        if (empty($continuingEducationInstitution)) {
            $errors[] = 'Institution is required for detailed continuing education records.';
        }
        if (empty($continuingEducationProgram)) {
            $errors[] = 'Program is required for detailed continuing education records.';
        }
    }
}

// Validate licensure fields
if ($requiresLicense === 'Y') {
    if (empty($licenseType)) {
        $errors[] = 'License type is required when licensure is required.';
    }
    if ($licenseObtained === 'Y' && empty($licenseNumber)) {
        $errors[] = 'License number is required when license has been obtained.';
    }
}

// Validate date format
if (!empty($employmentDate) && !DateTime::createFromFormat('Y-m-d', $employmentDate)) {
    $errors[] = 'Invalid employment date format.';
}

if (!empty($verificationDate) && !DateTime::createFromFormat('Y-m-d', $verificationDate)) {
    $errors[] = 'Invalid verification date format.';
}

// Validate email format
if (!empty($employerContactEmail) && !filter_var($employerContactEmail, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid employer contact email format.';
}

// Validate salary exact is numeric
if (!empty($salaryExact) && !is_numeric($salaryExact)) {
    $errors[] = 'Exact salary must be a valid number.';
}

if (!empty($errors)) {
    // Store errors in session and redirect back (never render directly to avoid data loss)
    $_SESSION['flash_errors'] = $errors;
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=career');
    exit;
}

try {
    // Verify student exists
    $student = $studentGateway->getByID((int)$studentID);
    if (!$student) {
        throw new Exception('Student not found.');
    }

    // Prepare placement data
    $placementData = [
        'studentID' => (int)$studentID,
        'employmentStatus' => $employmentStatus,
        'employmentDate' => $employmentDate ?: null,
        'jobTitle' => $jobTitle ?: null,
        'employerName' => $employerName ?: null,
        'employerAddress' => $employerAddress ?: null,
        'employerContactName' => $employerContactName ?: null,
        'employerContactPhone' => $employerContactPhone ?: null,
        'employerContactEmail' => $employerContactEmail ?: null,
        'employmentType' => $employmentType,
        'isEntryLevel' => $isEntryLevel,
        'salaryRange' => $salaryRange,
        'salaryExact' => $salaryExact ? (float)$salaryExact : null,
        'continuingEducationInstitution' => $continuingEducationInstitution ?: null,
        'continuingEducationProgram' => $continuingEducationProgram ?: null,
        'verificationSource' => $verificationSource,
        'verificationDate' => $verificationDate ?: null,
        'verificationNotes' => $verificationNotes ?: null,
        'requiresLicense' => $requiresLicense,
        'licenseType' => $licenseType ?: null,
        'licenseObtained' => $licenseObtained,
        'licenseNumber' => $licenseNumber ?: null,
        'comments' => $comments ?: null,
        'createdBy' => $_SESSION['cor4edu']['staffID'] ?? 1
    ];

    // Set verification details if provided
    if (!empty($verificationDate) && !empty($verificationSource)) {
        $placementData['verifiedBy'] = $_SESSION['cor4edu']['staffID'] ?? 1;
    }

    // Create or update placement record
    if (!empty($placementID)) {
        // Update existing record
        $placementData['modifiedBy'] = $_SESSION['cor4edu']['staffID'] ?? 1;
        $success = $careerPlacementGateway->updatePlacementRecord((int)$placementID, $placementData);
        $action = 'updated';
    } else {
        // Create new record (this will automatically mark previous as not current)
        $newPlacementID = $careerPlacementGateway->createPlacementRecord($placementData);
        $success = $newPlacementID > 0;
        $action = 'created';
    }

    if ($success) {
        // Set success message based on action and status
        $statusMessages = [
            'not_graduated' => 'Employment status reset to None (Not Graduated)',
            'employed_related' => 'Employment record for related field position',
            'employed_unrelated' => 'Employment record for unrelated field position',
            'self_employed_related' => 'Self-employment record for related field',
            'self_employed_unrelated' => 'Self-employment record for unrelated field',
            'not_employed_seeking' => 'Job seeking status record',
            'not_employed_not_seeking' => 'Not seeking employment status record',
            'continuing_education' => 'Continuing education record'
        ];

        $statusMessage = $statusMessages[$employmentStatus] ?? 'Placement record';
        $_SESSION['flash_success'] = $statusMessage . ' ' . $action . ' successfully.';

        // Redirect to student view with career tab active
        header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=career');
        exit;
    } else {
        throw new Exception('Failed to ' . $action . ' placement record.');
    }
} catch (Exception $e) {
    // Store error in session and redirect back (never render directly to avoid data loss)
    $_SESSION['flash_errors'] = ['Error processing placement record: ' . $e->getMessage()];
    header('Location: index.php?q=/modules/Students/student_manage_view.php&studentID=' . $studentID . '&tab=career');
    exit;
}
