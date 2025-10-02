-- Fix superadmin user - set isSuperAdmin to 'Y'
UPDATE cor4edu_staff
SET isSuperAdmin = 'Y'
WHERE username = 'superadmin';

-- Verify the fix
SELECT staffID, username, isSuperAdmin, active
FROM cor4edu_staff
WHERE username = 'superadmin';
