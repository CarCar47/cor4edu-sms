<?php

/**
 * Program Add Process Module
 * Following Gibbon patterns exactly - handles form submission
 */

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?q=/modules/Programs/program_manage_add.php');
    exit;
}

// Initialize gateways
$programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');

try {
    // Collect form data
    $measurementType = $_POST['measurementType'] ?? 'credits';
    $creditHours = !empty($_POST['creditHours']) ? (int) $_POST['creditHours'] : null;
    $contactHours = !empty($_POST['contactHours']) ? (int) $_POST['contactHours'] : null;

    // Pricing components
    $tuitionAmount = !empty($_POST['tuitionAmount']) ? (float) $_POST['tuitionAmount'] : 0.00;
    $fees = !empty($_POST['fees']) ? (float) $_POST['fees'] : 0.00;
    $booksAmount = !empty($_POST['booksAmount']) ? (float) $_POST['booksAmount'] : 0.00;
    $materialsAmount = !empty($_POST['materialsAmount']) ? (float) $_POST['materialsAmount'] : 0.00;
    $applicationFee = !empty($_POST['applicationFee']) ? (float) $_POST['applicationFee'] : 0.00;
    $miscellaneousCosts = !empty($_POST['miscellaneousCosts']) ? (float) $_POST['miscellaneousCosts'] : 0.00;
    $totalCost = $tuitionAmount + $fees + $booksAmount + $materialsAmount + $applicationFee + $miscellaneousCosts;

    $programData = [
        'name' => trim($_POST['name'] ?? ''),
        'programCode' => trim($_POST['programCode'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'duration' => trim($_POST['duration'] ?? ''),
        'measurementType' => $measurementType,
        'creditHours' => $creditHours,
        'contactHours' => $contactHours,
        'tuitionAmount' => $tuitionAmount,
        'fees' => $fees,
        'booksAmount' => $booksAmount,
        'materialsAmount' => $materialsAmount,
        'applicationFee' => $applicationFee,
        'miscellaneousCosts' => $miscellaneousCosts,
        'totalCost' => $totalCost,
        'active' => $_POST['active'] ?? 'Y',
        'createdBy' => $_SESSION['cor4edu']['staffID']
    ];

    // Validate required fields
    $errors = [];
    if (empty($programData['name'])) {
        $errors[] = 'Program name is required';
    }

    // Validate measurement fields based on type
    if ($measurementType === 'credits' && empty($creditHours)) {
        $errors[] = 'Credit hours are required for credit-based programs';
    }
    if ($measurementType === 'hours' && empty($contactHours)) {
        $errors[] = 'Contact hours are required for hour-based programs';
    }
    if ($measurementType === 'both') {
        if (empty($creditHours) && empty($contactHours)) {
            $errors[] = 'Either credit hours or contact hours (or both) are required';
        }
    }

    // Generate program code if not provided
    if (empty($programData['programCode'])) {
        $programData['programCode'] = $programGateway->getNextProgramCode();
    } else {
        // Check if program code already exists
        if ($programGateway->programCodeExistsForOtherProgram($programData['programCode'], 0)) {
            $errors[] = 'This program code already exists';
        }
    }

    if (!empty($errors)) {
        // Store errors and form data in session
        $_SESSION['flash_errors'] = $errors;
        $_SESSION['program_form_data'] = $_POST;

        // Redirect back to form
        header('Location: index.php?q=/modules/Programs/program_manage_add.php');
        exit;
    }

    // Create the program
    $programID = $programGateway->createProgram($programData);

    if ($programID) {
        // Create initial price history record
        $priceId = 'PRICE_' . $programID . '_INITIAL';

        $priceHistoryData = [
            'priceId' => $priceId,
            'programID' => $programID,
            'tuitionAmount' => $tuitionAmount,
            'fees' => $fees,
            'booksAmount' => $booksAmount,
            'materialsAmount' => $materialsAmount,
            'applicationFee' => $applicationFee,
            'miscellaneousCosts' => $miscellaneousCosts,
            'totalCost' => $totalCost,
            'effectiveDate' => date('Y-m-d'),
            'createdBy' => $_SESSION['cor4edu']['staffID'],
            'isActive' => true,
            'description' => 'Initial pricing for new program'
        ];

        $programGateway->createPriceHistory($priceHistoryData);

        // Update program with current price ID
        $programGateway->updateProgram($programID, ['currentPriceId' => $priceId]);

        // Success - redirect to program list with success message
        $_SESSION['flash_success'] = 'Program created successfully!';
        header('Location: index.php?q=/modules/Programs/program_manage.php');
        exit;
    } else {
        throw new Exception('Failed to create program');
    }
} catch (Exception $e) {
    // Error - store error and form data in session
    $_SESSION['flash_errors'] = ['Failed to create program: ' . $e->getMessage()];
    $_SESSION['program_form_data'] = $_POST;

    // Redirect back to form
    header('Location: index.php?q=/modules/Programs/program_manage_add.php');
    exit;
}
