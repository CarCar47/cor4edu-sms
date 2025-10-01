-- Add 'not_graduated' status to career placements table
-- This supports the "None (Not Graduated)" option in the employment status dropdown

-- Update the employmentStatus ENUM to include 'not_graduated'
ALTER TABLE `cor4edu_career_placements`
MODIFY COLUMN `employmentStatus` ENUM(
    'not_graduated',
    'employed_related',
    'employed_unrelated',
    'self_employed_related',
    'self_employed_unrelated',
    'not_employed_seeking',
    'not_employed_not_seeking',
    'continuing_education'
) NOT NULL COMMENT 'Employment status including not_graduated for students who have not yet graduated';

-- Add comment for documentation
ALTER TABLE `cor4edu_career_placements`
COMMENT = 'Comprehensive career placement tracking table for compliance reporting. Tracks final employment outcomes, verification, and licensure requirements. Includes not_graduated status for students who have not yet graduated.';