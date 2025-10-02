-- PHASE 1: DIAGNOSE Cloud SQL Database State
-- Check superadmin user
SELECT '=== SUPERADMIN USER ===' as section;
SELECT staffID, username, isSuperAdmin, active, email
FROM cor4edu_staff
WHERE username = 'superadmin';

-- Check delete permission
SELECT '=== DELETE PERMISSION ===' as section;
SELECT * FROM cor4edu_system_permissions
WHERE module='programs' AND action='delete';

-- Check all programs permissions
SELECT '=== ALL PROGRAMS PERMISSIONS ===' as section;
SELECT permissionID, module, action, name, requiresAdminRole, active, displayOrder
FROM cor4edu_system_permissions
WHERE module='programs'
ORDER BY displayOrder;
