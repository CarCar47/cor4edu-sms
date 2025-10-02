-- Fix Cloud SQL Database - Add Missing Permission Tables
-- Run this file via: gcloud sql import sql sms-edu-db gs://BUCKET/fix_permission_tables.sql --database=cor4edu_sms

USE cor4edu_sms;

-- Create cor4edu_staff_role_types table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create cor4edu_system_permissions table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create cor4edu_role_permission_defaults table
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert staff role types
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

-- Insert system permissions
INSERT INTO `cor4edu_system_permissions` (`module`, `action`, `name`, `description`, `category`, `requiresAdminRole`, `displayOrder`, `createdBy`) VALUES
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
('reports', 'view_reports_tab', 'View Reports Tab', 'Access to main reports navigation', 'reports', 'N', 1, 1),
('reports', 'generate_overview_reports', 'Generate Overview Reports', 'Create system overview reports', 'reports', 'N', 2, 1),
('reports', 'generate_admissions_reports', 'Generate Admissions Reports', 'Create admissions-related reports', 'reports', 'N', 3, 1),
('reports', 'generate_financial_reports', 'Generate Financial Reports', 'Create financial and payment reports', 'reports', 'N', 4, 1),
('reports', 'generate_academic_reports', 'Generate Academic Reports', 'Create academic progress reports', 'reports', 'N', 5, 1),
('reports', 'generate_career_reports', 'Generate Career Reports', 'Create career placement reports', 'reports', 'N', 6, 1),
('reports', 'export_reports_csv', 'Export Reports to CSV', 'Download reports in CSV format', 'reports', 'N', 7, 1),
('reports', 'export_reports_excel', 'Export Reports to Excel', 'Download reports in Excel format', 'reports', 'N', 8, 1),
('reports', 'view_financial_details', 'View Financial Details in Reports', 'Access sensitive financial data in reports', 'reports', 'N', 9, 1),
('staff', 'view_staff_list', 'View Staff List', 'Access staff directory', 'staff_management', 'Y', 1, 1),
('staff', 'create_staff', 'Create Staff Users', 'Add new staff members', 'staff_management', 'Y', 2, 1),
('staff', 'edit_staff', 'Edit Staff Information', 'Modify staff member details', 'staff_management', 'Y', 3, 1),
('staff', 'delete_staff', 'Delete Staff Users', 'Remove staff members from system', 'staff_management', 'Y', 4, 1),
('staff', 'manage_staff_permissions', 'Manage Staff Permissions', 'Set individual staff permissions', 'staff_management', 'Y', 5, 1),
('staff', 'view_staff_documents', 'View Staff Documents', 'Access staff document folders', 'staff_management', 'Y', 6, 1),
('staff', 'create_admin_accounts', 'Create Admin Accounts', 'Create new admin users (SuperAdmin only)', 'staff_management', 'Y', 7, 1),
('staff', 'manage_admin_permissions', 'Manage Admin Permissions', 'Set admin user permissions (SuperAdmin only)', 'staff_management', 'Y', 8, 1),
('programs', 'view_programs', 'View Programs', 'Access program listings', 'program_management', 'Y', 1, 1),
('programs', 'create_programs', 'Create Programs', 'Add new educational programs', 'program_management', 'Y', 2, 1),
('programs', 'edit_programs', 'Edit Programs', 'Modify program details', 'program_management', 'Y', 3, 1),
('programs', 'delete_programs', 'Delete Programs', 'Remove programs from system', 'program_management', 'Y', 4, 1),
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
