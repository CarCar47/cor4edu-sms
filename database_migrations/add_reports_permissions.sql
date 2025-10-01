-- Add Reports System Permissions
-- This migration adds all necessary permissions for the comprehensive reports system

-- Add general reports permissions
INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 1, 'reports', 'view_reports_tab', 'Y', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 1 AND module = 'reports' AND action = 'view_reports_tab'
);

-- Overview reports permission (usually available to all)
INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 1, 'reports', 'generate_overview_reports', 'Y', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 1 AND module = 'reports' AND action = 'generate_overview_reports'
);

-- Admissions/Enrollment reports permission
INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 1, 'reports', 'generate_admissions_reports', 'Y', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 1 AND module = 'reports' AND action = 'generate_admissions_reports'
);

-- Financial reports permission
INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 1, 'reports', 'generate_financial_reports', 'Y', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 1 AND module = 'reports' AND action = 'generate_financial_reports'
);

-- Career services reports permission
INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 1, 'reports', 'generate_career_reports', 'Y', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 1 AND module = 'reports' AND action = 'generate_career_reports'
);

-- Academic performance reports permission
INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 1, 'reports', 'generate_academic_reports', 'Y', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 1 AND module = 'reports' AND action = 'generate_academic_reports'
);

-- Export permissions
INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 1, 'reports', 'export_reports_csv', 'Y', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 1 AND module = 'reports' AND action = 'export_reports_csv'
);

INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 1, 'reports', 'export_reports_excel', 'Y', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 1 AND module = 'reports' AND action = 'export_reports_excel'
);

-- Financial data viewing permission (for detailed financial information)
INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 1, 'reports', 'view_financial_details', 'Y', 1, NOW()
WHERE NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 1 AND module = 'reports' AND action = 'view_financial_details'
);

-- Add permissions for staff ID 2 (second admin) if exists
INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 2, 'reports', 'view_reports_tab', 'Y', 1, NOW()
WHERE EXISTS (SELECT 1 FROM cor4edu_staff WHERE staffID = 2 AND active = 'Y')
AND NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 2 AND module = 'reports' AND action = 'view_reports_tab'
);

INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 2, 'reports', 'generate_overview_reports', 'Y', 1, NOW()
WHERE EXISTS (SELECT 1 FROM cor4edu_staff WHERE staffID = 2 AND active = 'Y')
AND NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 2 AND module = 'reports' AND action = 'generate_overview_reports'
);

INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 2, 'reports', 'generate_admissions_reports', 'Y', 1, NOW()
WHERE EXISTS (SELECT 1 FROM cor4edu_staff WHERE staffID = 2 AND active = 'Y')
AND NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 2 AND module = 'reports' AND action = 'generate_admissions_reports'
);

INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 2, 'reports', 'generate_financial_reports', 'Y', 1, NOW()
WHERE EXISTS (SELECT 1 FROM cor4edu_staff WHERE staffID = 2 AND active = 'Y')
AND NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 2 AND module = 'reports' AND action = 'generate_financial_reports'
);

INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 2, 'reports', 'generate_career_reports', 'Y', 1, NOW()
WHERE EXISTS (SELECT 1 FROM cor4edu_staff WHERE staffID = 2 AND active = 'Y')
AND NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 2 AND module = 'reports' AND action = 'generate_career_reports'
);

INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 2, 'reports', 'generate_academic_reports', 'Y', 1, NOW()
WHERE EXISTS (SELECT 1 FROM cor4edu_staff WHERE staffID = 2 AND active = 'Y')
AND NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 2 AND module = 'reports' AND action = 'generate_academic_reports'
);

INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 2, 'reports', 'export_reports_csv', 'Y', 1, NOW()
WHERE EXISTS (SELECT 1 FROM cor4edu_staff WHERE staffID = 2 AND active = 'Y')
AND NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 2 AND module = 'reports' AND action = 'export_reports_csv'
);

INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 2, 'reports', 'export_reports_excel', 'Y', 1, NOW()
WHERE EXISTS (SELECT 1 FROM cor4edu_staff WHERE staffID = 2 AND active = 'Y')
AND NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 2 AND module = 'reports' AND action = 'export_reports_excel'
);

INSERT INTO cor4edu_staff_permissions (staffID, module, action, allowed, createdBy, createdOn)
SELECT 2, 'reports', 'view_financial_details', 'Y', 1, NOW()
WHERE EXISTS (SELECT 1 FROM cor4edu_staff WHERE staffID = 2 AND active = 'Y')
AND NOT EXISTS (
    SELECT 1 FROM cor4edu_staff_permissions
    WHERE staffID = 2 AND module = 'reports' AND action = 'view_financial_details'
);

-- Verification: Count of permissions added
SELECT
    'Reports permissions added successfully' as message,
    COUNT(*) as total_reports_permissions
FROM cor4edu_staff_permissions
WHERE module = 'reports';