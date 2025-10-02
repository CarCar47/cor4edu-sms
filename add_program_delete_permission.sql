-- Add delete permission for programs
-- This allows SuperAdmin to delete programs

-- Check if permission already exists (for safety)
-- If it exists, this will show it; if not, will return empty

-- Add the delete permission (SuperAdmin only)
-- Update existing permission to be admin-only
UPDATE cor4edu_system_permissions
SET requiresAdminRole = 'Y',
    name = 'Delete Programs',
    description = 'Ability to delete academic programs (SuperAdmin only)',
    active = 'Y'
WHERE module = 'programs' AND action = 'delete';

-- If it doesn't exist, insert it
INSERT INTO cor4edu_system_permissions
(module, action, name, description, category, requiresAdminRole, active, displayOrder, createdBy)
SELECT 'programs', 'delete', 'Delete Programs', 'Ability to delete academic programs (SuperAdmin only)', 'program_management', 'Y', 'Y', 4, 1
WHERE NOT EXISTS (
    SELECT 1 FROM cor4edu_system_permissions WHERE module = 'programs' AND action = 'delete'
);

-- Verify it was added
SELECT * FROM cor4edu_system_permissions WHERE module='programs' ORDER BY displayOrder;
