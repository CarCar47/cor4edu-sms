-- Check superadmin user in Cloud SQL
SELECT staffID, username, isSuperAdmin, active, email
FROM cor4edu_staff
WHERE username = 'superadmin';
