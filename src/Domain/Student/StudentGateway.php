<?php

namespace Cor4Edu\Domain\Student;

use Cor4Edu\Domain\QueryableGateway;

/**
 * StudentGateway following Gibbon patterns exactly
 * Handles all student data operations
 */
class StudentGateway extends QueryableGateway
{
    protected static $tableName = 'cor4edu_students';
    protected static $primaryKey = 'studentID';
    protected static $searchableColumns = ['firstName', 'lastName', 'email', 'studentCode'];

    /**
     * Select students by status
     * @param string $status
     * @return array
     */
    public function selectStudentsByStatus(string $status): array
    {
        $sql = "SELECT * FROM cor4edu_students
                WHERE status = :status
                ORDER BY lastName, firstName";
        return $this->select($sql, ['status' => $status]);
    }

    /**
     * Get all students with program information
     * @return array
     */
    public function selectAllWithPrograms(): array
    {
        $sql = "SELECT s.*, p.name as programName
                FROM cor4edu_students s
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                ORDER BY s.lastName, s.firstName";
        return $this->select($sql);
    }

    /**
     * Get status counts for dashboard
     * @return array
     */
    public function getStatusCounts(): array
    {
        $sql = "SELECT
                    status,
                    COUNT(*) as count
                FROM cor4edu_students
                GROUP BY status";

        $results = $this->select($sql);

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['status']] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * Get next student code
     * @return string
     */
    public function getNextStudentCode(): string
    {
        $sql = "SELECT COUNT(*) + 1 as next_id FROM cor4edu_students";
        $result = $this->selectOne($sql);
        $nextId = $result['next_id'];

        return 'STU' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Create new student with generated code
     * @param array $data
     * @return int
     */
    public function createStudent(array $data): int
    {
        // Generate student code if not provided
        if (!isset($data['studentCode'])) {
            $data['studentCode'] = $this->getNextStudentCode();
        }

        // Set default values
        $data['status'] = $data['status'] ?? 'prospective';
        $data['createdOn'] = date('Y-m-d H:i:s');

        return $this->insertRecord($data);
    }

    /**
     * Get student with full details
     * @param int $studentID
     * @return array|false
     */
    public function getStudentDetails(int $studentID)
    {
        $sql = "SELECT s.*,
                       p.name as programName,
                       p.description as programDescription,
                       creator.firstName as createdByFirstName,
                       creator.lastName as createdByLastName
                FROM cor4edu_students s
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_staff creator ON s.createdBy = creator.staffID
                WHERE s.studentID = :studentID";

        return $this->selectOne($sql, ['studentID' => $studentID]);
    }

    /**
     * Search students with advanced filters
     * @param string $search
     * @param string $status
     * @param int $programID
     * @return array
     */
    public function searchStudents(string $search = '', string $status = '', int $programID = 0): array
    {
        $sql = "SELECT s.*, p.name as programName
                FROM cor4edu_students s
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                WHERE 1=1";

        $params = [];

        if (!empty($search)) {
            $sql .= " AND (s.firstName LIKE :search OR s.lastName LIKE :search OR s.email LIKE :search OR s.studentCode LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        if (!empty($status)) {
            $sql .= " AND s.status = :status";
            $params['status'] = $status;
        }

        if ($programID > 0) {
            $sql .= " AND s.programID = :programID";
            $params['programID'] = $programID;
        }

        $sql .= " ORDER BY s.lastName, s.firstName";

        return $this->select($sql, $params);
    }

    /**
     * Get student with program information (alias for getStudentDetails)
     * @param int $studentID
     * @return array|false
     */
    public function selectStudentWithProgram(int $studentID)
    {
        return $this->getStudentDetails($studentID);
    }

    /**
     * Get total student count for dashboard
     * @return int
     */
    public function getTotalStudentCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM cor4edu_students";
        $result = $this->selectOne($sql);
        return (int) $result['count'];
    }

    /**
     * Get active student count for dashboard
     * @return int
     */
    public function getActiveStudentCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM cor4edu_students WHERE status = 'active'";
        $result = $this->selectOne($sql);
        return (int) $result['count'];
    }

    /**
     * Check if email exists for another student
     * @param string $email
     * @param int $excludeStudentID
     * @return bool
     */
    public function emailExistsForOtherStudent(string $email, int $excludeStudentID): bool
    {
        $sql = "SELECT studentID FROM cor4edu_students WHERE email = ? AND studentID != ?";
        $result = $this->selectOne($sql, [$email, $excludeStudentID]);
        return $result !== false;
    }

    // Employment Tracking Methods

    /**
     * Update student employment status
     * @param int $studentID
     * @param string $employmentStatus
     * @param array $additionalData
     * @param int $staffID
     * @return bool
     */
    public function updateEmploymentStatus(int $studentID, string $employmentStatus, array $additionalData = [], int $staffID = 0): bool
    {
        // Get current status for audit trail
        $currentStudent = $this->getByID($studentID);
        if (!$currentStudent) {
            return false;
        }

        $currentStatus = $currentStudent['employmentStatus'] ?? 'not_graduated';

        // Prepare update data
        $updateData = [
            'employmentStatus' => $employmentStatus,
            'modifiedBy' => $staffID,
            'modifiedOn' => date('Y-m-d H:i:s')
        ];

        // Add additional employment fields if provided
        if (isset($additionalData['jobSeekingStartDate'])) {
            $updateData['jobSeekingStartDate'] = $additionalData['jobSeekingStartDate'];
        }
        if (isset($additionalData['jobPlacementDate'])) {
            $updateData['jobPlacementDate'] = $additionalData['jobPlacementDate'];
        }
        if (isset($additionalData['employerName'])) {
            $updateData['employerName'] = $additionalData['employerName'];
        }
        if (isset($additionalData['jobTitle'])) {
            $updateData['jobTitle'] = $additionalData['jobTitle'];
        }

        // Update student record
        $updated = $this->updateByID($studentID, $updateData);

        // Log the status change if update was successful
        if ($updated && $currentStatus !== $employmentStatus) {
            $this->logEmploymentStatusChange($studentID, $currentStatus, $employmentStatus, $additionalData, $staffID);
        }

        return $updated;
    }

    /**
     * Log employment status change for audit trail
     * @param int $studentID
     * @param string $statusFrom
     * @param string $statusTo
     * @param array $additionalData
     * @param int $staffID
     * @return bool
     */
    private function logEmploymentStatusChange(int $studentID, string $statusFrom, string $statusTo, array $additionalData, int $staffID): bool
    {
        $logData = [
            'studentID' => $studentID,
            'statusFrom' => $statusFrom,
            'statusTo' => $statusTo,
            'changeDate' => date('Y-m-d'),
            'createdBy' => $staffID,
            'createdOn' => date('Y-m-d H:i:s')
        ];

        // Add optional fields
        if (isset($additionalData['notes'])) {
            $logData['notes'] = $additionalData['notes'];
        }
        if (isset($additionalData['employerName'])) {
            $logData['employerName'] = $additionalData['employerName'];
        }
        if (isset($additionalData['jobTitle'])) {
            $logData['jobTitle'] = $additionalData['jobTitle'];
        }
        if (isset($additionalData['salaryRange'])) {
            $logData['salaryRange'] = $additionalData['salaryRange'];
        }
        if (isset($additionalData['contactMethod'])) {
            $logData['contactMethod'] = $additionalData['contactMethod'];
        }
        if (isset($additionalData['followUpRequired'])) {
            $logData['followUpRequired'] = $additionalData['followUpRequired'];
        }
        if (isset($additionalData['followUpDate'])) {
            $logData['followUpDate'] = $additionalData['followUpDate'];
        }

        return $this->insert('cor4edu_employment_tracking', $logData) > 0;
    }

    /**
     * Get employment tracking history for a student
     * @param int $studentID
     * @return array
     */
    public function getEmploymentTrackingHistory(int $studentID): array
    {
        $sql = "SELECT et.*,
                       s.firstName as staffFirstName,
                       s.lastName as staffLastName
                FROM cor4edu_employment_tracking et
                LEFT JOIN cor4edu_staff s ON et.createdBy = s.staffID
                WHERE et.studentID = :studentID
                ORDER BY et.changeDate DESC, et.createdOn DESC";

        return $this->select($sql, ['studentID' => $studentID]);
    }

    /**
     * Get employment statistics
     * @return array
     */
    public function getEmploymentStatistics(): array
    {
        $sql = "SELECT
                    employmentStatus,
                    COUNT(*) as count
                FROM cor4edu_students
                WHERE status IN ('graduated', 'alumni')
                GROUP BY employmentStatus";

        $results = $this->select($sql);

        $stats = [
            'not_graduated' => 0,
            'job_seeking' => 0,
            'job_placement_received' => 0
        ];

        foreach ($results as $result) {
            $stats[$result['employmentStatus']] = (int) $result['count'];
        }

        return $stats;
    }

    /**
     * Calculate days to job placement for students
     * @param int $studentID
     * @return int|null
     */
    public function calculateDaysToJobPlacement(int $studentID): ?int
    {
        $sql = "SELECT jobSeekingStartDate, jobPlacementDate
                FROM cor4edu_students
                WHERE studentID = :studentID
                AND jobSeekingStartDate IS NOT NULL
                AND jobPlacementDate IS NOT NULL";

        $result = $this->selectOne($sql, ['studentID' => $studentID]);

        if (!$result) {
            return null;
        }

        $startDate = new \DateTime($result['jobSeekingStartDate']);
        $placementDate = new \DateTime($result['jobPlacementDate']);

        return $placementDate->diff($startDate)->days;
    }

    /**
     * Get students by employment status
     * @param string $employmentStatus
     * @return array
     */
    public function getStudentsByEmploymentStatus(string $employmentStatus): array
    {
        $sql = "SELECT s.*, p.name as programName
                FROM cor4edu_students s
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                WHERE s.employmentStatus = :employmentStatus
                ORDER BY s.lastName, s.firstName";

        return $this->select($sql, ['employmentStatus' => $employmentStatus]);
    }

    /**
     * Get students requiring follow-up for employment
     * @return array
     */
    public function getStudentsRequiringFollowUp(): array
    {
        $sql = "SELECT DISTINCT s.*, p.name as programName
                FROM cor4edu_students s
                LEFT JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_employment_tracking et ON s.studentID = et.studentID
                WHERE (et.followUpRequired = 'Y' AND et.followUpDate <= CURDATE())
                OR (s.employmentStatus = 'job_seeking' AND s.jobSeekingStartDate < DATE_SUB(CURDATE(), INTERVAL 90 DAY))
                ORDER BY s.lastName, s.firstName";

        return $this->select($sql);
    }
}