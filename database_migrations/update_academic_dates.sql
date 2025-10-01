-- Academic Date Fields Enhancement Migration
-- Adds comprehensive date tracking for complete student academic lifecycle
-- Following Gibbon patterns for table modifications

-- Add new academic date fields to cor4edu_students table
ALTER TABLE `cor4edu_students`
ADD COLUMN `anticipatedGraduationDate` DATE NULL AFTER `enrollmentDate`,
ADD COLUMN `actualGraduationDate` DATE NULL AFTER `anticipatedGraduationDate`,
ADD COLUMN `withdrawnDate` DATE NULL AFTER `lastDayOfAttendance`;

-- Migrate existing graduationDate data to anticipatedGraduationDate
UPDATE `cor4edu_students`
SET `anticipatedGraduationDate` = `graduationDate`
WHERE `graduationDate` IS NOT NULL;

-- Set actualGraduationDate for students who have already graduated
UPDATE `cor4edu_students`
SET `actualGraduationDate` = `graduationDate`
WHERE `status` IN ('graduated', 'alumni')
AND `graduationDate` IS NOT NULL;

-- Remove the old graduationDate column (now replaced by anticipatedGraduationDate)
ALTER TABLE `cor4edu_students`
DROP COLUMN `graduationDate`;

-- Add indexes for performance on new date fields
ALTER TABLE `cor4edu_students`
ADD INDEX `idx_anticipated_graduation` (`anticipatedGraduationDate`),
ADD INDEX `idx_actual_graduation` (`actualGraduationDate`),
ADD INDEX `idx_withdrawn_date` (`withdrawnDate`);

-- Add comments for documentation
ALTER TABLE `cor4edu_students`
COMMENT = 'Student management table with comprehensive academic date tracking';

-- Create view for easy access to student academic timeline
CREATE OR REPLACE VIEW `cor4edu_student_academic_timeline` AS
SELECT
    s.studentID,
    s.studentCode,
    CONCAT(s.firstName, ' ', s.lastName) AS fullName,
    p.name AS programName,
    s.enrollmentDate,
    s.anticipatedGraduationDate,
    s.actualGraduationDate,
    s.lastDayOfAttendance,
    s.withdrawnDate,
    s.status,
    CASE
        WHEN s.actualGraduationDate IS NOT NULL THEN 'Graduated'
        WHEN s.withdrawnDate IS NOT NULL THEN 'Withdrawn'
        WHEN s.status = 'active' THEN 'Currently Enrolled'
        WHEN s.status = 'prospective' THEN 'Prospective'
        ELSE s.status
    END AS academicStatusDescription,
    CASE
        WHEN s.actualGraduationDate IS NOT NULL THEN DATEDIFF(s.actualGraduationDate, s.enrollmentDate)
        WHEN s.lastDayOfAttendance IS NOT NULL THEN DATEDIFF(s.lastDayOfAttendance, s.enrollmentDate)
        WHEN s.withdrawnDate IS NOT NULL THEN DATEDIFF(s.withdrawnDate, s.enrollmentDate)
        ELSE DATEDIFF(CURDATE(), s.enrollmentDate)
    END AS daysEnrolled
FROM `cor4edu_students` s
LEFT JOIN `cor4edu_programs` p ON s.programID = p.programID;