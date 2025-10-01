-- COR4EDU Permissions System Migration (Fixed)
-- Creates comprehensive permission management with role defaults + individual overrides
-- Import this file via phpMyAdmin

-- Select the database first
USE `cor4edu_sms`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Role Permission Defaults Table
-- Stores what permissions each role gets by default when user is created
CREATE TABLE IF NOT EXISTS `cor4edu_role_permission_defaults` (
  `rolePermissionID` int(11) NOT NULL AUTO_INCREMENT,
  `roleTypeID` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `allowed` enum('Y','N') NOT NULL DEFAULT 'Y',
  `description` varchar(255) DEFAULT NULL COMMENT 'Human readable description of this permission',
  `createdOn` datetime NOT NULL DEFAULT current_timestamp(),
  `createdBy` int(11) NOT NULL,
  `modifiedOn` datetime NULL ON UPDATE current_timestamp(),
  `modifiedBy` int(11) NULL,
  PRIMARY KEY (`rolePermissionID`),
  UNIQUE KEY `unique_role_permission` (`roleTypeID`, `module`, `action`),
  KEY `idx_role_module` (`roleTypeID`, `module`),
  KEY `idx_module_action` (`module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Default permissions assigned when user with specific role is created';

-- System Permission Registry Table
-- Master list of all available permissions in the system
CREATE TABLE IF NOT EXISTS `cor4edu_system_permissions` (
  `permissionID` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) NOT NULL,
  `category` varchar(50) NOT NULL DEFAULT 'general' COMMENT 'Groups permissions for UI organization',
  `requiresAdminRole` enum('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Only available to admin roles',
  `displayOrder` int(11) NOT NULL DEFAULT 1,
  `active` enum('Y','N') NOT NULL DEFAULT 'Y',
  `createdOn` datetime NOT NULL DEFAULT current_timestamp(),
  `createdBy` int(11) NOT NULL,
  PRIMARY KEY (`permissionID`),
  UNIQUE KEY `unique_module_action` (`module`, `action`),
  KEY `idx_category` (`category`),
  KEY `idx_requires_admin` (`requiresAdminRole`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master registry of all available system permissions';

-- Add foreign key constraint for role permission defaults
ALTER TABLE `cor4edu_role_permission_defaults`
  ADD CONSTRAINT `fk_role_permission_role_type`
  FOREIGN KEY (`roleTypeID`) REFERENCES `cor4edu_staff_role_types`(`roleTypeID`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_role_permission_created_by`
  FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`),
  ADD CONSTRAINT `fk_role_permission_modified_by`
  FOREIGN KEY (`modifiedBy`) REFERENCES `cor4edu_staff`(`staffID`);

-- Add foreign key constraint for system permissions
ALTER TABLE `cor4edu_system_permissions`
  ADD CONSTRAINT `fk_system_permission_created_by`
  FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`);

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
('permissions', 'view_permission_reports', 'View Permission Reports', 'Access permission audit reports', 'permission_management', 'Y', 4, 1);

-- Set AUTO_INCREMENT values
ALTER TABLE `cor4edu_role_permission_defaults` AUTO_INCREMENT = 1;
ALTER TABLE `cor4edu_system_permissions` AUTO_INCREMENT = 1;

COMMIT;