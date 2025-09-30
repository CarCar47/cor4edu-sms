<?php
/**
 * Program Edit Process Module
 * Following Gibbon patterns exactly - simple PHP file
 */

// Initialize gateways - Gibbon style
$programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');

// Get form data
$programID = $_POST['programID'] ?? '';
$name = trim($_POST['name'] ?? '');
$programCode = trim($_POST['programCode'] ?? '');
$description = trim($_POST['description'] ?? '');
$duration = trim($_POST['duration'] ?? '');
$measurementType = $_POST['measurementType'] ?? 'credits';
$creditHours = !empty($_POST['creditHours']) ? (int) $_POST['creditHours'] : null;
$contactHours = !empty($_POST['contactHours']) ? (int) $_POST['contactHours'] : null;
$active = $_POST['active'] ?? 'Y';

// Pricing components
$tuitionAmount = !empty($_POST['tuitionAmount']) ? (float) $_POST['tuitionAmount'] : 0.00;
$fees = !empty($_POST['fees']) ? (float) $_POST['fees'] : 0.00;
$booksAmount = !empty($_POST['booksAmount']) ? (float) $_POST['booksAmount'] : 0.00;
$materialsAmount = !empty($_POST['materialsAmount']) ? (float) $_POST['materialsAmount'] : 0.00;
$applicationFee = !empty($_POST['applicationFee']) ? (float) $_POST['applicationFee'] : 0.00;
$miscellaneousCosts = !empty($_POST['miscellaneousCosts']) ? (float) $_POST['miscellaneousCosts'] : 0.00;
$totalCost = $tuitionAmount + $fees + $booksAmount + $materialsAmount + $applicationFee + $miscellaneousCosts;

// Validation
$errors = [];

if (empty($programID)) {
    $errors[] = 'Program ID is required.';
}

if (empty($name)) {
    $errors[] = 'Program name is required.';
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

if (empty($programCode)) {
    $errors[] = 'Program code is required.';
}

// Check if program code already exists for another program
if (!empty($programCode)) {
    if ($programGateway->programCodeExistsForOtherProgram($programCode, (int)$programID)) {
        $errors[] = 'This program code is already used by another program.';
    }
}

if (!empty($errors)) {
    // Get program data for form repopulation
    $program = $programGateway->getProgramDetails($programID);
    if (!$program) {
        header('Location: index.php?q=/modules/Programs/program_manage.php');
        exit;
    }

    // Get user permissions for navigation
    $reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

    // Return to form with errors
    echo $twig->render('programs/edit.twig.html', [
        'title' => 'Edit ' . $program['name'] . ' - Program Details',
        'program' => $program,
        'errors' => $errors,
        'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $reportPermissions])
    ]);
    exit;
}

try {
    // Get current program data to check for pricing changes
    $currentProgram = $programGateway->getProgramDetails($programID);
    if (!$currentProgram) {
        throw new Exception('Program not found');
    }

    // Check if pricing has changed
    $pricingChanged = (
        $currentProgram['tuitionAmount'] != $tuitionAmount ||
        $currentProgram['fees'] != $fees ||
        $currentProgram['booksAmount'] != $booksAmount ||
        $currentProgram['materialsAmount'] != $materialsAmount ||
        $currentProgram['applicationFee'] != $applicationFee ||
        $currentProgram['miscellaneousCosts'] != $miscellaneousCosts
    );

    // Prepare data for update
    $data = [
        'name' => $name,
        'programCode' => $programCode,
        'description' => $description ?: null,
        'duration' => $duration ?: null,
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
        'active' => $active
    ];

    // If pricing changed, create new price history record
    if ($pricingChanged) {
        $pricingData = [
            'tuitionAmount' => $tuitionAmount,
            'fees' => $fees,
            'booksAmount' => $booksAmount,
            'materialsAmount' => $materialsAmount,
            'applicationFee' => $applicationFee,
            'miscellaneousCosts' => $miscellaneousCosts
        ];

        // Use existing updateProgramPricing method
        $priceId = $programGateway->updateProgramPricing($programID, $pricingData, $_SESSION['cor4edu']['staffID']);

        // Update current price ID
        $data['currentPriceId'] = $priceId;
    }

    // Update program
    $affectedRows = $programGateway->updateByID((int)$programID, $data);
    $success = $affectedRows > 0;

    if ($success) {
        // Set success message
        $_SESSION['flash_success'] = 'Program updated successfully.';

        // Redirect to program list
        header('Location: index.php?q=/modules/Programs/program_manage.php');
        exit;
    } else {
        throw new Exception('Failed to update program.');
    }

} catch (Exception $e) {
    // Get program data for form repopulation
    $program = $programGateway->getProgramDetails($programID);
    if (!$program) {
        header('Location: index.php?q=/modules/Programs/program_manage.php');
        exit;
    }

    // Get user permissions for navigation
    $reportPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

    // Return to form with error
    echo $twig->render('programs/edit.twig.html', [
        'title' => 'Edit ' . $program['name'] . ' - Program Details',
        'program' => $program,
        'error' => 'Error updating program: ' . $e->getMessage(),
        'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $reportPermissions])
    ]);
}