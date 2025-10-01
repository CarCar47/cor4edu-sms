-- ============================================================================
-- COR4EDU SMS Complete Database Schema
-- Consolidated schema with all required tables for Google Cloud deployment
-- ============================================================================
--
-- This file combines:
-- 1. Base schema (students, staff, programs, documents, payments, etc.)
-- 2. Role types system (staff roles: Admissions, Bursar, Registrar, etc.)
-- 3. Permission system (role defaults, system permissions, individual overrides)
--
-- Version: 1.0.0
-- Date: 2025-10-01
-- ============================================================================

USE `cor4edu_sms`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET FOREIGN_KEY_CHECKS = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- ============================================================================
-- SECTION 1: CORE TABLES
-- ============================================================================

-- Staff Table (replacing gibbonPerson for staff authentication)
CREATE TABLE IF NOT EXISTS `cor4edu_staff` (
  `staffID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staffCode` VARCHAR(20) NOT NULL,
  `firstName` VARCHAR(60) NOT NULL,
  `lastName` VARCHAR(60) NOT NULL,
  `email` VARCHAR(75),
  `username` VARCHAR(50),
  `passwordStrong` VARCHAR(255),
  `passwordStrongSalt` VARCHAR(255),
  `position` VARCHAR(100),
  `department` VARCHAR(100),
  `roleTypeID` INT(11) NULL COMMENT 'References cor4edu_staff_role_types',
  `phone` VARCHAR(20) NULL,
  `address` VARCHAR(255) NULL,
  `city` VARCHAR(100) NULL,
  `state` VARCHAR(50) NULL,
  `zipCode` VARCHAR(20) NULL,
  `country` VARCHAR(100) DEFAULT 'United States',
  `dateOfBirth` DATE NULL,
  `emergencyContact` VARCHAR(100) NULL,
  `emergencyPhone` VARCHAR(20) NULL,
  `teachingPrograms` JSON NULL COMMENT 'Programs this staff member teaches in',
  `notes` TEXT NULL COMMENT 'Administrative notes about this staff member',
  `startDate` DATE,
  `endDate` DATE,
  `active` ENUM('Y','N') DEFAULT 'Y',
  `isSuperAdmin` ENUM('Y','N') DEFAULT 'N',
  `canCreateAdmins` ENUM('Y','N') DEFAULT 'N',
  `lastLogin` TIMESTAMP NULL,
  `failedLogins` INT DEFAULT 0,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`staffID`),
  UNIQUE KEY `unique_staff_code` (`staffCode`),
  UNIQUE KEY `unique_staff_email` (`email`),
  UNIQUE KEY `unique_staff_username` (`username`),
  KEY `idx_staff_active` (`active`),
  KEY `idx_staff_role_type` (`roleTypeID`),
  KEY `idx_staff_active_role` (`active`, `roleTypeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Staff members with authentication and role assignments';

-- Students Table (Non-login profiles)
CREATE TABLE IF NOT EXISTS `cor4edu_students` (
  `studentID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentCode` VARCHAR(20) NOT NULL,
  `firstName` VARCHAR(60) NOT NULL,
  `lastName` VARCHAR(60) NOT NULL,
  `preferredName` VARCHAR(60),
  `email` VARCHAR(75),
  `phone` VARCHAR(20),
  `dateOfBirth` DATE,
  `gender` ENUM('Male','Female','Other','Unspecified'),
  `address` TEXT,
  `city` VARCHAR(60),
  `state` VARCHAR(60),
  `zipCode` VARCHAR(10),
  `country` VARCHAR(60),
  `programID` INT(10) UNSIGNED,
  `enrollmentDate` DATE,
  `graduationDate` DATE,
  `status` ENUM('prospective','active','withdrawn','graduated','alumni') DEFAULT 'prospective',
  `notes` TEXT,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`studentID`),
  UNIQUE KEY `unique_student_code` (`studentCode`),
  KEY `idx_student_status` (`status`),
  KEY `idx_student_program` (`programID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Student profiles and enrollment information';

-- Programs Table
CREATE TABLE IF NOT EXISTS `cor4edu_programs` (
  `programID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `programCode` VARCHAR(20) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `duration` VARCHAR(50),
  `creditHours` INT,
  `active` ENUM('Y','N') DEFAULT 'Y',
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`programID`),
  UNIQUE KEY `unique_program_code` (`programCode`),
  KEY `idx_program_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Academic programs offered by the institution';

-- Program Pricing
CREATE TABLE IF NOT EXISTS `cor4edu_program_pricing` (
  `priceID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `programID` INT(10) UNSIGNED NOT NULL,
  `studentType` ENUM('domestic','international') NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `effectiveDate` DATE NOT NULL,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`priceID`),
  KEY `idx_pricing_program` (`programID`),
  KEY `idx_pricing_date` (`effectiveDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Program pricing by student type and effective date';

-- ============================================================================
-- SECTION 2: ROLE TYPES SYSTEM
-- ============================================================================

-- Staff Role Types (for default permission templates)
CREATE TABLE IF NOT EXISTS `cor4edu_staff_role_types` (
    `roleTypeID` INT(11) NOT NULL AUTO_INCREMENT,
    `roleTypeName` VARCHAR(50) NOT NULL COMMENT 'Admissions, Bursar, Registrar, Career Services, Faculty, School Admin',
    `description` VARCHAR(255) NOT NULL,
    `defaultTabAccess` JSON NOT NULL COMMENT 'Default student tabs this role can access',
    `isAdminRole` ENUM('Y', 'N') NOT NULL DEFAULT 'N' COMMENT 'Can access admin functions',
    `canCreateStaff` ENUM('Y', 'N') NOT NULL DEFAULT 'N' COMMENT 'Can create other staff members',
    `active` ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
    `createdOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `createdBy` INT(11) NOT NULL,
    `modifiedOn` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    `modifiedBy` INT(11) NULL,
    PRIMARY KEY (`roleTypeID`),
    UNIQUE KEY `role_type_name` (`roleTypeName`),
    KEY `role_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Staff role types with default permission templates';

-- ============================================================================
-- SECTION 3: PERMISSION SYSTEM
-- ============================================================================

-- System Permission Registry Table
-- Master list of all available permissions in the system
CREATE TABLE IF NOT EXISTS `cor4edu_system_permissions` (
  `permissionID` INT(11) NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) NOT NULL DEFAULT 'general' COMMENT 'Groups permissions for UI organization',
  `requiresAdminRole` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Only available to admin roles',
  `displayOrder` INT(11) NOT NULL DEFAULT 1,
  `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `createdOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `createdBy` INT(11) NOT NULL,
  PRIMARY KEY (`permissionID`),
  UNIQUE KEY `unique_module_action` (`module`, `action`),
  KEY `idx_category` (`category`),
  KEY `idx_requires_admin` (`requiresAdminRole`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master registry of all available system permissions';

-- Role Permission Defaults Table
-- Stores what permissions each role gets by default when user is created
CREATE TABLE IF NOT EXISTS `cor4edu_role_permission_defaults` (
  `rolePermissionID` INT(11) NOT NULL AUTO_INCREMENT,
  `roleTypeID` INT(11) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `allowed` ENUM('Y','N') NOT NULL DEFAULT 'Y',
  `description` VARCHAR(255) DEFAULT NULL COMMENT 'Human readable description of this permission',
  `createdOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `createdBy` INT(11) NOT NULL,
  `modifiedOn` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  `modifiedBy` INT(11) NULL,
  PRIMARY KEY (`rolePermissionID`),
  UNIQUE KEY `unique_role_permission` (`roleTypeID`, `module`, `action`),
  KEY `idx_role_module` (`roleTypeID`, `module`),
  KEY `idx_module_action` (`module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Default permissions assigned when user with specific role is created';

-- Granular Individual Permissions Table (overrides role defaults)
CREATE TABLE IF NOT EXISTS `cor4edu_staff_permissions` (
  `permissionID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staffID` INT(10) UNSIGNED NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `allowed` ENUM('Y','N') DEFAULT 'N',
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permissionID`),
  UNIQUE KEY `unique_staff_permission` (`staffID`, `module`, `action`),
  KEY `idx_perm_staff` (`staffID`),
  KEY `idx_perm_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Individual staff permission overrides (supersede role defaults)';

-- Tab Access Permissions (legacy - may be deprecated)
CREATE TABLE IF NOT EXISTS `cor4edu_staff_tab_access` (
  `accessID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staffID` INT(10) UNSIGNED NOT NULL,
  `tabName` VARCHAR(50) NOT NULL,
  `canView` ENUM('Y','N') DEFAULT 'N',
  `canEdit` ENUM('Y','N') DEFAULT 'N',
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`accessID`),
  UNIQUE KEY `unique_staff_tab_access` (`staffID`, `tabName`),
  KEY `idx_tab_staff` (`staffID`),
  KEY `idx_tab_name` (`tabName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Legacy tab access permissions - use cor4edu_staff_permissions instead';

-- ============================================================================
-- SECTION 4: DOCUMENTS & FILES
-- ============================================================================

-- Documents Storage
CREATE TABLE IF NOT EXISTS `cor4edu_documents` (
  `documentID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entityType` ENUM('student','staff') NOT NULL,
  `entityID` INT(10) UNSIGNED NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `subcategory` VARCHAR(50),
  `fileName` VARCHAR(255) NOT NULL,
  `filePath` VARCHAR(500) NOT NULL,
  `fileSize` INT,
  `mimeType` VARCHAR(100),
  `uploadedBy` INT(10) UNSIGNED NOT NULL,
  `uploadedOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  `isArchived` ENUM('Y','N') DEFAULT 'N',
  `archivedBy` INT(10) UNSIGNED,
  `archivedOn` TIMESTAMP NULL,
  PRIMARY KEY (`documentID`),
  KEY `idx_doc_entity` (`entityType`, `entityID`),
  KEY `idx_doc_category` (`category`),
  KEY `idx_doc_uploader` (`uploadedBy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Document storage for students and staff';

-- ============================================================================
-- SECTION 5: STUDENT-RELATED TABLES
-- ============================================================================

-- Payment Records (Bursar Tab)
CREATE TABLE IF NOT EXISTS `cor4edu_payments` (
  `paymentID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `invoiceNumber` VARCHAR(50),
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `paymentDate` DATE,
  `paymentMethod` VARCHAR(50),
  `status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  `semester` VARCHAR(50),
  `academicYear` VARCHAR(20),
  `notes` TEXT,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`paymentID`),
  UNIQUE KEY `unique_invoice` (`invoiceNumber`),
  KEY `idx_payment_student` (`studentID`),
  KEY `idx_payment_status` (`status`),
  KEY `idx_payment_date` (`paymentDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Student payment records and financial transactions';

-- Academic Records (Registrar Tab)
CREATE TABLE IF NOT EXISTS `cor4edu_academic_records` (
  `recordID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `recordType` ENUM('transcript','grade','enrollment','graduation') NOT NULL,
  `semester` VARCHAR(50),
  `academicYear` VARCHAR(20),
  `gpa` DECIMAL(3,2),
  `credits` INT,
  `status` VARCHAR(50),
  `notes` TEXT,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`recordID`),
  KEY `idx_record_student` (`studentID`),
  KEY `idx_record_type` (`recordType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Student academic records and transcripts';

-- Career Services Records
CREATE TABLE IF NOT EXISTS `cor4edu_career_services` (
  `careerID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `employmentStatus` ENUM('job_seeking','job_placement_achieved','continuing_education') DEFAULT 'job_seeking',
  `employer` VARCHAR(100),
  `jobTitle` VARCHAR(100),
  `salary` DECIMAL(10,2),
  `startDate` DATE,
  `endDate` DATE,
  `notes` TEXT,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`careerID`),
  KEY `idx_career_student` (`studentID`),
  KEY `idx_career_status` (`employmentStatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Student career services and employment tracking';

-- ============================================================================
-- SECTION 6: SYSTEM TABLES
-- ============================================================================

-- System Sessions Table (for session management)
CREATE TABLE IF NOT EXISTS `cor4edu_sessions` (
  `sessionID` VARCHAR(128) NOT NULL,
  `staffID` INT(10) UNSIGNED,
  `sessionData` TEXT,
  `lastAccessed` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ipAddress` VARCHAR(45),
  `userAgent` TEXT,
  PRIMARY KEY (`sessionID`),
  KEY `idx_session_staff` (`staffID`),
  KEY `idx_session_accessed` (`lastAccessed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='User session management and tracking';

-- ============================================================================
-- SECTION 7: FOREIGN KEY CONSTRAINTS
-- ============================================================================

-- Add foreign key constraints for referential integrity
ALTER TABLE `cor4edu_program_pricing`
ADD CONSTRAINT `fk_pricing_program`
FOREIGN KEY (`programID`) REFERENCES `cor4edu_programs`(`programID`) ON DELETE CASCADE;

ALTER TABLE `cor4edu_staff`
ADD CONSTRAINT `fk_staff_role_type`
FOREIGN KEY (`roleTypeID`) REFERENCES `cor4edu_staff_role_types`(`roleTypeID`) ON DELETE SET NULL;

ALTER TABLE `cor4edu_role_permission_defaults`
ADD CONSTRAINT `fk_role_permission_role_type`
FOREIGN KEY (`roleTypeID`) REFERENCES `cor4edu_staff_role_types`(`roleTypeID`) ON DELETE CASCADE;

ALTER TABLE `cor4edu_staff_permissions`
ADD CONSTRAINT `fk_permission_staff`
FOREIGN KEY (`staffID`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE CASCADE;

ALTER TABLE `cor4edu_staff_tab_access`
ADD CONSTRAINT `fk_tab_access_staff`
FOREIGN KEY (`staffID`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE CASCADE;

ALTER TABLE `cor4edu_students`
ADD CONSTRAINT `fk_student_program`
FOREIGN KEY (`programID`) REFERENCES `cor4edu_programs`(`programID`);

ALTER TABLE `cor4edu_documents`
ADD CONSTRAINT `fk_document_uploader`
FOREIGN KEY (`uploadedBy`) REFERENCES `cor4edu_staff`(`staffID`);

ALTER TABLE `cor4edu_payments`
ADD CONSTRAINT `fk_payment_student`
FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`);

ALTER TABLE `cor4edu_academic_records`
ADD CONSTRAINT `fk_academic_student`
FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`);

ALTER TABLE `cor4edu_career_services`
ADD CONSTRAINT `fk_career_student`
FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`);

ALTER TABLE `cor4edu_sessions`
ADD CONSTRAINT `fk_session_staff`
FOREIGN KEY (`staffID`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE CASCADE;

-- ============================================================================
-- SECTION 8: SEED DATA - DEFAULT ROLE TYPES
-- ============================================================================

-- Insert default role types
INSERT INTO `cor4edu_staff_role_types` (`roleTypeName`, `description`, `defaultTabAccess`, `isAdminRole`, `canCreateStaff`, `createdBy`) VALUES
('Admissions', 'Admissions staff can access student information and admissions tabs', '["information", "admissions"]', 'N', 'N', 1),
('Bursar', 'Bursar staff can access student financial/bursar tab only', '["bursar"]', 'N', 'N', 1),
('Registrar', 'Registrar staff can access registrar and academic tabs', '["registrar", "academics"]', 'N', 'N', 1),
('Career Services', 'Career services staff can access career and graduation tabs', '["career", "graduation"]', 'N', 'N', 1),
('Faculty', 'Faculty can access academic and graduation tabs', '["academics", "graduation"]', 'N', 'N', 1),
('School Admin', 'School administrators have access to all tabs and admin functions', '["information", "admissions", "bursar", "registrar", "academics", "career", "graduation"]', 'Y', 'Y', 1)
ON DUPLICATE KEY UPDATE
  `description` = VALUES(`description`),
  `defaultTabAccess` = VALUES(`defaultTabAccess`),
  `isAdminRole` = VALUES(`isAdminRole`),
  `canCreateStaff` = VALUES(`canCreateStaff`);

-- ============================================================================
-- SECTION 9: SEED DATA - SYSTEM PERMISSIONS
-- ============================================================================

-- Insert system permission registry
INSERT INTO `cor4edu_system_permissions` (`module`, `action`, `name`, `description`, `category`, `requiresAdminRole`, `displayOrder`, `createdBy`) VALUES

-- Student Access Permissions (based on existing tab structure)
('students', 'view_information_tab', 'View Student Information', 'Access basic student information tab', 'student_access', 'N', 1, 1),
('students', 'edit_information_tab', 'Edit Student Information', 'Modify basic student information', 'student_access', 'N', 2, 1),
('students', 'view_admissions_tab', 'View Admissions Tab', 'Access student admissions information', 'student_access', 'N', 3, 1),
('students', 'edit_admissions_tab', 'Edit Admissions Tab', 'Modify admissions information', 'student_access', 'N', 4, 1),
('students', 'view_bursar_tab', 'View Bursar Tab', 'Access financial/payment information', 'student_access', 'N', 5, 1),
('students', 'edit_bursar_tab', 'Edit Bursar Tab', 'Modify financial information', 'student_access', 'N', 6, 1),
('students', 'view_registrar_tab', 'View Registrar Tab', 'Access registrar information', 'student_access', 'N', 7, 1),
('students', 'edit_registrar_tab', 'Edit Registrar Tab', 'Modify registrar information', 'student_access', 'N', 8, 1),
('students', 'view_academics_tab', 'View Academics Tab', 'Access academic records', 'student_access', 'N', 9, 1),
('students', 'edit_academics_tab', 'Edit Academics Tab', 'Modify academic records', 'student_access', 'N', 10, 1),
('students', 'view_career_tab', 'View Career Tab', 'Access career services information', 'student_access', 'N', 11, 1),
('students', 'edit_career_tab', 'Edit Career Tab', 'Modify career information', 'student_access', 'N', 12, 1),
('students', 'view_graduation_tab', 'View Graduation Tab', 'Access graduation information', 'student_access', 'N', 13, 1),
('students', 'edit_graduation_tab', 'Edit Graduation Tab', 'Modify graduation information', 'student_access', 'N', 14, 1),

-- Reports Module Permissions
('reports', 'view_reports_tab', 'View Reports Tab', 'Access to main reports navigation', 'reports', 'N', 1, 1),
('reports', 'generate_overview_reports', 'Generate Overview Reports', 'Create system overview reports', 'reports', 'N', 2, 1),
('reports', 'generate_admissions_reports', 'Generate Admissions Reports', 'Create admissions-related reports', 'reports', 'N', 3, 1),
('reports', 'generate_financial_reports', 'Generate Financial Reports', 'Create financial and payment reports', 'reports', 'N', 4, 1),
('reports', 'generate_academic_reports', 'Generate Academic Reports', 'Create academic progress reports', 'reports', 'N', 5, 1),
('reports', 'generate_career_reports', 'Generate Career Reports', 'Create career placement reports', 'reports', 'N', 6, 1),
('reports', 'export_reports_csv', 'Export Reports to CSV', 'Download reports in CSV format', 'reports', 'N', 7, 1),
('reports', 'export_reports_excel', 'Export Reports to Excel', 'Download reports in Excel format', 'reports', 'N', 8, 1),
('reports', 'view_financial_details', 'View Financial Details in Reports', 'Access sensitive financial data in reports', 'reports', 'N', 9, 1),

-- Staff Management Module (Admin only)
('staff', 'view_staff_list', 'View Staff List', 'Access staff directory', 'staff_management', 'Y', 1, 1),
('staff', 'create_staff', 'Create Staff Users', 'Add new staff members', 'staff_management', 'Y', 2, 1),
('staff', 'edit_staff', 'Edit Staff Information', 'Modify staff member details', 'staff_management', 'Y', 3, 1),
('staff', 'delete_staff', 'Delete Staff Users', 'Remove staff members from system', 'staff_management', 'Y', 4, 1),
('staff', 'manage_staff_permissions', 'Manage Staff Permissions', 'Set individual staff permissions', 'staff_management', 'Y', 5, 1),
('staff', 'view_staff_documents', 'View Staff Documents', 'Access staff document folders', 'staff_management', 'Y', 6, 1),
('staff', 'create_admin_accounts', 'Create Admin Accounts', 'Create new admin users (SuperAdmin only)', 'staff_management', 'Y', 7, 1),
('staff', 'manage_admin_permissions', 'Manage Admin Permissions', 'Set admin user permissions (SuperAdmin only)', 'staff_management', 'Y', 8, 1),

-- Program Management Module (Admin only)
('programs', 'view_programs', 'View Programs', 'Access program listings', 'program_management', 'Y', 1, 1),
('programs', 'create_programs', 'Create Programs', 'Add new educational programs', 'program_management', 'Y', 2, 1),
('programs', 'edit_programs', 'Edit Programs', 'Modify program details', 'program_management', 'Y', 3, 1),
('programs', 'delete_programs', 'Delete Programs', 'Remove programs from system', 'program_management', 'Y', 4, 1),

-- Permission Management Module (Admin only)
('permissions', 'manage_permissions', 'Manage Permissions', 'Access permission management interface', 'permission_management', 'Y', 1, 1),
('permissions', 'manage_role_defaults', 'Manage Role Defaults', 'Set default permissions for roles (SuperAdmin only)', 'permission_management', 'Y', 2, 1),
('permissions', 'manage_staff_permissions', 'Manage Individual Staff Permissions', 'Override permissions for specific staff', 'permission_management', 'Y', 3, 1),
('permissions', 'view_permission_reports', 'View Permission Reports', 'Access permission audit reports', 'permission_management', 'Y', 4, 1)

ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `description` = VALUES(`description`),
  `category` = VALUES(`category`),
  `requiresAdminRole` = VALUES(`requiresAdminRole`),
  `displayOrder` = VALUES(`displayOrder`);

-- ============================================================================
-- FINALIZE
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- Schema creation complete!
-- Remember to create the SuperAdmin user separately using seed_data.sql
