<?php

/**
 * COR4EDU SMS Reports Gateway
 * Base class for all report data aggregation following Gibbon patterns
 */

namespace Cor4Edu\Reports\Domain;

class ReportsGateway
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get institution-wide overview statistics
     * @return array Summary statistics for dashboard
     */
    public function getInstitutionOverview(): array
    {
        // Student statistics
        $sql = "SELECT
                    COUNT(*) as totalStudents,
                    SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as activeStudents,
                    SUM(CASE WHEN status = 'Graduated' THEN 1 ELSE 0 END) as graduatedStudents,
                    SUM(CASE WHEN status = 'Prospective' THEN 1 ELSE 0 END) as prospectiveStudents,
                    SUM(CASE WHEN status = 'Withdrawn' THEN 1 ELSE 0 END) as withdrawnStudents
                FROM cor4edu_students";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $studentStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Program statistics
        $sql = "SELECT
                    COUNT(*) as totalPrograms,
                    SUM(CASE WHEN active = 'Y' THEN 1 ELSE 0 END) as activePrograms
                FROM cor4edu_programs";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $programStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Financial statistics
        $sql = "SELECT
                    COUNT(DISTINCT studentID) as studentsWithPayments,
                    SUM(amount) as totalRevenue,
                    AVG(amount) as averagePayment
                FROM cor4edu_payments";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        $financialStats = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Career placement statistics - simplified for now
        $careerStats = [
            'totalPlacements' => 0,
            'employedGraduates' => 0,
            'avgPlacementDays' => 0
        ];

        // Try to get career statistics if table exists
        try {
            // First check if table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'cor4edu_career_placements'");
            if ($stmt->rowCount() > 0) {
                $sql = "SELECT
                            COUNT(*) as totalPlacements,
                            SUM(CASE WHEN cp.employmentStatus IN ('employed_related', 'employed_unrelated', 'self_employed_related', 'self_employed_unrelated') THEN 1 ELSE 0 END) as employedGraduates,
                            AVG(CASE WHEN cp.employmentStatus IN ('employed_related', 'employed_unrelated', 'self_employed_related', 'self_employed_unrelated') AND cp.employmentDate IS NOT NULL
                                     THEN DATEDIFF(cp.employmentDate, s.actualGraduationDate) END) as avgPlacementDays
                        FROM cor4edu_career_placements cp
                        JOIN cor4edu_students s ON cp.studentID = s.studentID
                        WHERE s.status = 'Graduated' AND cp.isCurrentRecord = 'Y'";

                $stmt = $this->pdo->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($result) {
                    $careerStats = $result;
                }
            }
        } catch (\Exception $e) {
            // Career placements table might not exist or have wrong columns, use default values
            error_log("Career placements data not available: " . $e->getMessage());
        }

        return [
            'students' => $studentStats,
            'programs' => $programStats,
            'financial' => $financialStats,
            'career' => $careerStats,
            'lastUpdated' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Get enrollment trends over time with summary statistics
     * @param string $startDate
     * @param string $endDate
     * @return array Enrollment data by month with summary stats
     */
    public function getEnrollmentTrends(string $startDate, string $endDate): array
    {
        // Get detail data
        $detailSql = "SELECT
                    DATE_FORMAT(s.enrollmentDate, '%Y-%m') as month,
                    COUNT(*) as enrollments,
                    p.programID,
                    p.name as programName
                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID
                WHERE s.enrollmentDate BETWEEN :startDate AND :endDate
                GROUP BY 1, 3, 4
                ORDER BY month, programName";

        $stmt = $this->pdo->prepare($detailSql);
        $stmt->bindParam(':startDate', $startDate);
        $stmt->bindParam(':endDate', $endDate);
        $stmt->execute();
        $details = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Calculate summary statistics in SQL (industry standard approach)
        $summarySql = "SELECT
                    COUNT(*) as totalEnrollments,
                    COUNT(DISTINCT DATE_FORMAT(s.enrollmentDate, '%Y-%m')) as uniqueMonths,
                    COUNT(DISTINCT p.programID) as uniquePrograms
                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID
                WHERE s.enrollmentDate BETWEEN :startDate AND :endDate";

        $summaryStmt = $this->pdo->prepare($summarySql);
        $summaryStmt->bindParam(':startDate', $startDate);
        $summaryStmt->bindParam(':endDate', $endDate);
        $summaryStmt->execute();
        $summary = $summaryStmt->fetch(\PDO::FETCH_ASSOC);

        return [
            'details' => $details,
            'summary' => $summary
        ];
    }

    /**
     * Get program enrollment summary
     * @param array $filters Optional filters for program, status, date range
     * @return array Program enrollment data
     */
    public function getProgramEnrollmentSummary(array $filters = []): array
    {
        $sql = "SELECT
                    p.programID,
                    p.name as programName,
                    p.programCode,
                    COUNT(s.studentID) as totalEnrollments,
                    SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as activeStudents,
                    SUM(CASE WHEN s.status = 'Graduated' THEN 1 ELSE 0 END) as graduatedStudents,
                    SUM(CASE WHEN s.status = 'Withdrawn' THEN 1 ELSE 0 END) as withdrawnStudents,
                    SUM(CASE WHEN s.status = 'Prospective' THEN 1 ELSE 0 END) as prospectiveStudents,
                    MIN(s.enrollmentDate) as firstEnrollment,
                    MAX(s.enrollmentDate) as lastEnrollment
                FROM cor4edu_programs p
                LEFT JOIN cor4edu_students s ON p.programID = s.programID";

        $conditions = [];
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "p.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['status'])) {
            $conditions[] = "s.status IN (" . implode(',', array_fill(0, count($filters['status']), '?')) . ")";
            $params = array_merge($params, $filters['status']);
        }

        if (!empty($filters['startDate'])) {
            $conditions[] = "s.enrollmentDate >= ?";
            $params[] = $filters['startDate'];
        }

        if (!empty($filters['endDate'])) {
            $conditions[] = "s.enrollmentDate <= ?";
            $params[] = $filters['endDate'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY p.programID, p.name, p.programCode
                  ORDER BY p.name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get detailed student list for reports
     * @param array $filters Filters for program, status, etc.
     * @param array $optionalFields Additional fields to include
     * @return array Student data
     */
    public function getStudentDetailReport(array $filters = [], array $optionalFields = []): array
    {
        $baseFields = [
            's.studentID',
            's.firstName',
            's.lastName',
            's.email',
            's.phone',
            's.status',
            's.enrollmentDate',
            's.anticipatedGraduationDate',
            's.actualGraduationDate',
            'p.name as programName',
            'p.programCode'
        ];

        // Add optional fields based on permissions
        $optionalFieldMap = [
            'address' => 's.address, s.city, s.state, s.zipCode',
            'demographics' => 's.dateOfBirth, s.gender',
            'financial' => '(SELECT SUM(amount) FROM cor4edu_payments WHERE studentID = s.studentID) as totalPaid',
            'employment' => 'cp.employmentStatus, cp.employerName, cp.employmentDate'
        ];

        $fields = $baseFields;
        $additionalJoins = '';

        foreach ($optionalFields as $field) {
            if (isset($optionalFieldMap[$field])) {
                $fields[] = $optionalFieldMap[$field];

                if ($field === 'employment') {
                    $additionalJoins .= " LEFT JOIN cor4edu_career_placements cp ON s.studentID = cp.studentID AND cp.isCurrentRecord = 'Y'";
                }
            }
        }

        $sql = "SELECT " . implode(', ', $fields) . "
                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID"
                . $additionalJoins;

        $conditions = [];
        $params = [];

        // Apply filters (same logic as above)
        if (!empty($filters['programID'])) {
            $conditions[] = "s.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['status'])) {
            $conditions[] = "s.status IN (" . implode(',', array_fill(0, count($filters['status']), '?')) . ")";
            $params = array_merge($params, $filters['status']);
        }

        if (!empty($filters['startDate'])) {
            $conditions[] = "s.enrollmentDate >= ?";
            $params[] = $filters['startDate'];
        }

        if (!empty($filters['endDate'])) {
            $conditions[] = "s.enrollmentDate <= ?";
            $params[] = $filters['endDate'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY s.lastName, s.firstName";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Check if user has permission for specific report type
     * @param int $staffID
     * @param string $reportType
     * @return bool
     */
    public function checkReportPermission(int $staffID, string $reportType): bool
    {
        $sql = "SELECT COUNT(*) as hasPermission
                FROM cor4edu_staff_permissions sp
                JOIN cor4edu_staff s ON sp.staffID = s.staffID
                WHERE sp.staffID = :staffID
                AND sp.module = 'reports'
                AND sp.action = :reportType
                AND sp.allowed = 'Y'
                AND s.active = 'Y'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'staffID' => $staffID,
            'reportType' => $reportType
        ]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return ($result['hasPermission'] > 0);
    }

    /**
     * Get available programs for filter dropdowns
     * @return array Program list
     */
    public function getAvailablePrograms(): array
    {
        $sql = "SELECT programID, name, programCode
                FROM cor4edu_programs
                WHERE active = 'Y'
                ORDER BY name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get available student statuses for filter dropdowns
     * @return array Status list
     */
    public function getAvailableStatuses(): array
    {
        return [
            ['value' => 'Prospective', 'label' => 'Prospective'],
            ['value' => 'Active', 'label' => 'Active'],
            ['value' => 'Graduated', 'label' => 'Graduated'],
            ['value' => 'Withdrawn', 'label' => 'Withdrawn'],
            ['value' => 'Alumni', 'label' => 'Alumni']
        ];
    }
}
