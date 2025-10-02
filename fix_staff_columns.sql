-- Add missing columns to cor4edu_staff table
-- If column exists, this will error - that's OK, ignore the error

ALTER TABLE cor4edu_staff ADD COLUMN phone VARCHAR(20) NULL;
ALTER TABLE cor4edu_staff ADD COLUMN address VARCHAR(255) NULL;
ALTER TABLE cor4edu_staff ADD COLUMN city VARCHAR(100) NULL;
ALTER TABLE cor4edu_staff ADD COLUMN state VARCHAR(50) NULL;
ALTER TABLE cor4edu_staff ADD COLUMN zipCode VARCHAR(20) NULL;
ALTER TABLE cor4edu_staff ADD COLUMN country VARCHAR(100) DEFAULT 'United States';
ALTER TABLE cor4edu_staff ADD COLUMN dateOfBirth DATE NULL;
ALTER TABLE cor4edu_staff ADD COLUMN emergencyContact VARCHAR(100) NULL;
ALTER TABLE cor4edu_staff ADD COLUMN emergencyPhone VARCHAR(20) NULL;
ALTER TABLE cor4edu_staff ADD COLUMN teachingPrograms JSON NULL;
ALTER TABLE cor4edu_staff ADD COLUMN notes TEXT NULL;
ALTER TABLE cor4edu_staff ADD COLUMN startDate DATE NULL;
ALTER TABLE cor4edu_staff ADD COLUMN endDate DATE NULL;
