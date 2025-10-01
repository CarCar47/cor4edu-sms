-- Fix Staff ID 2 to have proper ADMIN002 code
-- This resolves the unique constraint issue

UPDATE cor4edu_staff
SET staffCode = 'ADMIN002'
WHERE staffID = 2 AND staffCode = '';

-- Verify the fix
SELECT staffID, firstName, lastName, staffCode, isSuperAdmin
FROM cor4edu_staff
ORDER BY staffID;