-- COR4EDU SMS Initial Data
-- Creates superadmin user and basic system data

-- Insert SuperAdmin User
INSERT INTO `cor4edu_staff` (
    `staffCode`,
    `firstName`,
    `lastName`,
    `email`,
    `username`,
    `passwordStrong`,
    `passwordStrongSalt`,
    `position`,
    `department`,
    `startDate`,
    `active`,
    `isSuperAdmin`,
    `canCreateAdmins`,
    `createdBy`,
    `createdOn`
) VALUES (
    'ADMIN001',
    'Super',
    'Admin',
    'admin@cor4edu.com',
    'superadmin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    'cor4edu_salt_2025',
    'System Administrator',
    'IT Administration',
    CURDATE(),
    'Y',
    'Y',
    'Y',
    1,
    NOW()
);

-- Get the staffID of the created superadmin
SET @superAdminID = LAST_INSERT_ID();

-- Insert Full Permissions for SuperAdmin (all modules and actions)
INSERT INTO `cor4edu_staff_permissions` (`staffID`, `module`, `action`, `allowed`, `createdBy`, `createdOn`) VALUES
(@superAdminID, 'students', 'view', 'Y', @superAdminID, NOW()),
(@superAdminID, 'students', 'create', 'Y', @superAdminID, NOW()),
(@superAdminID, 'students', 'edit', 'Y', @superAdminID, NOW()),
(@superAdminID, 'students', 'delete', 'Y', @superAdminID, NOW()),
(@superAdminID, 'staff', 'view', 'Y', @superAdminID, NOW()),
(@superAdminID, 'staff', 'create', 'Y', @superAdminID, NOW()),
(@superAdminID, 'staff', 'edit', 'Y', @superAdminID, NOW()),
(@superAdminID, 'staff', 'delete', 'Y', @superAdminID, NOW()),
(@superAdminID, 'programs', 'view', 'Y', @superAdminID, NOW()),
(@superAdminID, 'programs', 'create', 'Y', @superAdminID, NOW()),
(@superAdminID, 'programs', 'edit', 'Y', @superAdminID, NOW()),
(@superAdminID, 'programs', 'delete', 'Y', @superAdminID, NOW()),
(@superAdminID, 'reports', 'view', 'Y', @superAdminID, NOW()),
(@superAdminID, 'reports', 'generate', 'Y', @superAdminID, NOW()),
(@superAdminID, 'documents', 'view', 'Y', @superAdminID, NOW()),
(@superAdminID, 'documents', 'upload', 'Y', @superAdminID, NOW()),
(@superAdminID, 'documents', 'delete', 'Y', @superAdminID, NOW()),
(@superAdminID, 'permissions', 'manage', 'Y', @superAdminID, NOW());

-- Insert Full Tab Access for SuperAdmin (all student profile tabs)
INSERT INTO `cor4edu_staff_tab_access` (`staffID`, `tabName`, `canView`, `canEdit`, `createdBy`, `createdOn`) VALUES
(@superAdminID, 'information', 'Y', 'Y', @superAdminID, NOW()),
(@superAdminID, 'admissions', 'Y', 'Y', @superAdminID, NOW()),
(@superAdminID, 'bursar', 'Y', 'Y', @superAdminID, NOW()),
(@superAdminID, 'registrar', 'Y', 'Y', @superAdminID, NOW()),
(@superAdminID, 'career', 'Y', 'Y', @superAdminID, NOW()),
(@superAdminID, 'academics', 'Y', 'Y', @superAdminID, NOW()),
(@superAdminID, 'graduation', 'Y', 'Y', @superAdminID, NOW()),
(@superAdminID, 'staff_folder', 'Y', 'Y', @superAdminID, NOW());

-- Insert Sample Program
INSERT INTO `cor4edu_programs` (
    `programCode`,
    `name`,
    `description`,
    `duration`,
    `creditHours`,
    `active`,
    `createdBy`,
    `createdOn`
) VALUES (
    'PROG001',
    'General Studies Program',
    'A comprehensive general studies program covering various academic disciplines.',
    '2 Years',
    60,
    'Y',
    @superAdminID,
    NOW()
);

-- Get the programID for pricing
SET @programID = LAST_INSERT_ID();

-- Insert Sample Program Pricing
INSERT INTO `cor4edu_program_pricing` (
    `programID`,
    `studentType`,
    `price`,
    `currency`,
    `effectiveDate`,
    `createdBy`,
    `createdOn`
) VALUES
(@programID, 'domestic', 15000.00, 'USD', CURDATE(), @superAdminID, NOW()),
(@programID, 'international', 25000.00, 'USD', CURDATE(), @superAdminID, NOW());

-- Insert Sample Student for Testing
INSERT INTO `cor4edu_students` (
    `studentCode`,
    `firstName`,
    `lastName`,
    `email`,
    `phone`,
    `dateOfBirth`,
    `gender`,
    `address`,
    `city`,
    `state`,
    `zipCode`,
    `country`,
    `programID`,
    `enrollmentDate`,
    `status`,
    `notes`,
    `createdBy`,
    `createdOn`
) VALUES (
    'STU001',
    'John',
    'Doe',
    'john.doe@student.cor4edu.com',
    '(555) 123-4567',
    '1995-06-15',
    'Male',
    '123 Main Street',
    'Anytown',
    'CA',
    '12345',
    'USA',
    @programID,
    CURDATE(),
    'active',
    'Sample student record for testing purposes.',
    @superAdminID,
    NOW()
);

-- Display success message
SELECT 'Database seeded successfully!' as message,
       'SuperAdmin Login: superadmin / admin123' as credentials,
       'Sample student created: John Doe (STU001)' as sample_data;