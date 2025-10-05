<?php

/**
 * Staff Profile Gateway
 * Handles staff profile operations including personal info and document management
 */

namespace Cor4Edu\Domain\Staff;

use PDO;
use Exception;

class StaffProfileGateway
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get complete staff profile with role information
     */
    public function getStaffProfile($staffID)
    {
        $sql = "SELECT s.*, rt.roleTypeName, rt.description as roleDescription, rt.defaultTabAccess, rt.isAdminRole
                FROM cor4edu_staff s
                LEFT JOIN cor4edu_staff_role_types rt ON s.roleTypeID = rt.roleTypeID
                WHERE s.staffID = ? AND s.active = 'Y'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffID]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update staff profile information
     */
    public function updateStaffProfile($staffID, $profileData)
    {
        $sql = "UPDATE cor4edu_staff SET
                firstName = ?, lastName = ?, email = ?, phone = ?,
                address = ?, city = ?, state = ?, zipCode = ?, country = ?,
                dateOfBirth = ?, emergencyContact = ?, emergencyPhone = ?,
                position = ?, department = ?, teachingPrograms = ?, notes = ?,
                modifiedBy = ?, modifiedOn = NOW()
                WHERE staffID = ?";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            $profileData['firstName'],
            $profileData['lastName'],
            $profileData['email'],
            $profileData['phone'],
            $profileData['address'],
            $profileData['city'],
            $profileData['state'],
            $profileData['zipCode'],
            $profileData['country'],
            $profileData['dateOfBirth'],
            $profileData['emergencyContact'],
            $profileData['emergencyPhone'],
            $profileData['position'],
            $profileData['department'],
            $profileData['teachingPrograms'],
            $profileData['notes'],
            $profileData['modifiedBy'],
            $staffID
        ]);
    }

    /**
     * Get staff document requirements
     */
    public function getDocumentRequirements()
    {
        $sql = "SELECT * FROM cor4edu_staff_document_requirements
                WHERE active = 'Y'
                ORDER BY displayOrder";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get staff document status for all requirements
     */
    public function getStaffDocumentStatus($staffID)
    {
        $sql = "SELECT sds.*, sdr.name as requirementName, sdr.description as requirementDescription,
                       sdr.required, sdr.renewalRequired, sdr.renewalPeriodMonths,
                       d.fileName, d.filePath, d.uploadedOn, d.uploadedBy
                FROM cor4edu_staff_document_requirements sdr
                LEFT JOIN cor4edu_staff_document_status sds ON sdr.requirementCode = sds.requirementCode AND sds.staffID = ?
                LEFT JOIN cor4edu_documents d ON sds.currentDocumentID = d.documentID
                WHERE sdr.active = 'Y'
                ORDER BY sdr.displayOrder";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get staff other documents (non-required)
     */
    public function getStaffOtherDocuments($staffID)
    {
        $sql = "SELECT d.*, CONCAT(s.firstName, ' ', s.lastName) as uploaderName
                FROM cor4edu_documents d
                LEFT JOIN cor4edu_staff s ON d.uploadedBy = s.staffID
                WHERE d.entityType = 'staff' AND d.entityID = ? AND d.subcategory = 'other'
                ORDER BY d.uploadedOn DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffID]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update document status for a staff member
     */
    public function updateDocumentStatus($staffID, $requirementCode, $status, $documentID = null, $notes = null, $updatedBy = 1)
    {
        $sql = "INSERT INTO cor4edu_staff_document_status
                (staffID, requirementCode, status, currentDocumentID, notes, updatedBy)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                currentDocumentID = VALUES(currentDocumentID),
                notes = VALUES(notes),
                updatedBy = VALUES(updatedBy),
                updatedOn = NOW()";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$staffID, $requirementCode, $status, $documentID, $notes, $updatedBy]);
    }

    /**
     * Get all role types for selection
     */
    public function getAllRoleTypes()
    {
        $sql = "SELECT * FROM cor4edu_staff_role_types WHERE active = 'Y' ORDER BY roleTypeName";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all programs for teaching assignments
     */
    public function getAllPrograms()
    {
        $sql = "SELECT programID, name, programCode FROM cor4edu_programs WHERE active = 'Y' ORDER BY name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if staff member has permission to access student tab
     */
    public function hasStudentTabAccess($staffID, $tabName)
    {
        // First check role-based default permissions
        $sql = "SELECT rt.defaultTabAccess, rt.isAdminRole
                FROM cor4edu_staff s
                JOIN cor4edu_staff_role_types rt ON s.roleTypeID = rt.roleTypeID
                WHERE s.staffID = ? AND s.active = 'Y' AND rt.active = 'Y'";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffID]);
        $role = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            return false;
        }

        // Admin roles have access to everything
        if ($role['isAdminRole'] === 'Y') {
            return true;
        }

        // Check if tab is in default access list
        $defaultAccess = json_decode($role['defaultTabAccess'], true);
        if (in_array($tabName, $defaultAccess)) {
            return true;
        }

        // Check for individual permission overrides
        $sql = "SELECT canView FROM cor4edu_staff_tab_access
                WHERE staffID = ? AND tabName = ?";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffID, $tabName]);
        $override = $stmt->fetch(PDO::FETCH_ASSOC);

        return $override ? ($override['canView'] === 'Y') : false;
    }

    /**
     * Get staff accessible student tabs
     */
    public function getStaffAccessibleTabs($staffID)
    {
        // Get staff info to check SuperAdmin status
        $sql = "SELECT isSuperAdmin FROM cor4edu_staff WHERE staffID = ? AND active = 'Y'";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$staffID]);
        $staff = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$staff) {
            // Staff not found, return basic access
            return ['information'];
        }

        // Super admins get access to everything
        if ($staff['isSuperAdmin'] === 'Y') {
            return ['information', 'admissions', 'bursar', 'registrar', 'academics', 'career', 'graduation'];
        }

        // Use permission system for regular staff - check each tab permission
        $accessibleTabs = [];

        // Information tab - most basic access
        if (hasPermission($staffID, 'students', 'view_information_tab')) {
            $accessibleTabs[] = 'information';
        }

        // Admissions tab
        if (hasPermission($staffID, 'students', 'view_admissions_tab')) {
            $accessibleTabs[] = 'admissions';
        }

        // Bursar/Financial tab
        if (hasPermission($staffID, 'students', 'view_bursar_tab')) {
            $accessibleTabs[] = 'bursar';
        }

        // Registrar tab
        if (hasPermission($staffID, 'students', 'view_registrar_tab')) {
            $accessibleTabs[] = 'registrar';
        }

        // Academics tab
        if (hasPermission($staffID, 'students', 'view_academics_tab')) {
            $accessibleTabs[] = 'academics';
        }

        // Career tab
        if (hasPermission($staffID, 'students', 'view_career_tab')) {
            $accessibleTabs[] = 'career';
        }

        // Graduation tab
        if (hasPermission($staffID, 'students', 'view_graduation_tab')) {
            $accessibleTabs[] = 'graduation';
        }

        // Ensure at least information tab is always available if they have any student access
        if (empty($accessibleTabs) && hasPermission($staffID, 'students', 'view_information_tab')) {
            $accessibleTabs = ['information'];
        }

        return $accessibleTabs;
    }
}
