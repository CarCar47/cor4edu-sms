-- COR4EDU Staff Management System Migration
-- Creates enhanced staff profile system with role-based permissions and document management

-- Staff Role Types (for default permission templates)
CREATE TABLE IF NOT EXISTS `cor4edu_staff_role_types` (
    `roleTypeID` int(11) NOT NULL AUTO_INCREMENT,
    `roleTypeName` varchar(50) NOT NULL COMMENT 'Admissions, Bursar, Registrar, Career Services, Faculty, School Admin',
    `description` varchar(255) NOT NULL,
    `defaultTabAccess` JSON NOT NULL COMMENT 'Default student tabs this role can access',
    `isAdminRole` ENUM('Y', 'N') NOT NULL DEFAULT 'N' COMMENT 'Can access admin functions',
    `canCreateStaff` ENUM('Y', 'N') NOT NULL DEFAULT 'N' COMMENT 'Can create other staff members',
    `active` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
    `createdOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `createdBy` int(11) NOT NULL,
    `modifiedOn` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    `modifiedBy` int(11) NULL,
    PRIMARY KEY (`roleTypeID`),
    UNIQUE KEY `role_type_name` (`roleTypeName`),
    KEY `role_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Staff role types with default permission templates';

-- Add role type to staff table
ALTER TABLE `cor4edu_staff`
ADD COLUMN `roleTypeID` int(11) NULL AFTER `department`,
ADD COLUMN `phone` varchar(20) NULL AFTER `email`,
ADD COLUMN `address` varchar(255) NULL AFTER `phone`,
ADD COLUMN `city` varchar(100) NULL AFTER `address`,
ADD COLUMN `state` varchar(50) NULL AFTER `city`,
ADD COLUMN `zipCode` varchar(20) NULL AFTER `state`,
ADD COLUMN `country` varchar(100) NULL DEFAULT 'United States' AFTER `zipCode`,
ADD COLUMN `dateOfBirth` DATE NULL AFTER `country`,
ADD COLUMN `emergencyContact` varchar(100) NULL AFTER `dateOfBirth`,
ADD COLUMN `emergencyPhone` varchar(20) NULL AFTER `emergencyContact`,
ADD COLUMN `teachingPrograms` JSON NULL COMMENT 'Programs this staff member teaches in' AFTER `emergencyPhone`,
ADD COLUMN `notes` TEXT NULL COMMENT 'Administrative notes about this staff member' AFTER `teachingPrograms`,
ADD FOREIGN KEY (`roleTypeID`) REFERENCES `cor4edu_staff_role_types`(`roleTypeID`) ON DELETE SET NULL;

-- Staff Document Requirements Categories
CREATE TABLE IF NOT EXISTS `cor4edu_staff_document_requirements` (
    `requirementID` int(11) NOT NULL AUTO_INCREMENT,
    `requirementCode` varchar(50) NOT NULL COMMENT 'resume, cie_documents, professional_licenses, official_transcripts, continuing_education',
    `name` varchar(100) NOT NULL,
    `description` varchar(255) NOT NULL,
    `required` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
    `renewalRequired` ENUM('Y', 'N') NOT NULL DEFAULT 'N' COMMENT 'Requires periodic renewal',
    `renewalPeriodMonths` int(11) NULL COMMENT 'How often renewal is required in months',
    `displayOrder` int(11) NOT NULL DEFAULT 1,
    `active` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
    `createdOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `createdBy` int(11) NOT NULL,
    PRIMARY KEY (`requirementID`),
    UNIQUE KEY `requirement_code` (`requirementCode`),
    KEY `requirement_active` (`active`),
    KEY `requirement_order` (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Staff document requirements definitions';

-- Modify documents table to support staff subcategories
ALTER TABLE `cor4edu_documents`
ADD COLUMN `subcategory` varchar(50) NULL COMMENT 'For required vs other documents' AFTER `category`,
ADD COLUMN `expirationDate` DATE NULL COMMENT 'For documents that expire' AFTER `subcategory`,
ADD COLUMN `replacesDocumentID` int(11) NULL COMMENT 'If this document replaces another' AFTER `expirationDate`,
ADD FOREIGN KEY (`replacesDocumentID`) REFERENCES `cor4edu_documents`(`documentID`) ON DELETE SET NULL;

-- Staff Document Status Tracking (for required documents)
CREATE TABLE IF NOT EXISTS `cor4edu_staff_document_status` (
    `statusID` int(11) NOT NULL AUTO_INCREMENT,
    `staffID` int(11) NOT NULL,
    `requirementCode` varchar(50) NOT NULL,
    `status` ENUM('missing', 'submitted', 'approved', 'expired', 'rejected') NOT NULL DEFAULT 'missing',
    `currentDocumentID` int(11) NULL COMMENT 'Current active document for this requirement',
    `dueDate` DATE NULL COMMENT 'When this document is due',
    `lastReminderSent` DATETIME NULL,
    `notes` TEXT NULL,
    `updatedOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `updatedBy` int(11) NOT NULL,
    PRIMARY KEY (`statusID`),
    UNIQUE KEY `staff_requirement` (`staffID`, `requirementCode`),
    FOREIGN KEY (`staffID`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE CASCADE,
    FOREIGN KEY (`requirementCode`) REFERENCES `cor4edu_staff_document_requirements`(`requirementCode`) ON DELETE CASCADE,
    FOREIGN KEY (`currentDocumentID`) REFERENCES `cor4edu_documents`(`documentID`) ON DELETE SET NULL,
    KEY `status_staff` (`staffID`),
    KEY `status_requirement` (`requirementCode`),
    KEY `status_due_date` (`dueDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Tracks status of required documents for each staff member';

-- Insert default role types
INSERT INTO `cor4edu_staff_role_types` (`roleTypeName`, `description`, `defaultTabAccess`, `isAdminRole`, `canCreateStaff`, `createdBy`) VALUES
('Admissions', 'Admissions staff can access student information and admissions tabs', '["information", "admissions"]', 'N', 'N', 1),
('Bursar', 'Bursar staff can access student financial/bursar tab only', '["bursar"]', 'N', 'N', 1),
('Registrar', 'Registrar staff can access registrar and academic tabs', '["registrar", "academics"]', 'N', 'N', 1),
('Career Services', 'Career services staff can access career and graduation tabs', '["career", "graduation"]', 'N', 'N', 1),
('Faculty', 'Faculty can access academic and graduation tabs', '["academics", "graduation"]', 'N', 'N', 1),
('School Admin', 'School administrators have access to all tabs and admin functions', '["information", "admissions", "bursar", "registrar", "academics", "career", "graduation"]', 'Y', 'Y', 1);

-- Insert default document requirements
INSERT INTO `cor4edu_staff_document_requirements` (`requirementCode`, `name`, `description`, `required`, `renewalRequired`, `renewalPeriodMonths`, `displayOrder`, `createdBy`) VALUES
('resume', 'Current Resume', 'Up-to-date professional resume', 'Y', 'N', NULL, 1, 1),
('cie_documents', 'CIE Documents', 'Continuing and Institutional Education certification documents', 'Y', 'Y', 12, 2, 1),
('professional_licenses', 'Professional Licenses', 'Current professional licenses and certifications', 'Y', 'Y', 24, 3, 1),
('official_transcripts', 'Official Transcripts', 'Official academic transcripts from educational institutions', 'Y', 'N', NULL, 4, 1),
('continuing_education', 'Continuing Education', 'Annual continuing education documentation (8 hours minimum per CIE rules)', 'Y', 'Y', 12, 5, 1);

-- Add indexes for better performance
CREATE INDEX `idx_staff_role_type` ON `cor4edu_staff` (`roleTypeID`);
CREATE INDEX `idx_staff_active_role` ON `cor4edu_staff` (`active`, `roleTypeID`);
CREATE INDEX `idx_documents_staff_category` ON `cor4edu_documents` (`entityType`, `entityID`, `category`) WHERE `entityType` = 'staff';
CREATE INDEX `idx_staff_document_status_due` ON `cor4edu_staff_document_status` (`status`, `dueDate`);