<?php

namespace Cor4Edu\Domain\CareerPlacement;

use Cor4Edu\Domain\QueryableGateway;

/**
 * CareerPlacementGateway following Gibbon patterns exactly
 * Handles all career placement data operations for compliance reporting
 */
class CareerPlacementGateway extends QueryableGateway
{
    protected static $tableName = 'cor4edu_career_placements';
    protected static $primaryKey = 'placementID';
    protected static $searchableColumns = ['jobTitle', 'employer'];

    /**
     * Get current placement record for a student
     * @param int $studentID
     * @return array|false
     */
    public function getCurrentPlacementRecord(int $studentID)
    {
        $sql = "SELECT cp.*,
                       s.firstName, s.lastName, s.studentCode,
                       p.name as programName,
                       creator.firstName as createdByFirstName,
                       creator.lastName as createdByLastName,
                       verifier.firstName as verifiedByFirstName,
                       verifier.lastName as verifiedByLastName
                FROM cor4edu_career_placements cp
                LEFT JOIN cor4edu_students s ON cp.studentID = s.studentID
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_staff creator ON cp.createdBy = creator.staffID
                LEFT JOIN cor4edu_staff verifier ON cp.verifiedBy = verifier.staffID
                WHERE cp.studentID = :studentID
                AND cp.isCurrentRecord = 'Y'
                ORDER BY cp.createdOn DESC
                LIMIT 1";

        return $this->selectOne($sql, ['studentID' => $studentID]);
    }

    /**
     * Get placement history for a student
     * @param int $studentID
     * @return array
     */
    public function getPlacementHistory(int $studentID): array
    {
        $sql = "SELECT cp.*,
                       creator.firstName as createdByFirstName,
                       creator.lastName as createdByLastName,
                       verifier.firstName as verifiedByFirstName,
                       verifier.lastName as verifiedByLastName
                FROM cor4edu_career_placements cp
                LEFT JOIN cor4edu_staff creator ON cp.createdBy = creator.staffID
                LEFT JOIN cor4edu_staff verifier ON cp.verifiedBy = verifier.staffID
                WHERE cp.studentID = :studentID
                ORDER BY cp.createdOn DESC";

        return $this->select($sql, ['studentID' => $studentID]);
    }

    /**
     * Create new placement record (marks previous as not current)
     * @param array $data
     * @return int
     */
    public function createPlacementRecord(array $data): int
    {
        // Start transaction to ensure data consistency
        $this->pdo->beginTransaction();

        try {
            // Mark existing records as not current
            if (isset($data['studentID'])) {
                $updateSql = "UPDATE cor4edu_career_placements
                              SET isCurrentRecord = 'N',
                                  modifiedOn = NOW(),
                                  modifiedBy = :modifiedBy
                              WHERE studentID = :studentID
                              AND isCurrentRecord = 'Y'";

                $this->pdo->prepare($updateSql)->execute([
                    'studentID' => $data['studentID'],
                    'modifiedBy' => $data['createdBy'] ?? 1
                ]);
            }

            // Set default values
            $data['isCurrentRecord'] = 'Y';
            $data['createdOn'] = date('Y-m-d H:i:s');

            // Insert new record
            $placementID = $this->insert('cor4edu_career_placements', $data);

            $this->pdo->commit();
            return $placementID;
        } catch (\Exception $e) {
            $this->pdo->rollback();
            throw $e;
        }
    }

    /**
     * Update existing placement record
     * @param int $placementID
     * @param array $data
     * @return bool
     */
    public function updatePlacementRecord(int $placementID, array $data): bool
    {
        $data['modifiedOn'] = date('Y-m-d H:i:s');
        $updated = $this->updateByID($placementID, $data);
        return $updated > 0;
    }

    /**
     * Get placement statistics for dashboard
     * @return array
     */
    public function getPlacementStatistics(): array
    {
        $sql = "SELECT
                    employmentStatus,
                    COUNT(*) as count
                FROM cor4edu_career_placements
                WHERE isCurrentRecord = 'Y'
                GROUP BY employmentStatus";

        $results = $this->select($sql);

        $stats = [
            'employed_related' => 0,
            'employed_unrelated' => 0,
            'self_employed_related' => 0,
            'self_employed_unrelated' => 0,
            'not_employed_seeking' => 0,
            'not_employed_not_seeking' => 0,
            'continuing_education' => 0
        ];

        foreach ($results as $result) {
            $stats[$result['employmentStatus']] = (int) $result['count'];
        }

        return $stats;
    }

    /**
     * Get placement records requiring verification
     * @return array
     */
    public function getRecordsRequiringVerification(): array
    {
        $sql = "SELECT cp.*,
                       s.firstName, s.lastName, s.studentCode,
                       p.name as programName
                FROM cor4edu_career_placements cp
                LEFT JOIN cor4edu_students s ON cp.studentID = s.studentID
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                WHERE cp.isCurrentRecord = 'Y'
                AND (cp.verificationDate IS NULL OR cp.verifiedBy IS NULL)
                AND cp.employmentStatus IN ('employed_related', 'employed_unrelated', 'self_employed_related', 'self_employed_unrelated')
                ORDER BY cp.createdOn ASC";

        return $this->select($sql);
    }

    /**
     * Get students by employment status
     * @param string $employmentStatus
     * @return array
     */
    public function getStudentsByEmploymentStatus(string $employmentStatus): array
    {
        $sql = "SELECT cp.*,
                       s.firstName, s.lastName, s.studentCode,
                       p.name as programName
                FROM cor4edu_career_placements cp
                LEFT JOIN cor4edu_students s ON cp.studentID = s.studentID
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                WHERE cp.employmentStatus = :employmentStatus
                AND cp.isCurrentRecord = 'Y'
                ORDER BY s.lastName, s.firstName";

        return $this->select($sql, ['employmentStatus' => $employmentStatus]);
    }

    /**
     * Get employment rate for a program
     * @param int $programID
     * @return array
     */
    public function getEmploymentRateByProgram(int $programID = 0): array
    {
        $sql = "SELECT
                    p.name as programName,
                    cp.employmentStatus,
                    COUNT(*) as count
                FROM cor4edu_career_placements cp
                LEFT JOIN cor4edu_students s ON cp.studentID = s.studentID
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                WHERE cp.isCurrentRecord = 'Y'";

        $params = [];

        if ($programID > 0) {
            $sql .= " AND s.programID = :programID";
            $params['programID'] = $programID;
        }

        $sql .= " GROUP BY p.programID, cp.employmentStatus
                  ORDER BY p.name, cp.employmentStatus";

        return $this->select($sql, $params);
    }

    /**
     * Search placement records with filters
     * @param string $search
     * @param string $employmentStatus
     * @param string $verificationStatus
     * @param int $programID
     * @return array
     */
    public function searchPlacementRecords(string $search = '', string $employmentStatus = '', string $verificationStatus = '', int $programID = 0): array
    {
        $sql = "SELECT cp.*,
                       s.firstName, s.lastName, s.studentCode,
                       p.name as programName,
                       verifier.firstName as verifiedByFirstName,
                       verifier.lastName as verifiedByLastName
                FROM cor4edu_career_placements cp
                LEFT JOIN cor4edu_students s ON cp.studentID = s.studentID
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_staff verifier ON cp.verifiedBy = verifier.staffID
                WHERE cp.isCurrentRecord = 'Y'";

        $params = [];

        if (!empty($search)) {
            $sql .= " AND (s.firstName LIKE :search OR s.lastName LIKE :search OR s.studentCode LIKE :search
                         OR cp.jobTitle LIKE :search OR cp.employerName LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        if (!empty($employmentStatus)) {
            $sql .= " AND cp.employmentStatus = :employmentStatus";
            $params['employmentStatus'] = $employmentStatus;
        }

        if (!empty($verificationStatus)) {
            if ($verificationStatus === 'verified') {
                $sql .= " AND cp.verificationDate IS NOT NULL AND cp.verifiedBy IS NOT NULL";
            } elseif ($verificationStatus === 'not_verified') {
                $sql .= " AND (cp.verificationDate IS NULL OR cp.verifiedBy IS NULL)";
            }
        }

        if ($programID > 0) {
            $sql .= " AND s.programID = :programID";
            $params['programID'] = $programID;
        }

        $sql .= " ORDER BY s.lastName, s.firstName";

        return $this->select($sql, $params);
    }

    /**
     * Verify placement record
     * @param int $placementID
     * @param int $verifiedBy
     * @param string $verificationSource
     * @param string $verificationNotes
     * @return bool
     */
    public function verifyPlacementRecord(int $placementID, int $verifiedBy, string $verificationSource, string $verificationNotes = ''): bool
    {
        $data = [
            'verifiedBy' => $verifiedBy,
            'verificationDate' => date('Y-m-d'),
            'verificationSource' => $verificationSource,
            'verificationNotes' => $verificationNotes,
            'modifiedBy' => $verifiedBy,
            'modifiedOn' => date('Y-m-d H:i:s')
        ];

        return $this->updateByID($placementID, $data) > 0;
    }

    /**
     * Get compliance report data
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getComplianceReportData(string $startDate = '', string $endDate = ''): array
    {
        $sql = "SELECT cp.*,
                       s.firstName, s.lastName, s.studentCode, s.actualGraduationDate,
                       p.name as programName,
                       verifier.firstName as verifiedByFirstName,
                       verifier.lastName as verifiedByLastName
                FROM cor4edu_career_placements cp
                LEFT JOIN cor4edu_students s ON cp.studentID = s.studentID
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_staff verifier ON cp.verifiedBy = verifier.staffID
                WHERE cp.isCurrentRecord = 'Y'";

        $params = [];

        if (!empty($startDate)) {
            $sql .= " AND s.actualGraduationDate >= :startDate";
            $params['startDate'] = $startDate;
        }

        if (!empty($endDate)) {
            $sql .= " AND s.actualGraduationDate <= :endDate";
            $params['endDate'] = $endDate;
        }

        $sql .= " ORDER BY p.name, s.lastName, s.firstName";

        return $this->select($sql, $params);
    }
}
