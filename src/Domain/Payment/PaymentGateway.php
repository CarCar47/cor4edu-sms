<?php

namespace Cor4Edu\Domain\Payment;

use Cor4Edu\Domain\QueryableGateway;
use PDO;

/**
 * PaymentGateway following Gibbon patterns
 * Handles all payment data operations and financial calculations
 */
class PaymentGateway extends QueryableGateway
{
    protected static $tableName = 'cor4edu_payments';
    protected static $primaryKey = 'paymentID';
    protected static $searchableColumns = ['invoiceNumber', 'notes'];

    /**
     * Get payment methods enum values
     * @return array
     */
    public function getPaymentMethods(): array
    {
        return [
            'cash' => 'Cash',
            'check' => 'Check',
            'credit_card' => 'Credit Card',
            'ach' => 'ACH/Bank Transfer',
            'financial_aid' => 'Financial Aid'
        ];
    }

    /**
     * Get payment types enum values
     * @return array
     */
    public function getPaymentTypes(): array
    {
        return [
            'program' => 'Program Payment',
            'other' => 'Other Payment'
        ];
    }

    /**
     * Get all payments for a student
     * @param int $studentID
     * @param string|null $paymentType Filter by payment type ('program', 'other', or null for all)
     * @return array
     */
    public function selectPaymentsByStudent(int $studentID, ?string $paymentType = null): array
    {
        $sql = "SELECT p.*,
                       s.firstName as processedByFirstName,
                       s.lastName as processedByLastName
                FROM cor4edu_payments p
                LEFT JOIN cor4edu_staff s ON p.processedBy = s.staffID
                WHERE p.studentID = :studentID";

        $params = ['studentID' => $studentID];

        if ($paymentType !== null) {
            $sql .= " AND p.paymentType = :paymentType";
            $params['paymentType'] = $paymentType;
        }

        $sql .= " ORDER BY p.paymentDate DESC, p.createdOn DESC";

        return $this->select($sql, $params);
    }

    /**
     * Get payment by ID
     * @param int $paymentID
     * @return array|false
     */
    public function selectPaymentById(int $paymentID)
    {
        $sql = "SELECT p.*,
                       st.firstName as studentFirstName,
                       st.lastName as studentLastName,
                       s.firstName as processedByFirstName,
                       s.lastName as processedByLastName
                FROM cor4edu_payments p
                LEFT JOIN cor4edu_students st ON p.studentID = st.studentID
                LEFT JOIN cor4edu_staff s ON p.processedBy = s.staffID
                WHERE p.paymentID = :paymentID";

        return $this->selectOne($sql, ['paymentID' => $paymentID]);
    }

    /**
     * Insert new payment
     * @param array $data
     * @return int
     */
    public function insertPayment(array $data): int
    {
        // Set default values
        $data['status'] = $data['status'] ?? 'completed';
        $data['currency'] = $data['currency'] ?? 'USD';
        $data['paymentType'] = $data['paymentType'] ?? 'program';
        $data['createdOn'] = date('Y-m-d H:i:s');

        // Handle charge applications - ensure it's JSON
        if (isset($data['appliedToCharges']) && is_array($data['appliedToCharges'])) {
            $data['appliedToCharges'] = json_encode($data['appliedToCharges']);
        } elseif (!isset($data['appliedToCharges']) || empty($data['appliedToCharges'])) {
            // Default charge application
            $chargeApplications = [
                [
                    'chargeType' => 'tuition',
                    'amount' => $data['amount'],
                    'description' => 'Payment allocation'
                ]
            ];
            $data['appliedToCharges'] = json_encode($chargeApplications);
        }

        return $this->insertRecord($data);
    }

    /**
     * Update payment
     * @param int $paymentID
     * @param array $data
     * @return int
     */
    public function updatePayment(int $paymentID, array $data): int
    {
        // Handle charge applications - ensure it's JSON
        if (isset($data['appliedToCharges']) && is_array($data['appliedToCharges'])) {
            $data['appliedToCharges'] = json_encode($data['appliedToCharges']);
        }

        $data['modifiedOn'] = date('Y-m-d H:i:s');

        return $this->updateByID($paymentID, $data);
    }

    /**
     * Get payment summary for a student
     * @param int $studentID
     * @return array
     */
    public function getPaymentSummaryByStudent(int $studentID): array
    {
        $sql = "SELECT
                    paymentType,
                    SUM(amount) as totalAmount,
                    COUNT(*) as paymentCount
                FROM cor4edu_payments
                WHERE studentID = :studentID AND status = 'completed'
                GROUP BY paymentType";

        $results = $this->select($sql, ['studentID' => $studentID]);

        $summary = [
            'programPayments' => 0.00,
            'otherPayments' => 0.00,
            'totalPayments' => 0.00,
            'programPaymentCount' => 0,
            'otherPaymentCount' => 0,
            'totalPaymentCount' => 0
        ];

        foreach ($results as $result) {
            $amount = (float) $result['totalAmount'];
            $count = (int) $result['paymentCount'];

            if ($result['paymentType'] === 'program') {
                $summary['programPayments'] = $amount;
                $summary['programPaymentCount'] = $count;
            } elseif ($result['paymentType'] === 'other') {
                $summary['otherPayments'] = $amount;
                $summary['otherPaymentCount'] = $count;
            }

            $summary['totalPayments'] += $amount;
            $summary['totalPaymentCount'] += $count;
        }

        return $summary;
    }

    /**
     * Calculate outstanding balance for a student
     * @param int $studentID
     * @return float
     */
    public function calculateOutstandingBalance(int $studentID): float
    {
        // Get student's enrolled pricing (protected pricing)
        $studentSql = "SELECT s.enrollmentPriceId, s.programID
                       FROM cor4edu_students s
                       WHERE s.studentID = :studentID";

        $student = $this->selectOne($studentSql, ['studentID' => $studentID]);

        if (!$student) {
            return 0.00;
        }

        $totalProgramCost = 0.00;

        // Try to get student's locked-in pricing first
        if (!empty($student['enrollmentPriceId'])) {
            $priceSql = "SELECT totalCost
                         FROM cor4edu_program_price_history
                         WHERE priceId = :priceId";

            $priceResult = $this->selectOne($priceSql, ['priceId' => $student['enrollmentPriceId']]);

            if ($priceResult) {
                $totalProgramCost = (float) $priceResult['totalCost'];
            }
        }

        // Fallback to current program pricing if no locked pricing
        if ($totalProgramCost === 0.00 && !empty($student['programID'])) {
            $programSql = "SELECT totalCost
                           FROM cor4edu_programs
                           WHERE programID = :programID";

            $programResult = $this->selectOne($programSql, ['programID' => $student['programID']]);

            if ($programResult) {
                $totalProgramCost = (float) $programResult['totalCost'];
            }
        }

        // Get total program payments (excluding 'other' payments)
        $paymentSql = "SELECT SUM(amount) as totalPaid
                       FROM cor4edu_payments
                       WHERE studentID = :studentID
                       AND paymentType = 'program'
                       AND status = 'completed'";

        $paymentResult = $this->selectOne($paymentSql, ['studentID' => $studentID]);
        $totalProgramPaid = $paymentResult ? (float) $paymentResult['totalPaid'] : 0.00;

        // Outstanding balance = Total Program Cost - Program Payments
        return max(0.00, $totalProgramCost - $totalProgramPaid);
    }

    /**
     * Get student financial summary (for bursar tab)
     * @param int $studentID
     * @return array
     */
    public function getStudentFinancialSummary(int $studentID): array
    {
        $paymentSummary = $this->getPaymentSummaryByStudent($studentID);
        $outstandingBalance = $this->calculateOutstandingBalance($studentID);

        // Get total program cost for display
        $studentSql = "SELECT s.enrollmentPriceId, s.programID
                       FROM cor4edu_students s
                       WHERE s.studentID = :studentID";

        $student = $this->selectOne($studentSql, ['studentID' => $studentID]);
        $totalProgramCost = 0.00;

        if ($student) {
            // Try locked-in pricing first
            if (!empty($student['enrollmentPriceId'])) {
                $priceSql = "SELECT totalCost
                             FROM cor4edu_program_price_history
                             WHERE priceId = :priceId";

                $priceResult = $this->selectOne($priceSql, ['priceId' => $student['enrollmentPriceId']]);

                if ($priceResult) {
                    $totalProgramCost = (float) $priceResult['totalCost'];
                }
            }

            // Fallback to current program pricing
            if ($totalProgramCost === 0.00 && !empty($student['programID'])) {
                $programSql = "SELECT totalCost
                               FROM cor4edu_programs
                               WHERE programID = :programID";

                $programResult = $this->selectOne($programSql, ['programID' => $student['programID']]);

                if ($programResult) {
                    $totalProgramCost = (float) $programResult['totalCost'];
                }
            }
        }

        return [
            'outstandingBalance' => $outstandingBalance,
            'programPayments' => $paymentSummary['programPayments'],
            'otherPayments' => $paymentSummary['otherPayments'],
            'totalProgramCost' => $totalProgramCost,
            'totalPayments' => $paymentSummary['totalPayments'],
            'programPaymentCount' => $paymentSummary['programPaymentCount'],
            'otherPaymentCount' => $paymentSummary['otherPaymentCount']
        ];
    }

    /**
     * Get payments for financial reporting
     * @param array $filters
     * @return array
     */
    public function getPaymentsForReporting(array $filters = []): array
    {
        $sql = "SELECT p.*,
                       st.firstName as studentFirstName,
                       st.lastName as studentLastName,
                       st.studentCode,
                       prog.name as programName,
                       s.firstName as processedByFirstName,
                       s.lastName as processedByLastName
                FROM cor4edu_payments p
                LEFT JOIN cor4edu_students st ON p.studentID = st.studentID
                LEFT JOIN cor4edu_programs prog ON st.programID = prog.programID
                LEFT JOIN cor4edu_staff s ON p.processedBy = s.staffID
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $sql .= " AND st.programID = :programID";
            $params['programID'] = $filters['programID'];
        }

        if (!empty($filters['paymentType'])) {
            $sql .= " AND p.paymentType = :paymentType";
            $params['paymentType'] = $filters['paymentType'];
        }

        if (!empty($filters['dateFrom'])) {
            $sql .= " AND p.paymentDate >= :dateFrom";
            $params['dateFrom'] = $filters['dateFrom'];
        }

        if (!empty($filters['dateTo'])) {
            $sql .= " AND p.paymentDate <= :dateTo";
            $params['dateTo'] = $filters['dateTo'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND p.status = :status";
            $params['status'] = $filters['status'];
        }

        $sql .= " ORDER BY p.paymentDate DESC, p.createdOn DESC";

        return $this->select($sql, $params);
    }

    /**
     * Get next invoice number
     * @return string
     */
    public function getNextInvoiceNumber(): string
    {
        $sql = "SELECT COUNT(*) + 1 as next_id FROM cor4edu_payments WHERE invoiceNumber IS NOT NULL";
        $result = $this->selectOne($sql);
        $nextId = $result['next_id'];

        return 'INV' . date('Y') . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validate payment data
     * @param array $data
     * @return array Array of validation errors
     */
    public function validatePaymentData(array $data): array
    {
        $errors = [];

        // Required fields
        if (empty($data['studentID'])) {
            $errors[] = 'Student ID is required';
        }

        if (empty($data['amount']) || !is_numeric($data['amount']) || (float)$data['amount'] <= 0) {
            $errors[] = 'Amount must be a positive number';
        }

        if (empty($data['paymentDate'])) {
            $errors[] = 'Payment date is required';
        } elseif (strtotime($data['paymentDate']) > time()) {
            $errors[] = 'Payment date cannot be in the future';
        }

        if (empty($data['paymentMethod'])) {
            $errors[] = 'Payment method is required';
        } elseif (!array_key_exists($data['paymentMethod'], $this->getPaymentMethods())) {
            $errors[] = 'Invalid payment method';
        }

        if (!empty($data['paymentType']) && !array_key_exists($data['paymentType'], $this->getPaymentTypes())) {
            $errors[] = 'Invalid payment type';
        }

        return $errors;
    }

    /**
     * Format charge applications for display
     * @param string|array $appliedToCharges
     * @return string
     */
    public function formatChargeApplications($appliedToCharges): string
    {
        if (empty($appliedToCharges)) {
            return '—';
        }

        if (is_string($appliedToCharges)) {
            $appliedToCharges = json_decode($appliedToCharges, true);
        }

        if (!is_array($appliedToCharges)) {
            return '—';
        }

        $formatted = [];
        foreach ($appliedToCharges as $charge) {
            if (isset($charge['chargeType'], $charge['amount'])) {
                $formatted[] = ucfirst($charge['chargeType']) . ': $' . number_format((float)$charge['amount'], 2);
            }
        }

        return $formatted ? implode(', ', $formatted) : '—';
    }
}