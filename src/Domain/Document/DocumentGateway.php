<?php

namespace Cor4Edu\Domain\Document;

use Cor4Edu\Domain\QueryableGateway;

/**
 * DocumentGateway following Gibbon patterns
 * Handles all document data operations
 */
class DocumentGateway extends QueryableGateway
{
    protected static $tableName = 'cor4edu_documents';
    protected static $primaryKey = 'documentID';
    protected static $searchableColumns = ['fileName', 'category', 'subcategory', 'notes'];

    /**
     * Get documents for a specific entity (student or staff)
     * @param string $entityType
     * @param int $entityID
     * @param string $category
     * @return array
     */
    public function getDocumentsByEntity(string $entityType, int $entityID, string $category = ''): array
    {
        $sql = "SELECT d.*,
                       uploader.firstName as uploaderFirstName,
                       uploader.lastName as uploaderLastName
                FROM cor4edu_documents d
                LEFT JOIN cor4edu_staff uploader ON d.uploadedBy = uploader.staffID
                WHERE d.entityType = :entityType
                AND d.entityID = :entityID
                AND d.isArchived = 'N'";

        $params = [
            'entityType' => $entityType,
            'entityID' => $entityID
        ];

        if (!empty($category)) {
            $sql .= " AND d.category = :category";
            $params['category'] = $category;
        }

        $sql .= " ORDER BY d.uploadedOn DESC";

        return $this->select($sql, $params);
    }

    /**
     * Create new document record
     * @param array $data
     * @return int
     */
    public function createDocument(array $data): int
    {
        // Set default values
        $data['uploadedOn'] = date('Y-m-d H:i:s');
        $data['isArchived'] = 'N';

        return $this->insertRecord($data);
    }

    /**
     * Get document details by ID
     * @param int $documentID
     * @return array|false
     */
    public function getDocumentDetails(int $documentID)
    {
        $sql = "SELECT d.*,
                       uploader.firstName as uploaderFirstName,
                       uploader.lastName as uploaderLastName
                FROM cor4edu_documents d
                LEFT JOIN cor4edu_staff uploader ON d.uploadedBy = uploader.staffID
                WHERE d.documentID = :documentID";

        return $this->selectOne($sql, ['documentID' => $documentID]);
    }

    /**
     * Archive document (soft delete)
     * @param int $documentID
     * @param int $archivedBy
     * @return bool
     */
    public function archiveDocument(int $documentID, int $archivedBy): bool
    {
        $data = [
            'isArchived' => 'Y',
            'archivedBy' => $archivedBy,
            'archivedOn' => date('Y-m-d H:i:s')
        ];

        $affectedRows = $this->updateByID($documentID, $data);
        return $affectedRows > 0;
    }

    /**
     * Get document categories for student documents
     * @return array
     */
    public function getStudentDocumentCategories(): array
    {
        return [
            'admissions' => 'Admissions Documents',
            'academic' => 'Academic Records',
            'financial' => 'Financial Documents',
            'career' => 'Career Services',
            'personal' => 'Personal Documents',
            'graduation' => 'Graduation Documents',
            'other' => 'Other Documents'
        ];
    }

    /**
     * Get available staff document categories
     * @return array
     */
    public function getStaffDocumentCategories(): array
    {
        return [
            'staff_other' => 'Other Staff Documents',
            'staff_personal' => 'Personal Documents',
            'staff_training' => 'Training Materials',
            'staff_certification' => 'Certifications',
            'staff_administrative' => 'Administrative Documents'
        ];
    }

    /**
     * Get documents by category for a student
     * @param int $studentID
     * @param string $category
     * @return array
     */
    public function getStudentDocumentsByCategory(int $studentID, string $category): array
    {
        return $this->getDocumentsByEntity('student', $studentID, $category);
    }

    /**
     * Get documents by category for staff
     * @param int $staffID
     * @param string $category
     * @return array
     */
    public function getStaffDocumentsByCategory(int $staffID, string $category): array
    {
        return $this->getDocumentsByEntity('staff', $staffID, $category);
    }

    /**
     * Count documents for an entity
     * @param string $entityType
     * @param int $entityID
     * @param string $category
     * @return int
     */
    public function getDocumentCount(string $entityType, int $entityID, string $category = ''): int
    {
        $sql = "SELECT COUNT(*) as count FROM cor4edu_documents
                WHERE entityType = :entityType
                AND entityID = :entityID
                AND isArchived = 'N'";

        $params = [
            'entityType' => $entityType,
            'entityID' => $entityID
        ];

        if (!empty($category)) {
            $sql .= " AND category = :category";
            $params['category'] = $category;
        }

        $result = $this->selectOne($sql, $params);
        return (int) $result['count'];
    }

    /**
     * Check if file name exists for entity to prevent duplicates
     * @param string $entityType
     * @param int $entityID
     * @param string $fileName
     * @param int $excludeDocumentID
     * @return bool
     */
    public function fileNameExistsForEntity(string $entityType, int $entityID, string $fileName, int $excludeDocumentID = 0): bool
    {
        $sql = "SELECT documentID FROM cor4edu_documents
                WHERE entityType = ? AND entityID = ? AND fileName = ? AND isArchived = 'N'";
        $params = [$entityType, $entityID, $fileName];

        if ($excludeDocumentID > 0) {
            $sql .= " AND documentID != ?";
            $params[] = $excludeDocumentID;
        }

        $result = $this->selectOne($sql, $params);
        return $result !== false;
    }

    /**
     * Get student requirements status for all requirements
     * @param int $studentID
     * @return array
     */
    public function getStudentRequirements(int $studentID): array
    {
        $sql = "SELECT r.*,
                       sr.studentRequirementID,
                       sr.currentDocumentID,
                       sr.status,
                       sr.submittedOn,
                       d.fileName as currentFileName,
                       d.filePath as currentFilePath,
                       d.fileSize as currentFileSize,
                       d.uploadedOn as currentUploadedOn
                FROM cor4edu_document_requirements r
                LEFT JOIN cor4edu_student_document_requirements sr ON r.requirementCode = sr.requirementCode
                    AND sr.studentID = :studentID
                LEFT JOIN cor4edu_documents d ON sr.currentDocumentID = d.documentID
                WHERE r.isActive = 'Y'
                ORDER BY r.tabName, r.displayOrder";

        return $this->select($sql, ['studentID' => $studentID]);
    }

    /**
     * Get requirements for a specific tab
     * @param string $tabName
     * @param int $studentID
     * @return array
     */
    public function getRequirementsByTab(string $tabName, int $studentID): array
    {
        $sql = "SELECT r.*,
                       sr.studentRequirementID,
                       sr.currentDocumentID,
                       sr.status,
                       sr.submittedOn,
                       d.fileName as currentFileName,
                       d.filePath as currentFilePath,
                       d.fileSize as currentFileSize,
                       d.uploadedOn as currentUploadedOn,
                       uploader.firstName as uploaderFirstName,
                       uploader.lastName as uploaderLastName
                FROM cor4edu_document_requirements r
                LEFT JOIN cor4edu_student_document_requirements sr ON r.requirementCode = sr.requirementCode
                    AND sr.studentID = :studentID
                LEFT JOIN cor4edu_documents d ON sr.currentDocumentID = d.documentID
                LEFT JOIN cor4edu_staff uploader ON d.uploadedBy = uploader.staffID
                WHERE r.isActive = 'Y' AND r.tabName = :tabName
                ORDER BY r.displayOrder";

        return $this->select($sql, ['studentID' => $studentID, 'tabName' => $tabName]);
    }

    /**
     * Update student requirement with new document
     * @param int $studentID
     * @param string $requirementCode
     * @param int $documentID
     * @param int $staffID
     * @return bool
     */
    public function updateStudentRequirement(int $studentID, string $requirementCode, int $documentID, int $staffID): bool
    {
        // Check if requirement record exists
        $existingRecord = $this->selectOne(
            "SELECT studentRequirementID FROM cor4edu_student_document_requirements
             WHERE studentID = ? AND requirementCode = ?",
            [$studentID, $requirementCode]
        );

        if ($existingRecord) {
            // Update existing record using proper Gateway pattern
            $affectedRows = $this->update(
                'cor4edu_student_document_requirements',
                [
                    'currentDocumentID' => $documentID,
                    'status' => 'submitted',
                    'submittedOn' => date('Y-m-d H:i:s'),
                    'modifiedBy' => $staffID
                ],
                'studentID = :studentID AND requirementCode = :requirementCode',
                ['studentID' => $studentID, 'requirementCode' => $requirementCode]
            );
            return $affectedRows > 0;
        } else {
            // Create new record using proper Gateway pattern
            $insertId = $this->insert(
                'cor4edu_student_document_requirements',
                [
                    'studentID' => $studentID,
                    'requirementCode' => $requirementCode,
                    'currentDocumentID' => $documentID,
                    'status' => 'submitted',
                    'submittedOn' => date('Y-m-d H:i:s'),
                    'createdBy' => $staffID
                ]
            );
            return $insertId > 0;
        }
    }

    /**
     * Get current requirement document for student
     * @param int $studentID
     * @param string $requirementCode
     * @return array|false
     */
    public function getRequirementDocument(int $studentID, string $requirementCode)
    {
        $sql = "SELECT d.*,
                       uploader.firstName as uploaderFirstName,
                       uploader.lastName as uploaderLastName,
                       sr.submittedOn,
                       sr.status
                FROM cor4edu_student_document_requirements sr
                LEFT JOIN cor4edu_documents d ON sr.currentDocumentID = d.documentID
                LEFT JOIN cor4edu_staff uploader ON d.uploadedBy = uploader.staffID
                WHERE sr.studentID = ? AND sr.requirementCode = ? AND sr.currentDocumentID IS NOT NULL";

        return $this->selectOne($sql, [$studentID, $requirementCode]);
    }

    /**
     * Unlink requirement document but keep it in archive
     * @param int $studentID
     * @param string $requirementCode
     * @param int $staffID
     * @return bool
     */
    public function unlinkRequirementDocument(int $studentID, string $requirementCode, int $staffID): bool
    {
        // Update using proper Gateway pattern
        $affectedRows = $this->update(
            'cor4edu_student_document_requirements',
            [
                'currentDocumentID' => null,
                'status' => 'missing',
                'modifiedBy' => $staffID
            ],
            'studentID = :studentID AND requirementCode = :requirementCode',
            ['studentID' => $studentID, 'requirementCode' => $requirementCode]
        );
        return $affectedRows > 0;
    }

    /**
     * Get requirement history for audit trail
     * @param int $studentID
     * @param string $requirementCode
     * @return array
     */
    public function getRequirementHistory(int $studentID, string $requirementCode): array
    {
        $sql = "SELECT d.*,
                       uploader.firstName as uploaderFirstName,
                       uploader.lastName as uploaderLastName
                FROM cor4edu_documents d
                LEFT JOIN cor4edu_staff uploader ON d.uploadedBy = uploader.staffID
                WHERE d.entityType = 'student'
                AND d.entityID = ?
                AND d.linkedRequirementCode = ?
                AND d.isArchived = 'N'
                ORDER BY d.uploadedOn DESC";

        return $this->select($sql, [$studentID, $requirementCode]);
    }

    /**
     * Create document with requirement linking
     * @param array $data
     * @param string $requirementCode
     * @return int
     */
    public function createDocumentWithRequirement(array $data, string $requirementCode = null): int
    {
        // Add requirement linking if provided
        if ($requirementCode) {
            $data['linkedRequirementCode'] = $requirementCode;
        }

        return $this->createDocument($data);
    }

    /**
     * Get requirement definition by code
     * @param string $requirementCode
     * @return array|false
     */
    public function getRequirementDefinition(string $requirementCode)
    {
        $sql = "SELECT * FROM cor4edu_document_requirements WHERE requirementCode = ? AND isActive = 'Y'";
        return $this->selectOne($sql, [$requirementCode]);
    }

    /**
     * Permanently delete document and file (Admin only)
     * Following Gibbon's ScrubbableGateway pattern
     * @param int $documentID
     * @return bool
     */
    public function deleteDocumentPermanently(int $documentID): bool
    {
        // Get document details first to delete file
        $document = $this->getDocumentDetails($documentID);
        if (!$document) {
            return false;
        }

        try {
            // Delete file from filesystem
            $fullPath = __DIR__ . '/../../../' . $document['filePath'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            // Delete from database
            $sql = "DELETE FROM cor4edu_documents WHERE documentID = ?";
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$documentID]);

            return $success && $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get all documents for admin management
     * @param array $filters
     * @return array
     */
    public function getDocumentsForAdmin(array $filters = []): array
    {
        $sql = "SELECT d.*,
                       s.firstName as studentFirstName,
                       s.lastName as studentLastName,
                       s.studentCode,
                       uploader.firstName as uploaderFirstName,
                       uploader.lastName as uploaderLastName
                FROM cor4edu_documents d
                LEFT JOIN cor4edu_students s ON d.entityType = 'student' AND d.entityID = s.studentID
                LEFT JOIN cor4edu_staff uploader ON d.uploadedBy = uploader.staffID
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['entityType'])) {
            $sql .= " AND d.entityType = :entityType";
            $params['entityType'] = $filters['entityType'];
        }

        if (!empty($filters['category'])) {
            $sql .= " AND d.category = :category";
            $params['category'] = $filters['category'];
        }

        if (!empty($filters['isArchived'])) {
            $sql .= " AND d.isArchived = :isArchived";
            $params['isArchived'] = $filters['isArchived'];
        }

        if (!empty($filters['olderThan'])) {
            $sql .= " AND d.uploadedOn < :olderThan";
            $params['olderThan'] = $filters['olderThan'];
        }

        $sql .= " ORDER BY d.uploadedOn DESC";

        return $this->select($sql, $params);
    }

    /**
     * Get storage statistics for admin dashboard
     * @return array
     */
    public function getStorageStatistics(): array
    {
        $sql = "SELECT
                    COUNT(*) as totalDocuments,
                    SUM(fileSize) as totalSize,
                    COUNT(CASE WHEN isArchived = 'Y' THEN 1 END) as archivedDocuments,
                    SUM(CASE WHEN isArchived = 'Y' THEN fileSize END) as archivedSize,
                    COUNT(CASE WHEN linkedRequirementCode IS NOT NULL THEN 1 END) as requirementDocuments,
                    AVG(fileSize) as averageSize
                FROM cor4edu_documents";

        $result = $this->selectOne($sql);

        // Format sizes
        $result['totalSizeFormatted'] = $this->formatFileSize($result['totalSize'] ?? 0);
        $result['archivedSizeFormatted'] = $this->formatFileSize($result['archivedSize'] ?? 0);
        $result['averageSizeFormatted'] = $this->formatFileSize($result['averageSize'] ?? 0);

        return $result;
    }

    /**
     * Get documents by category for admin review
     * @return array
     */
    public function getDocumentsByCategory(): array
    {
        $sql = "SELECT
                    category,
                    COUNT(*) as documentCount,
                    SUM(fileSize) as totalSize,
                    COUNT(CASE WHEN isArchived = 'Y' THEN 1 END) as archivedCount
                FROM cor4edu_documents
                GROUP BY category
                ORDER BY totalSize DESC";

        $results = $this->select($sql);

        // Format sizes
        foreach ($results as &$result) {
            $result['totalSizeFormatted'] = $this->formatFileSize($result['totalSize']);
        }

        return $results;
    }

    /**
     * Bulk delete documents older than specified date (Admin only)
     * @param string $olderThan Date in Y-m-d format
     * @param array $categories Optional category filter
     * @return int Number of documents deleted
     */
    public function bulkDeleteOldDocuments(string $olderThan, array $categories = []): int
    {
        // Get documents to delete first
        $filters = [
            'olderThan' => $olderThan,
            'isArchived' => 'Y' // Only delete archived documents
        ];

        $documents = $this->getDocumentsForAdmin($filters);

        if (!empty($categories)) {
            $documents = array_filter($documents, function($doc) use ($categories) {
                return in_array($doc['category'], $categories);
            });
        }

        $deletedCount = 0;
        foreach ($documents as $document) {
            if ($this->deleteDocumentPermanently($document['documentID'])) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * Format file size for display
     * @param int $bytes
     * @return string
     */
    private function formatFileSize($bytes): string
    {
        if (!$bytes || $bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        $bytes = (float)$bytes;

        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }

        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Check if user has admin permissions for document management
     * @param array $user Session user data
     * @return bool
     */
    public function hasDocumentAdminPermission(array $user): bool
    {
        return isset($user['is_super_admin']) && $user['is_super_admin'] === true;
    }

    /**
     * Get required documents for staff members
     * @param int $staffID
     * @return array
     */
    public function getStaffRequirements(int $staffID): array
    {
        // Define the 5 required staff documents
        $requiredDocuments = [
            [
                'requirementCode' => 'staff_resume',
                'title' => 'Resume',
                'description' => 'Current professional resume',
                'isRequired' => true
            ],
            [
                'requirementCode' => 'staff_cie_documents',
                'title' => 'CIE Documents',
                'description' => 'Commission for Independent Education documentation',
                'isRequired' => true
            ],
            [
                'requirementCode' => 'staff_professional_license',
                'title' => 'Professional License',
                'description' => 'Current professional licenses and certifications',
                'isRequired' => true
            ],
            [
                'requirementCode' => 'staff_official_transcripts',
                'title' => 'Official Transcripts',
                'description' => 'Official educational transcripts',
                'isRequired' => true
            ],
            [
                'requirementCode' => 'staff_continuing_education',
                'title' => 'Continuing Education',
                'description' => 'Continuing education documentation (8 hours/annually)',
                'isRequired' => true
            ]
        ];

        // Check which requirements have submitted documents
        foreach ($requiredDocuments as &$requirement) {
            $sql = "SELECT d.documentID, d.fileName, d.filePath, d.uploadedOn,
                           uploader.firstName as uploaderFirstName,
                           uploader.lastName as uploaderLastName
                    FROM cor4edu_documents d
                    LEFT JOIN cor4edu_staff uploader ON d.uploadedBy = uploader.staffID
                    WHERE d.entityType = 'staff'
                      AND d.entityID = :staffID
                      AND d.linkedRequirementCode = :requirementCode
                      AND d.isArchived = 'N'
                    ORDER BY d.uploadedOn DESC
                    LIMIT 1";

            $document = $this->selectOne($sql, [
                'staffID' => $staffID,
                'requirementCode' => $requirement['requirementCode']
            ]);

            if ($document) {
                $requirement['status'] = 'submitted';
                $requirement['currentDocument'] = $document;
            } else {
                $requirement['status'] = 'missing';
                $requirement['currentDocument'] = null;
            }
        }

        return $requiredDocuments;
    }
}