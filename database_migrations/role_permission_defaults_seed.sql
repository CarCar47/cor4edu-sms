-- COR4EDU Role Permission Defaults Seed Data
-- Populates default permissions for each role type based on existing role structure
-- Run AFTER permissions_system_migration.sql

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Clear any existing role defaults (in case re-running)
DELETE FROM `cor4edu_role_permission_defaults`;

-- Role Type 1: Admissions
-- Access: information, admissions tabs
INSERT INTO `cor4edu_role_permission_defaults` (`roleTypeID`, `module`, `action`, `allowed`, `description`, `createdBy`) VALUES
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

-- Note: SuperAdmin (staffID=1, isSuperAdmin='Y') gets all permissions automatically
-- via code bypass - no need to define role defaults for SuperAdmin

COMMIT;