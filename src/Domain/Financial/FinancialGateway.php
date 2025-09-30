<?php

namespace Cor4Edu\Domain\Financial;

use Cor4Edu\Domain\Gateway;
use PDO;

/**
 * FinancialGateway following Gibbon patterns
 * Handles financial calculations, aggregations, and reporting
 */
class FinancialGateway extends Gateway
{
    /**
     * Get comprehensive financial summary for a student
     * @param int $studentID
     * @return array
     */
    public function getStudentFinancialSummary(int $studentID): array
    {
        // Get student and program information
        $studentSql = "SELECT s.*,
                              p.name as programName,
                              p.totalCost as currentProgramCost
                       FROM cor4edu_students s
                       LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                       WHERE s.studentID = :studentID";

        $student = $this->selectOne($studentSql, ['studentID' => $studentID]);

        if (!$student) {
            return [];
        }

        // Get student's locked-in pricing (contract protection)
        $totalProgramCost = 0.00;
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
        if ($totalProgramCost === 0.00) {
            $totalProgramCost = (float) ($student['currentProgramCost'] ?? 0);
        }

        // Get payment summary
        $paymentSql = "SELECT
                          paymentType,
                          SUM(amount) as totalAmount,
                          COUNT(*) as paymentCount
                       FROM cor4edu_payments
                       WHERE studentID = :studentID AND status = 'completed'
                       GROUP BY paymentType";

        $paymentResults = $this->select($paymentSql, ['studentID' => $studentID]);

        $programPayments = 0.00;
        $otherPayments = 0.00;
        $programPaymentCount = 0;
        $otherPaymentCount = 0;

        foreach ($paymentResults as $result) {
            $amount = (float) $result['totalAmount'];
            $count = (int) $result['paymentCount'];

            if ($result['paymentType'] === 'program') {
                $programPayments = $amount;
                $programPaymentCount = $count;
            } elseif ($result['paymentType'] === 'other') {
                $otherPayments = $amount;
                $otherPaymentCount = $count;
            }
        }

        $outstandingBalance = max(0.00, $totalProgramCost - $programPayments);

        return [
            'student' => $student,
            'totalProgramCost' => $totalProgramCost,
            'programPayments' => $programPayments,
            'otherPayments' => $otherPayments,
            'totalPayments' => $programPayments + $otherPayments,
            'outstandingBalance' => $outstandingBalance,
            'programPaymentCount' => $programPaymentCount,
            'otherPaymentCount' => $otherPaymentCount,
            'totalPaymentCount' => $programPaymentCount + $otherPaymentCount,
            'balanceStatus' => $outstandingBalance > 0 ? 'outstanding' : 'paid',
            'formattedTotalCost' => '$' . number_format($totalProgramCost, 2),
            'formattedProgramPayments' => '$' . number_format($programPayments, 2),
            'formattedOtherPayments' => '$' . number_format($otherPayments, 2),
            'formattedOutstandingBalance' => '$' . number_format($outstandingBalance, 2)
        ];
    }

    /**
     * Get financial summary report data for all programs
     * @return array
     */
    public function getFinancialSummaryReportData(): array
    {
        $sql = "SELECT
                    p.programID,
                    p.name as programName,
                    p.totalCost as currentProgramCost,
                    COUNT(s.studentID) as totalStudents,
                    COUNT(CASE WHEN s.status = 'active' THEN 1 END) as activeStudents,
                    -- Calculate total revenue (expected income)
                    SUM(
                        CASE
                            WHEN s.enrollmentPriceId IS NOT NULL THEN (
                                SELECT ph.totalCost
                                FROM cor4edu_program_price_history ph
                                WHERE ph.priceId = s.enrollmentPriceId
                            )
                            ELSE p.totalCost
                        END
                    ) as totalRevenue,
                    -- Program payments (towards tuition)
                    COALESCE(program_payments.totalProgramPaid, 0) as totalProgramPaid,
                    -- Other payments (miscellaneous)
                    COALESCE(other_payments.totalOtherPaid, 0) as totalOtherPaid
                FROM cor4edu_programs p
                LEFT JOIN cor4edu_students s ON p.programID = s.programID
                LEFT JOIN (
                    SELECT
                        s.programID,
                        SUM(pay.amount) as totalProgramPaid
                    FROM cor4edu_payments pay
                    JOIN cor4edu_students s ON pay.studentID = s.studentID
                    WHERE pay.paymentType = 'program' AND pay.status = 'completed'
                    GROUP BY s.programID
                ) program_payments ON p.programID = program_payments.programID
                LEFT JOIN (
                    SELECT
                        s.programID,
                        SUM(pay.amount) as totalOtherPaid
                    FROM cor4edu_payments pay
                    JOIN cor4edu_students s ON pay.studentID = s.studentID
                    WHERE pay.paymentType = 'other' AND pay.status = 'completed'
                    GROUP BY s.programID
                ) other_payments ON p.programID = other_payments.programID
                WHERE p.active = 'Y'
                GROUP BY p.programID
                ORDER BY p.name";

        $results = $this->select($sql);

        $reportData = [];
        foreach ($results as $result) {
            $totalRevenue = (float) $result['totalRevenue'];
            $totalProgramPaid = (float) $result['totalProgramPaid'];
            $totalOtherPaid = (float) $result['totalOtherPaid'];
            $totalPaid = $totalProgramPaid + $totalOtherPaid;
            $outstandingBalance = max(0.00, $totalRevenue - $totalProgramPaid);

            $reportData[] = [
                'programID' => (int) $result['programID'],
                'programName' => $result['programName'],
                'totalStudents' => (int) $result['totalStudents'],
                'activeStudents' => (int) $result['activeStudents'],
                'totalRevenue' => $totalRevenue,
                'totalProgramPaid' => $totalProgramPaid,
                'totalOtherPaid' => $totalOtherPaid,
                'totalPaid' => $totalPaid,
                'outstandingBalance' => $outstandingBalance,
                'averageBalance' => $result['totalStudents'] > 0 ? $outstandingBalance / $result['totalStudents'] : 0.00,
                'paymentPercentage' => $totalRevenue > 0 ? ($totalProgramPaid / $totalRevenue) * 100 : 0.00,
                // Formatted values
                'formattedTotalRevenue' => '$' . number_format($totalRevenue, 2),
                'formattedTotalPaid' => '$' . number_format($totalPaid, 2),
                'formattedProgramPaid' => '$' . number_format($totalProgramPaid, 2),
                'formattedOtherPaid' => '$' . number_format($totalOtherPaid, 2),
                'formattedOutstandingBalance' => '$' . number_format($outstandingBalance, 2),
                'formattedAverageBalance' => '$' . number_format($result['totalStudents'] > 0 ? $outstandingBalance / $result['totalStudents'] : 0.00, 2)
            ];
        }

        return $reportData;
    }

    /**
     * Get detailed financial report for individual students
     * @param array $filters
     * @return array
     */
    public function getStudentFinancialDetailReport(array $filters = []): array
    {
        $sql = "SELECT
                    s.studentID,
                    s.studentCode,
                    s.firstName,
                    s.lastName,
                    s.email,
                    s.phone,
                    s.status,
                    s.enrollmentDate,
                    s.graduationDate,
                    p.name as programName,
                    p.totalCost as currentProgramCost,
                    s.enrollmentPriceId
                FROM cor4edu_students s
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $sql .= " AND s.programID = :programID";
            $params['programID'] = $filters['programID'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND s.status = :status";
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['enrollmentDateFrom'])) {
            $sql .= " AND s.enrollmentDate >= :enrollmentDateFrom";
            $params['enrollmentDateFrom'] = $filters['enrollmentDateFrom'];
        }

        if (!empty($filters['enrollmentDateTo'])) {
            $sql .= " AND s.enrollmentDate <= :enrollmentDateTo";
            $params['enrollmentDateTo'] = $filters['enrollmentDateTo'];
        }

        $sql .= " ORDER BY s.lastName, s.firstName";

        $students = $this->select($sql, $params);

        $detailReport = [];
        foreach ($students as $student) {
            $financialSummary = $this->getStudentFinancialSummary($student['studentID']);

            $detailReport[] = [
                'studentID' => (int) $student['studentID'],
                'studentCode' => $student['studentCode'],
                'studentName' => $student['firstName'] . ' ' . $student['lastName'],
                'firstName' => $student['firstName'],
                'lastName' => $student['lastName'],
                'email' => $student['email'],
                'phone' => $student['phone'],
                'status' => $student['status'],
                'enrollmentDate' => $student['enrollmentDate'],
                'graduationDate' => $student['graduationDate'],
                'programName' => $student['programName'],
                'totalCost' => $financialSummary['totalProgramCost'],
                'paidAmount' => $financialSummary['totalPayments'],
                'programPaidAmount' => $financialSummary['programPayments'],
                'otherPaidAmount' => $financialSummary['otherPayments'],
                'outstandingBalance' => $financialSummary['outstandingBalance'],
                'balanceStatus' => $financialSummary['balanceStatus'],
                'paymentCount' => $financialSummary['totalPaymentCount'],
                // Formatted values for display
                'formattedTotalCost' => $financialSummary['formattedTotalCost'],
                'formattedPaidAmount' => '$' . number_format($financialSummary['totalPayments'], 2),
                'formattedProgramPaid' => $financialSummary['formattedProgramPayments'],
                'formattedOtherPaid' => $financialSummary['formattedOtherPayments'],
                'formattedOutstandingBalance' => $financialSummary['formattedOutstandingBalance']
            ];
        }

        return $detailReport;
    }

    /**
     * Get overall financial dashboard metrics
     * @return array
     */
    public function getFinancialDashboardMetrics(): array
    {
        // Total revenue (expected income from all enrolled students)
        $revenueSql = "SELECT
                           SUM(
                               CASE
                                   WHEN s.enrollmentPriceId IS NOT NULL THEN (
                                       SELECT ph.totalCost
                                       FROM cor4edu_program_price_history ph
                                       WHERE ph.priceId = s.enrollmentPriceId
                                   )
                                   ELSE p.totalCost
                               END
                           ) as totalRevenue
                       FROM cor4edu_students s
                       LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                       WHERE s.status = 'active'";

        $revenueResult = $this->selectOne($revenueSql);
        $totalRevenue = (float) ($revenueResult['totalRevenue'] ?? 0);

        // Total payments collected
        $paymentsSql = "SELECT
                            SUM(CASE WHEN paymentType = 'program' THEN amount ELSE 0 END) as totalProgramPayments,
                            SUM(CASE WHEN paymentType = 'other' THEN amount ELSE 0 END) as totalOtherPayments,
                            SUM(amount) as totalAllPayments,
                            COUNT(*) as totalPaymentCount
                        FROM cor4edu_payments
                        WHERE status = 'completed'";

        $paymentsResult = $this->selectOne($paymentsSql);
        $totalProgramPayments = (float) ($paymentsResult['totalProgramPayments'] ?? 0);
        $totalOtherPayments = (float) ($paymentsResult['totalOtherPayments'] ?? 0);
        $totalAllPayments = (float) ($paymentsResult['totalAllPayments'] ?? 0);
        $totalPaymentCount = (int) ($paymentsResult['totalPaymentCount'] ?? 0);

        // Outstanding balances
        $outstandingBalance = max(0.00, $totalRevenue - $totalProgramPayments);

        // Students with outstanding balances
        $outstandingStudentsSql = "SELECT COUNT(DISTINCT s.studentID) as studentsWithBalance
                                   FROM cor4edu_students s
                                   LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                                   LEFT JOIN (
                                       SELECT studentID, SUM(amount) as totalPaid
                                       FROM cor4edu_payments
                                       WHERE paymentType = 'program' AND status = 'completed'
                                       GROUP BY studentID
                                   ) payments ON s.studentID = payments.studentID
                                   WHERE s.status = 'active'
                                   AND (
                                       CASE
                                           WHEN s.enrollmentPriceId IS NOT NULL THEN (
                                               SELECT ph.totalCost
                                               FROM cor4edu_program_price_history ph
                                               WHERE ph.priceId = s.enrollmentPriceId
                                           )
                                           ELSE p.totalCost
                                       END
                                   ) > COALESCE(payments.totalPaid, 0)";

        $outstandingStudentsResult = $this->selectOne($outstandingStudentsSql);
        $studentsWithBalance = (int) ($outstandingStudentsResult['studentsWithBalance'] ?? 0);

        return [
            'totalRevenue' => $totalRevenue,
            'totalProgramPayments' => $totalProgramPayments,
            'totalOtherPayments' => $totalOtherPayments,
            'totalAllPayments' => $totalAllPayments,
            'outstandingBalance' => $outstandingBalance,
            'studentsWithBalance' => $studentsWithBalance,
            'totalPaymentCount' => $totalPaymentCount,
            'collectionRate' => $totalRevenue > 0 ? ($totalProgramPayments / $totalRevenue) * 100 : 0.00,
            // Formatted values
            'formattedTotalRevenue' => '$' . number_format($totalRevenue, 2),
            'formattedTotalProgramPayments' => '$' . number_format($totalProgramPayments, 2),
            'formattedTotalOtherPayments' => '$' . number_format($totalOtherPayments, 2),
            'formattedTotalAllPayments' => '$' . number_format($totalAllPayments, 2),
            'formattedOutstandingBalance' => '$' . number_format($outstandingBalance, 2),
            'formattedCollectionRate' => number_format($totalRevenue > 0 ? ($totalProgramPayments / $totalRevenue) * 100 : 0.00, 1) . '%'
        ];
    }

    /**
     * Get payment trends for analytics
     * @param string $period ('month', 'quarter', 'year')
     * @return array
     */
    public function getPaymentTrends(string $period = 'month'): array
    {
        $dateFormat = match($period) {
            'quarter' => "%Y-Q%q",
            'year' => "%Y",
            default => "%Y-%m"
        };

        $sql = "SELECT
                    DATE_FORMAT(paymentDate, '{$dateFormat}') as period,
                    paymentType,
                    SUM(amount) as totalAmount,
                    COUNT(*) as paymentCount
                FROM cor4edu_payments
                WHERE status = 'completed'
                AND paymentDate >= DATE_SUB(CURDATE(), INTERVAL 12 {$period})
                GROUP BY period, paymentType
                ORDER BY period ASC";

        return $this->select($sql);
    }

    /**
     * Export financial data for external systems
     * @param array $filters
     * @return array
     */
    public function exportFinancialData(array $filters = []): array
    {
        $sql = "SELECT
                    p.paymentID,
                    p.studentID,
                    s.studentCode,
                    s.firstName,
                    s.lastName,
                    prog.name as programName,
                    p.amount,
                    p.paymentDate,
                    p.paymentMethod,
                    p.paymentType,
                    p.referenceNumber,
                    p.invoiceNumber,
                    p.status,
                    p.notes,
                    COALESCE(staff.firstName, '') as processedByFirstName,
                    COALESCE(staff.lastName, '') as processedByLastName
                FROM cor4edu_payments p
                LEFT JOIN cor4edu_students s ON p.studentID = s.studentID
                LEFT JOIN cor4edu_programs prog ON s.programID = prog.programID
                LEFT JOIN cor4edu_staff staff ON p.processedBy = staff.staffID
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['dateFrom'])) {
            $sql .= " AND p.paymentDate >= :dateFrom";
            $params['dateFrom'] = $filters['dateFrom'];
        }

        if (!empty($filters['dateTo'])) {
            $sql .= " AND p.paymentDate <= :dateTo";
            $params['dateTo'] = $filters['dateTo'];
        }

        if (!empty($filters['programID'])) {
            $sql .= " AND s.programID = :programID";
            $params['programID'] = $filters['programID'];
        }

        if (!empty($filters['paymentType'])) {
            $sql .= " AND p.paymentType = :paymentType";
            $params['paymentType'] = $filters['paymentType'];
        }

        $sql .= " ORDER BY p.paymentDate DESC, p.createdOn DESC";

        return $this->select($sql, $params);
    }
}