<?php

/**
 * COR4EDU SMS - Payment Edit Process
 * Processes payment record updates from modal forms
 */

// Validate user session and permissions
if (!isset($_SESSION['cor4edu']) || !isset($_SESSION['cor4edu']['staffID'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$staffID = $_SESSION['cor4edu']['staffID'];

// Check for finance permissions
$staffGateway = getGateway('Cor4Edu\\Domain\\Staff\\StaffGateway');
$hasFinanceAccess = $staffGateway->hasPermission($staffID, 'payments.write');

if (!$hasFinanceAccess) {
    header('HTTP/1.1 403 Forbidden');
    exit('Insufficient permissions');
}

try {
    // Validate required POST data
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $paymentID = filter_input(INPUT_POST, 'paymentID', FILTER_VALIDATE_INT);
    $studentID = filter_input(INPUT_POST, 'studentID', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $paymentDate = filter_var($_POST['paymentDate'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $paymentMethod = filter_var($_POST['paymentMethod'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $referenceNumber = filter_var($_POST['referenceNumber'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $notes = filter_var($_POST['notes'] ?? '', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // Validation
    $errors = [];

    if (!$paymentID) {
        $errors[] = 'Invalid payment ID';
    }

    if (!$studentID) {
        $errors[] = 'Invalid student ID';
    }

    if (!$amount || $amount <= 0) {
        $errors[] = 'Payment amount must be greater than 0';
    }

    if (!$paymentDate) {
        $errors[] = 'Payment date is required';
    } else {
        $dateObj = DateTime::createFromFormat('Y-m-d', $paymentDate);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $paymentDate) {
            $errors[] = 'Invalid payment date format';
        } elseif ($dateObj > new DateTime()) {
            $errors[] = 'Payment date cannot be in the future';
        }
    }

    $validMethods = ['cash', 'check', 'credit_card', 'bank_transfer', 'money_order', 'other'];
    if (!in_array($paymentMethod, $validMethods)) {
        $errors[] = 'Invalid payment method';
    }

    // If validation errors, redirect back with errors
    if (!empty($errors)) {
        $_SESSION['flash_errors'] = $errors;
        header("Location: index.php?q=/modules/Students/student_manage_view.php&studentID=$studentID&tab=bursar");
        exit;
    }

    // Process the update
    $paymentGateway = getGateway('Cor4Edu\\Domain\\Payment\\PaymentGateway');

    // First verify payment exists and get current data
    $currentPayment = $paymentGateway->selectPaymentById($paymentID);

    if (!$currentPayment) {
        $_SESSION['flash_errors'] = ['Payment record not found'];
        header("Location: index.php?q=/modules/Students/student_manage_view.php&studentID=$studentID&tab=bursar");
        exit;
    }

    // Prepare update data matching actual database schema
    $updateData = [
        'amount' => $amount,
        'paymentDate' => $paymentDate,
        'paymentMethod' => $paymentMethod,
        'notes' => $referenceNumber ? "Reference: $referenceNumber. $notes" : $notes,
        'modifiedBy' => $staffID
    ];

    // Update charge allocation if amount changed and it's a program payment
    if ($currentPayment['paymentType'] === 'program' && $amount != $currentPayment['amount']) {
        $updateData['appliedToCharges'] = json_encode([
            [
                'chargeType' => 'tuition',
                'amount' => $amount,
                'description' => 'Updated program payment allocation'
            ]
        ]);
    }

    $success = $paymentGateway->updatePayment($paymentID, $updateData);

    if ($success) {
        $_SESSION['flash_success'] = 'Payment updated successfully';
        header("Location: index.php?q=/modules/Students/student_manage_view.php&studentID=$studentID&tab=bursar");
        exit;
    } else {
        $_SESSION['flash_errors'] = ['Failed to update payment'];
        header("Location: index.php?q=/modules/Students/student_manage_view.php&studentID=$studentID&tab=bursar");
        exit;
    }
} catch (Exception $e) {
    $_SESSION['flash_errors'] = ['An error occurred: ' . $e->getMessage()];
    header("Location: index.php?q=/modules/Students/student_manage_view.php&studentID=$studentID&tab=bursar");
    exit;
}
