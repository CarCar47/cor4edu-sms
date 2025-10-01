-- Add flexible measurement system to programs table
-- This allows programs to be credit-based, hour-based, both, or neither

ALTER TABLE cor4edu_programs
ADD COLUMN measurementType ENUM('credits', 'hours', 'both', 'none') DEFAULT 'credits' AFTER duration;

ALTER TABLE cor4edu_programs
ADD COLUMN contactHours INT NULL AFTER creditHours;

-- Update existing programs to use 'credits' as default measurement type
-- (This is safe since all existing programs will default to credits)
UPDATE cor4edu_programs SET measurementType = 'credits' WHERE measurementType IS NULL;

-- Optional: Add index for performance if needed
-- ALTER TABLE cor4edu_programs ADD INDEX idx_measurement_type (measurementType);