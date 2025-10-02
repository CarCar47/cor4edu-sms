-- Create superadmin user for COR4EDU SMS
-- Password: Admin@2025! (bcrypt hash)

INSERT INTO cor4edu_staff (
    staffCode,
    username,
    passwordStrong,
    passwordStrongSalt,
    firstName,
    lastName,
    email,
    position,
    active,
    isSuperAdmin,
    canCreateAdmins
) VALUES (
    'ADMIN001',
    'superadmin',
    '$2y$10$kC7gfK6qV9FJ0zXVhZY7wOQxZ7J8X3mGHvJ6qY0nZrQ5yF8xK9oWO',
    '',
    'Super',
    'Administrator',
    'admin@cor4edu.com',
    'System Administrator',
    'Y',
    'Y',
    'Y'
);

-- Create default program if doesn't exist
INSERT IGNORE INTO cor4edu_programs (
    programCode,
    name,
    description,
    duration,
    active
) VALUES (
    'GEN001',
    'General Studies',
    'Default program for testing',
    '12 months',
    'Y'
);
