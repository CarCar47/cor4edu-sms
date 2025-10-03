-- Academic Date Fields Enhancement Migration (FIXED)
-- Adds comprehensive date tracking for complete student academic lifecycle
-- Fixed version: Does NOT depend on existing column positions

SET FOREIGN_KEY_CHECKS = 0;

-- Check which columns already exist and add only missing ones

-- Add anticipatedGraduationDate if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'cor4edu_students';
SET @columnname = 'anticipatedGraduationDate';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_students ADD COLUMN anticipatedGraduationDate DATE NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add actualGraduationDate if it doesn't exist
SET @columnname = 'actualGraduationDate';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_students ADD COLUMN actualGraduationDate DATE NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add lastDayOfAttendance if it doesn't exist
SET @columnname = 'lastDayOfAttendance';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_students ADD COLUMN lastDayOfAttendance DATE NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add withdrawnDate if it doesn't exist
SET @columnname = 'withdrawnDate';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_students ADD COLUMN withdrawnDate DATE NULL'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Migrate existing graduationDate data to anticipatedGraduationDate (only if graduationDate exists)
SET @columnname = 'graduationDate';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'UPDATE cor4edu_students SET anticipatedGraduationDate = graduationDate WHERE graduationDate IS NOT NULL AND anticipatedGraduationDate IS NULL',
  'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Set actualGraduationDate for students who have already graduated (only if graduationDate exists)
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = 'graduationDate')
  ) > 0,
  'UPDATE cor4edu_students SET actualGraduationDate = graduationDate WHERE status IN (\'graduated\', \'alumni\') AND graduationDate IS NOT NULL AND actualGraduationDate IS NULL',
  'SELECT 1'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add indexes for performance (only if they don't exist)
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'idx_anticipated_graduation')
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_students ADD INDEX idx_anticipated_graduation (anticipatedGraduationDate)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'idx_actual_graduation')
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_students ADD INDEX idx_actual_graduation (actualGraduationDate)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'idx_withdrawn_date')
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_students ADD INDEX idx_withdrawn_date (withdrawnDate)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'idx_last_day_attendance')
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_students ADD INDEX idx_last_day_attendance (lastDayOfAttendance)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Create view for easy access to student academic timeline (replace if exists)
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

SET FOREIGN_KEY_CHECKS = 1;

-- Success message
SELECT 'Academic date columns migration completed successfully!' as message;
SELECT 'Added: anticipatedGraduationDate, actualGraduationDate, lastDayOfAttendance, withdrawnDate' as columns_added;
