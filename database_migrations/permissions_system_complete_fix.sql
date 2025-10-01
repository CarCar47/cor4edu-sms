-- COR4EDU Permissions System Complete Fix
-- This file completely fixes all database constraint issues and implements the full permission system
-- Import this single file via phpMyAdmin to fix everything

USE `cor4edu_sms`;

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ========================================
-- STEP 1: Clean up existing broken tables
-- ========================================

-- Drop foreign key constraints first if they exist
SET foreign_key_checks = 0;

-- Drop the broken tables completely
DROP TABLE IF EXISTS `cor4edu_role_permission_defaults`;
DROP TABLE IF EXISTS `cor4edu_system_permissions`;

SET foreign_key_checks = 1;

-- ========================================
-- STEP 2: Create tables with CORRECT data types
-- ========================================

-- Role Permission Defaults Table (CORRECTED DATA TYPES)
CREATE TABLE `cor4edu_role_permission_defaults` (
  `rolePermissionID` int(11) NOT NULL AUTO_INCREMENT,
  `roleTypeID` int(11) NOT NULL COMMENT 'References cor4edu_staff_role_types.roleTypeID',
  `module` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `allowed` enum('Y','N') NOT NULL DEFAULT 'Y',
  `description` varchar(255) DEFAULT NULL COMMENT 'Human readable description of this permission',
  `createdOn` datetime NOT NULL DEFAULT current_timestamp(),
  `createdBy` int(10) UNSIGNED NOT NULL COMMENT 'References cor4edu_staff.staffID',
  `modifiedOn` datetime NULL ON UPDATE current_timestamp(),
  `modifiedBy` int(10) UNSIGNED NULL COMMENT 'References cor4edu_staff.staffID',
  PRIMARY KEY (`rolePermissionID`),
  UNIQUE KEY `unique_role_permission` (`roleTypeID`, `module`, `action`),
  KEY `idx_role_module` (`roleTypeID`, `module`),
  KEY `idx_module_action` (`module`, `action`),
  CONSTRAINT `fk_role_permission_role_type`
    FOREIGN KEY (`roleTypeID`) REFERENCES `cor4edu_staff_role_types`(`roleTypeID`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permission_created_by`
    FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`),
  CONSTRAINT `fk_role_permission_modified_by`
    FOREIGN KEY (`modifiedBy`) REFERENCES `cor4edu_staff`(`staffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Default permissions assigned when user with specific role is created';

-- System Permission Registry Table (CORRECTED DATA TYPES)
CREATE TABLE `cor4edu_system_permissions` (
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
  `createdBy` int(10) UNSIGNED NOT NULL COMMENT 'References cor4edu_staff.staffID',
  PRIMARY KEY (`permissionID`),
  UNIQUE KEY `unique_module_action` (`module`, `action`),
  KEY `idx_category` (`category`),
  KEY `idx_requires_admin` (`requiresAdminRole`),
  CONSTRAINT `fk_system_permission_created_by`
    FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master registry of all available system permissions';

-- ========================================
-- STEP 3: Populate System Permissions Registry
-- ========================================

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

-- ========================================
-- STEP 4: Populate Role Permission Defaults for All 6 Role Types
-- ========================================

INSERT INTO `cor4edu_role_permission_defaults` (`roleTypeID`, `module`, `action`, `allowed`, `description`, `createdBy`) VALUES

-- Role Type 1: Admissions
-- Access: information, admissions tabs
(1, 'students', 'view_information_tab', 'Y', 'Admissions staff can view basic student information', 1),
(1, 'students', 'edit_information_tab', 'Y', 'Admissions staff can edit basic student information', 1),
(1, 'students', 'view_admissions_tab', 'Y', 'Admissions staff have full access to admissions tab', 1),
(1, 'students', 'edit_admissions_tab', 'Y', 'Admissions staff can edit admissions information', 1),
(1, 'students', 'view_bursar_tab', 'N', 'Admissions staff cannot access financial information', 1),
(1, 'students', 'view_registrar_tab', 'N', 'Admissions staff cannot access registrar information', 1),
(1, 'students', 'view_academics_tab', 'N', 'Admissions staff cannot access academic records', 1),
(1, 'students', 'view_career_tab', 'N', 'Admissions staff cannot access career information', 1),
(1, 'students', 'view_graduation_tab', 'N', 'Admissions staff cannot access graduation information', 1),
(1, 'reports', 'view_reports_tab', 'N', 'Admissions staff cannot access reports tab by default', 1),
(1, 'reports', 'generate_admissions_reports', 'Y', 'Admissions staff can generate admissions-specific reports', 1),

-- Role Type 2: Bursar
-- Access: information, bursar tabs
(2, 'students', 'view_information_tab', 'Y', 'Bursar staff can view basic student information', 1),
(2, 'students', 'edit_information_tab', 'N', 'Bursar staff cannot edit basic information', 1),
(2, 'students', 'view_admissions_tab', 'N', 'Bursar staff cannot access admissions information', 1),
(2, 'students', 'view_bursar_tab', 'Y', 'Bursar staff have full access to financial information', 1),
(2, 'students', 'edit_bursar_tab', 'Y', 'Bursar staff can edit financial information', 1),
(2, 'students', 'view_registrar_tab', 'N', 'Bursar staff cannot access registrar information', 1),
(2, 'students', 'view_academics_tab', 'N', 'Bursar staff cannot access academic records', 1),
(2, 'students', 'view_career_tab', 'N', 'Bursar staff cannot access career information', 1),
(2, 'students', 'view_graduation_tab', 'N', 'Bursar staff cannot access graduation information', 1),
(2, 'reports', 'view_reports_tab', 'N', 'Bursar staff cannot access reports tab by default', 1),
(2, 'reports', 'generate_financial_reports', 'Y', 'Bursar staff can generate financial reports', 1),

-- Role Type 3: Registrar
-- Access: information, admissions, bursar, graduation tabs
(3, 'students', 'view_information_tab', 'Y', 'Registrar staff can view basic student information', 1),
(3, 'students', 'edit_information_tab', 'Y', 'Registrar staff can edit basic student information', 1),
(3, 'students', 'view_admissions_tab', 'Y', 'Registrar staff can view admissions information', 1),
(3, 'students', 'edit_admissions_tab', 'N', 'Registrar staff cannot edit admissions information', 1),
(3, 'students', 'view_bursar_tab', 'Y', 'Registrar staff can view financial information', 1),
(3, 'students', 'edit_bursar_tab', 'N', 'Registrar staff cannot edit financial information', 1),
(3, 'students', 'view_registrar_tab', 'Y', 'Registrar staff have full access to registrar information', 1),
(3, 'students', 'edit_registrar_tab', 'Y', 'Registrar staff can edit registrar information', 1),
(3, 'students', 'view_academics_tab', 'N', 'Registrar staff cannot access academic records by default', 1),
(3, 'students', 'view_career_tab', 'N', 'Registrar staff cannot access career information', 1),
(3, 'students', 'view_graduation_tab', 'Y', 'Registrar staff can access graduation information', 1),
(3, 'students', 'edit_graduation_tab', 'Y', 'Registrar staff can edit graduation information', 1),
(3, 'reports', 'view_reports_tab', 'N', 'Registrar staff cannot access reports tab by default', 1),
(3, 'reports', 'generate_academic_reports', 'Y', 'Registrar staff can generate academic reports', 1),

-- Role Type 4: Career Services
-- Access: information, career tabs
(4, 'students', 'view_information_tab', 'Y', 'Career Services staff can view basic student information', 1),
(4, 'students', 'edit_information_tab', 'N', 'Career Services staff cannot edit basic information', 1),
(4, 'students', 'view_admissions_tab', 'N', 'Career Services staff cannot access admissions information', 1),
(4, 'students', 'view_bursar_tab', 'N', 'Career Services staff cannot access financial information', 1),
(4, 'students', 'view_registrar_tab', 'N', 'Career Services staff cannot access registrar information', 1),
(4, 'students', 'view_academics_tab', 'N', 'Career Services staff cannot access academic records', 1),
(4, 'students', 'view_career_tab', 'Y', 'Career Services staff have full access to career information', 1),
(4, 'students', 'edit_career_tab', 'Y', 'Career Services staff can edit career information', 1),
(4, 'students', 'view_graduation_tab', 'N', 'Career Services staff cannot access graduation information', 1),
(4, 'reports', 'view_reports_tab', 'N', 'Career Services staff cannot access reports tab by default', 1),
(4, 'reports', 'generate_career_reports', 'Y', 'Career Services staff can generate career reports', 1),

-- Role Type 5: Faculty
-- Access: information, academics tabs
(5, 'students', 'view_information_tab', 'Y', 'Faculty can view basic student information', 1),
(5, 'students', 'edit_information_tab', 'N', 'Faculty cannot edit basic student information', 1),
(5, 'students', 'view_admissions_tab', 'N', 'Faculty cannot access admissions information', 1),
(5, 'students', 'view_bursar_tab', 'N', 'Faculty cannot access financial information', 1),
(5, 'students', 'view_registrar_tab', 'N', 'Faculty cannot access registrar information', 1),
(5, 'students', 'view_academics_tab', 'Y', 'Faculty have full access to academic records', 1),
(5, 'students', 'edit_academics_tab', 'Y', 'Faculty can edit academic records', 1),
(5, 'students', 'view_career_tab', 'N', 'Faculty cannot access career information', 1),
(5, 'students', 'view_graduation_tab', 'N', 'Faculty cannot access graduation information', 1),
(5, 'reports', 'view_reports_tab', 'N', 'Faculty cannot access reports tab by default', 1),
(5, 'reports', 'generate_academic_reports', 'N', 'Faculty cannot generate reports by default (can be overridden)', 1),

-- Role Type 6: School Admin
-- Access: all tabs + admin functions (everything except SuperAdmin control)
(6, 'students', 'view_information_tab', 'Y', 'School Admin has full student access', 1),
(6, 'students', 'edit_information_tab', 'Y', 'School Admin can edit all student information', 1),
(6, 'students', 'view_admissions_tab', 'Y', 'School Admin has full admissions access', 1),
(6, 'students', 'edit_admissions_tab', 'Y', 'School Admin can edit admissions information', 1),
(6, 'students', 'view_bursar_tab', 'Y', 'School Admin has full financial access', 1),
(6, 'students', 'edit_bursar_tab', 'Y', 'School Admin can edit financial information', 1),
(6, 'students', 'view_registrar_tab', 'Y', 'School Admin has full registrar access', 1),
(6, 'students', 'edit_registrar_tab', 'Y', 'School Admin can edit registrar information', 1),
(6, 'students', 'view_academics_tab', 'Y', 'School Admin has full academic access', 1),
(6, 'students', 'edit_academics_tab', 'Y', 'School Admin can edit academic records', 1),
(6, 'students', 'view_career_tab', 'Y', 'School Admin has full career access', 1),
(6, 'students', 'edit_career_tab', 'Y', 'School Admin can edit career information', 1),
(6, 'students', 'view_graduation_tab', 'Y', 'School Admin has full graduation access', 1),
(6, 'students', 'edit_graduation_tab', 'Y', 'School Admin can edit graduation information', 1),

-- School Admin: Staff Management (but NOT admin management)
(6, 'staff', 'view_staff_list', 'Y', 'School Admin can view staff directory', 1),
(6, 'staff', 'create_staff', 'Y', 'School Admin can create staff users', 1),
(6, 'staff', 'edit_staff', 'Y', 'School Admin can edit staff information', 1),
(6, 'staff', 'delete_staff', 'Y', 'School Admin can delete staff users', 1),
(6, 'staff', 'manage_staff_permissions', 'Y', 'School Admin can manage staff permissions', 1),
(6, 'staff', 'view_staff_documents', 'Y', 'School Admin can view staff documents', 1),
(6, 'staff', 'create_admin_accounts', 'N', 'Only SuperAdmin can create admin accounts', 1),
(6, 'staff', 'manage_admin_permissions', 'N', 'Only SuperAdmin can manage admin permissions', 1),

-- School Admin: Program Management
(6, 'programs', 'view_programs', 'Y', 'School Admin has full program access', 1),
(6, 'programs', 'create_programs', 'Y', 'School Admin can create programs', 1),
(6, 'programs', 'edit_programs', 'Y', 'School Admin can edit programs', 1),
(6, 'programs', 'delete_programs', 'Y', 'School Admin can delete programs', 1),

-- School Admin: Reports
(6, 'reports', 'view_reports_tab', 'Y', 'School Admin has full reports access', 1),
(6, 'reports', 'generate_overview_reports', 'Y', 'School Admin can generate overview reports', 1),
(6, 'reports', 'generate_admissions_reports', 'Y', 'School Admin can generate admissions reports', 1),
(6, 'reports', 'generate_financial_reports', 'Y', 'School Admin can generate financial reports', 1),
(6, 'reports', 'generate_academic_reports', 'Y', 'School Admin can generate academic reports', 1),
(6, 'reports', 'generate_career_reports', 'Y', 'School Admin can generate career reports', 1),
(6, 'reports', 'export_reports_csv', 'Y', 'School Admin can export CSV reports', 1),
(6, 'reports', 'export_reports_excel', 'Y', 'School Admin can export Excel reports', 1),

-- School Admin: Permission Management (limited scope)
(6, 'permissions', 'manage_permissions', 'Y', 'School Admin can access permission management', 1),
(6, 'permissions', 'manage_role_defaults', 'N', 'Only SuperAdmin can manage role defaults', 1),
(6, 'permissions', 'manage_staff_permissions', 'Y', 'School Admin can manage individual staff permissions', 1),
(6, 'permissions', 'view_permission_reports', 'Y', 'School Admin can view permission reports', 1);

-- ========================================
-- FINAL COMMIT
-- ========================================

COMMIT;

-- Success message
SELECT 'Permission system successfully installed!' as message,
       COUNT(*) as system_permissions_count
FROM cor4edu_system_permissions;

SELECT 'Role defaults successfully populated!' as message,
       COUNT(*) as role_defaults_count
FROM cor4edu_role_permission_defaults;