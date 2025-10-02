-- Debug Financial Reports Database Structure
-- Run this in phpMyAdmin to see what tables and columns exist

-- Check if cor4edu_program_pricing table exists
SHOW TABLES LIKE 'cor4edu_program_pricing';

-- Check cor4edu_programs table structure
DESCRIBE cor4edu_programs;

-- Check cor4edu_program_pricing table structure (if it exists)
DESCRIBE cor4edu_program_pricing;

-- Show sample data from programs table
SELECT programID, name, programCode FROM cor4edu_programs LIMIT 3;

-- Check if cor4edu_programs has pricing columns
SHOW COLUMNS FROM cor4edu_programs LIKE '%tuition%';
SHOW COLUMNS FROM cor4edu_programs LIKE '%price%';
SHOW COLUMNS FROM cor4edu_programs LIKE '%cost%';