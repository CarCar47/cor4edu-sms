<?php
/**
 * COR4EDU SMS Financial Reports Gateway
 * Specialized gateway for financial reporting and analytics
 */

namespace Cor4Edu\Reports\Domain;

class FinancialReportsGateway
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get financial summary by program
     * @param array $filters Optional filters
     * @return array Financial summary data
     */
    public function getFinancialSummaryByProgram(array $filters = []): array
    {
        $sql = "SELECT
                    p.programID,
                    p.name as programName,
                    p.programCode,
                    COUNT(DISTINCT s.studentID) as totalStudents,
                    COUNT(DISTINCT CASE WHEN pay.paymentID IS NOT NULL THEN s.studentID END) as studentsWithPayments,

                    -- Revenue calculations
                    COALESCE(SUM(pay.amount), 0) as totalRevenue,
                    COALESCE(AVG(pay.amount), 0) as averagePayment,

                    -- Payment type breakdown
                    COALESCE(SUM(CASE WHEN pay.paymentType = 'Program Payment' THEN pay.amount ELSE 0 END), 0) as programRevenue,
                    COALESCE(SUM(CASE WHEN pay.paymentType = 'Other Payment' THEN pay.amount ELSE 0 END), 0) as otherRevenue,

                    -- Outstanding balances (expected vs received based on locked pricing)
                    COUNT(DISTINCT s.studentID) * COALESCE(MAX(p.totalCost), 0) as expectedRevenue,

                    -- Collection rate
                    CASE
                        WHEN COUNT(DISTINCT s.studentID) > 0 THEN
                            ROUND(
                                (COUNT(DISTINCT CASE WHEN pay.paymentID IS NOT NULL THEN s.studentID END) * 100.0) /
                                COUNT(DISTINCT s.studentID), 2
                            )
                        ELSE 0
                    END as collectionRate

                FROM cor4edu_programs p
                LEFT JOIN cor4edu_students s ON p.programID = s.programID
                LEFT JOIN cor4edu_payments pay ON s.studentID = pay.studentID";

        $conditions = ['p.active = \'Y\''];
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "p.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['paymentDateStart'])) {
            $conditions[] = "pay.paymentDate >= ?";
            $params[] = $filters['paymentDateStart'];
        }

        if (!empty($filters['paymentDateEnd'])) {
            $conditions[] = "pay.paymentDate <= ?";
            $params[] = $filters['paymentDateEnd'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY p.programID, p.name, p.programCode
                  ORDER BY totalRevenue DESC, p.name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get detailed student financial report
     * @param array $filters Optional filters
     * @return array Student financial details
     */
    public function getStudentFinancialDetails(array $filters = []): array
    {
        $sql = "SELECT
                    s.studentID,
                    s.firstName,
                    s.lastName,
                    s.email,
                    s.phone,
                    s.status,
                    s.enrollmentDate,
                    p.name as programName,
                    p.programCode,

                    -- Current program pricing (locked at enrollment)
                    p.tuitionAmount,
                    p.fees,
                    p.booksAmount,
                    p.materialsAmount,
                    p.applicationFee,
                    p.miscellaneousCosts,
                    p.totalCost as totalExpected,

                    -- Payment summary
                    COALESCE(SUM(pay.amount), 0) as totalPaid,
                    COUNT(pay.paymentID) as paymentCount,
                    MIN(pay.paymentDate) as firstPaymentDate,
                    MAX(pay.paymentDate) as lastPaymentDate,

                    -- Outstanding balance
                    (p.totalCost - COALESCE(SUM(pay.amount), 0)) as outstandingBalance,

                    -- Payment breakdown
                    COALESCE(SUM(CASE WHEN pay.paymentType = 'Program Payment' THEN pay.amount ELSE 0 END), 0) as programPayments,
                    COALESCE(SUM(CASE WHEN pay.paymentType = 'Other Payment' THEN pay.amount ELSE 0 END), 0) as otherPayments

                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_payments pay ON s.studentID = pay.studentID";

        $conditions = [];
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "s.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['status'])) {
            $conditions[] = "s.status IN (" . implode(',', array_fill(0, count($filters['status']), '?')) . ")";
            $params = array_merge($params, $filters['status']);
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY s.studentID, s.firstName, s.lastName, s.email, s.status, s.enrollmentDate,
                          p.name, p.programCode, p.tuitionAmount, p.fees, p.booksAmount, p.materialsAmount,
                          p.applicationFee, p.miscellaneousCosts, p.totalCost";

        if (!empty($filters['outstandingOnly']) && $filters['outstandingOnly']) {
            $sql .= " HAVING (p.totalCost - COALESCE(SUM(pay.amount), 0)) > 0";
        }

        $sql .= " ORDER BY outstandingBalance DESC, s.lastName, s.firstName";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get payment history report
     * @param array $filters Optional filters
     * @return array Payment history data
     */
    public function getPaymentHistory(array $filters = []): array
    {
        $sql = "SELECT
                    pay.paymentID,
                    pay.paymentDate,
                    pay.amount,
                    pay.paymentType,
                    pay.paymentMethod,
                    pay.description,
                    s.firstName,
                    s.lastName,
                    s.studentID,
                    s.email,
                    s.phone,
                    p.name as programName,
                    p.programCode,
                    staff.firstName as staffFirstName,
                    staff.lastName as staffLastName

                FROM cor4edu_payments pay
                JOIN cor4edu_students s ON pay.studentID = s.studentID
                JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_staff staff ON pay.recordedBy = staff.staffID";

        $conditions = [];
        $params = [];

        // Apply filters
        if (!empty($filters['studentID'])) {
            $conditions[] = "s.studentID = ?";
            $params[] = $filters['studentID'];
        }

        if (!empty($filters['programID'])) {
            $conditions[] = "s.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['paymentType'])) {
            $conditions[] = "pay.paymentType = ?";
            $params[] = $filters['paymentType'];
        }

        if (!empty($filters['paymentDateStart'])) {
            $conditions[] = "pay.paymentDate >= ?";
            $params[] = $filters['paymentDateStart'];
        }

        if (!empty($filters['paymentDateEnd'])) {
            $conditions[] = "pay.paymentDate <= ?";
            $params[] = $filters['paymentDateEnd'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY pay.paymentDate DESC, s.lastName, s.firstName";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get outstanding balances summary
     * @param array $filters Optional filters
     * @return array Outstanding balance data
     */
    public function getOutstandingBalancesSummary(array $filters = []): array
    {
        $sql = "SELECT
                    p.programID,
                    p.name as programName,
                    COUNT(s.studentID) as studentsWithBalance,
                    SUM(
                        p.totalCost - COALESCE(pay_totals.totalPaid, 0)
                    ) as totalOutstanding,
                    AVG(
                        p.totalCost - COALESCE(pay_totals.totalPaid, 0)
                    ) as averageOutstanding,
                    MIN(
                        p.totalCost - COALESCE(pay_totals.totalPaid, 0)
                    ) as minOutstanding,
                    MAX(
                        p.totalCost - COALESCE(pay_totals.totalPaid, 0)
                    ) as maxOutstanding

                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN (
                    SELECT studentID, SUM(amount) as totalPaid
                    FROM cor4edu_payments
                    GROUP BY studentID
                ) pay_totals ON s.studentID = pay_totals.studentID";

        $conditions = ["(p.totalCost - COALESCE(pay_totals.totalPaid, 0)) > 0"];
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "s.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['status'])) {
            $conditions[] = "s.status IN (" . implode(',', array_fill(0, count($filters['status']), '?')) . ")";
            $params = array_merge($params, $filters['status']);
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY p.programID, p.name
                  ORDER BY totalOutstanding DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}