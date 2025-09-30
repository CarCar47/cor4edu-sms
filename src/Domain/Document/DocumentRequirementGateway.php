<?php

namespace Cor4Edu\Domain\Document;

use Cor4Edu\Domain\QueryableGateway;

/**
 * DocumentRequirementGateway following Gibbon patterns
 * Handles document requirement definitions and student requirement tracking
 */
class DocumentRequirementGateway extends QueryableGateway
{
    protected static $tableName = 'cor4edu_document_requirements';
    protected static $primaryKey = 'requirementID';
    protected static $searchableColumns = ['requirementCode', 'displayName', 'description'];

    /**
     * Get all active requirements
     * @return array
     */
    public function getAllActiveRequirements(): array
    {
        $sql = "SELECT * FROM cor4edu_document_requirements
                WHERE isActive = 'Y'
                ORDER BY tabName, displayOrder";

        return $this->select($sql);
    }

    /**
     * Get requirements by tab name
     * @param string $tabName
     * @return array
     */
    public function getRequirementsByTabName(string $tabName): array
    {
        $sql = "SELECT * FROM cor4edu_document_requirements
                WHERE isActive = 'Y' AND tabName = ?
                ORDER BY displayOrder";

        return $this->select($sql, [$tabName]);
    }

    /**
     * Get requirement by code
     * @param string $requirementCode
     * @return array|false
     */
    public function getRequirementByCode(string $requirementCode)
    {
        $sql = "SELECT * FROM cor4edu_document_requirements
                WHERE requirementCode = ? AND isActive = 'Y'";

        return $this->selectOne($sql, [$requirementCode]);
    }

    /**
     * Create new requirement definition
     * @param array $data
     * @return int
     */
    public function createRequirement(array $data): int
    {
        // Set default values
        $data['createdOn'] = date('Y-m-d H:i:s');
        $data['isActive'] = $data['isActive'] ?? 'Y';
        $data['allowMultiple'] = $data['allowMultiple'] ?? 'N';
        $data['displayOrder'] = $data['displayOrder'] ?? 0;

        return $this->insertRecord($data);
    }

    /**
     * Update requirement definition
     * @param int $requirementID
     * @param array $data
     * @return bool
     */
    public function updateRequirement(int $requirementID, array $data): bool
    {
        $data['modifiedOn'] = date('Y-m-d H:i:s');
        $affectedRows = $this->updateByID($requirementID, $data);
        return $affectedRows > 0;
    }

    /**
     * Deactivate requirement (soft delete)
     * @param int $requirementID
     * @param int $staffID
     * @return bool
     */
    public function deactivateRequirement(int $requirementID, int $staffID): bool
    {
        $data = [
            'isActive' => 'N',
            'modifiedBy' => $staffID,
            'modifiedOn' => date('Y-m-d H:i:s')
        ];

        $affectedRows = $this->updateByID($requirementID, $data);
        return $affectedRows > 0;
    }

    /**
     * Get tab names with requirement counts
     * @return array
     */
    public function getTabsWithRequirementCounts(): array
    {
        $sql = "SELECT tabName, COUNT(*) as requirementCount
                FROM cor4edu_document_requirements
                WHERE isActive = 'Y'
                GROUP BY tabName
                ORDER BY tabName";

        return $this->select($sql);
    }

    /**
     * Check if requirement code exists
     * @param string $requirementCode
     * @param int $excludeRequirementID
     * @return bool
     */
    public function requirementCodeExists(string $requirementCode, int $excludeRequirementID = 0): bool
    {
        $sql = "SELECT requirementID FROM cor4edu_document_requirements
                WHERE requirementCode = ?";
        $params = [$requirementCode];

        if ($excludeRequirementID > 0) {
            $sql .= " AND requirementID != ?";
            $params[] = $excludeRequirementID;
        }

        $result = $this->selectOne($sql, $params);
        return $result !== false;
    }

    /**
     * Get student requirement status summary
     * @param int $studentID
     * @return array
     */
    public function getStudentRequirementSummary(int $studentID): array
    {
        $sql = "SELECT
                    r.tabName,
                    COUNT(r.requirementID) as totalRequirements,
                    COUNT(sr.currentDocumentID) as submittedRequirements,
                    (COUNT(r.requirementID) - COUNT(sr.currentDocumentID)) as missingRequirements
                FROM cor4edu_document_requirements r
                LEFT JOIN cor4edu_student_document_requirements sr ON r.requirementCode = sr.requirementCode
                    AND sr.studentID = ?
                WHERE r.isActive = 'Y'
                GROUP BY r.tabName
                ORDER BY r.tabName";

        return $this->select($sql, [$studentID]);
    }

    /**
     * Initialize requirements for a new student
     * @param int $studentID
     * @param int $staffID
     * @return bool
     */
    public function initializeStudentRequirements(int $studentID, int $staffID): bool
    {
        // Get all active requirements
        $requirements = $this->getAllActiveRequirements();

        if (empty($requirements)) {
            return true; // No requirements to initialize
        }

        // Prepare batch insert
        $sql = "INSERT INTO cor4edu_student_document_requirements
                (studentID, requirementCode, status, createdBy) VALUES ";

        $values = [];
        $params = [];

        foreach ($requirements as $requirement) {
            $values[] = "(?, ?, 'missing', ?)";
            $params[] = $studentID;
            $params[] = $requirement['requirementCode'];
            $params[] = $staffID;
        }

        $sql .= implode(', ', $values);

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get requirements that allow multiple documents
     * @return array
     */
    public function getMultipleDocumentRequirements(): array
    {
        $sql = "SELECT * FROM cor4edu_document_requirements
                WHERE isActive = 'Y' AND allowMultiple = 'Y'
                ORDER BY tabName, displayOrder";

        return $this->select($sql);
    }

    /**
     * Update requirement display order
     * @param array $orderData - Array of ['requirementID' => displayOrder]
     * @return bool
     */
    public function updateDisplayOrder(array $orderData): bool
    {
        try {
            $this->db->beginTransaction();

            foreach ($orderData as $requirementID => $displayOrder) {
                $sql = "UPDATE cor4edu_document_requirements
                        SET displayOrder = ?
                        WHERE requirementID = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$displayOrder, $requirementID]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }

    /**
     * Get requirements with submission statistics
     * @return array
     */
    public function getRequirementsWithStats(): array
    {
        $sql = "SELECT
                    r.*,
                    COUNT(DISTINCT sr.studentID) as totalStudents,
                    COUNT(DISTINCT CASE WHEN sr.status = 'submitted' THEN sr.studentID END) as submittedCount,
                    ROUND(
                        (COUNT(DISTINCT CASE WHEN sr.status = 'submitted' THEN sr.studentID END) * 100.0)
                        / NULLIF(COUNT(DISTINCT sr.studentID), 0),
                        2
                    ) as submissionRate
                FROM cor4edu_document_requirements r
                LEFT JOIN cor4edu_student_document_requirements sr ON r.requirementCode = sr.requirementCode
                WHERE r.isActive = 'Y'
                GROUP BY r.requirementID
                ORDER BY r.tabName, r.displayOrder";

        return $this->select($sql);
    }
}