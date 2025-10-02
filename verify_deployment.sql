-- Verify superadmin user
SELECT staffID, username, isSuperAdmin, active, email
FROM cor4edu_staff
WHERE username = 'superadmin';

-- Verify delete permission exists
SELECT * FROM cor4edu_system_permissions
WHERE module = 'programs' AND action = 'delete';
