-- Comprehensive Career Services Reports Fix
-- Ensures all required tables and columns exist for career placement tracking
-- Safe to run multiple times - uses IF NOT EXISTS and column checks

-- ==================================================
-- STEP 1: Add employment columns to students table if missing
-- ==================================================

-- Check and add employmentStatus column
SET @dbname = DATABASE();
SET @tablename = 'cor4edu_students';
SET @columnname = 'employmentStatus';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " ENUM('not_graduated','job_seeking','job_placement_received') DEFAULT 'not_graduated' AFTER status")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add jobSeekingStartDate column
SET @columnname = 'jobSeekingStartDate';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " DATE NULL AFTER employmentStatus")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add jobPlacementDate column
SET @columnname = 'jobPlacementDate';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " DATE NULL AFTER jobSeekingStartDate")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add employerName column
SET @columnname = 'employerName';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(100) NULL AFTER jobPlacementDate")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add jobTitle column
SET @columnname = 'jobTitle';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " VARCHAR(100) NULL AFTER employerName")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check and add lastDayOfAttendance column
SET @columnname = 'lastDayOfAttendance';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  CONCAT("ALTER TABLE ", @tablename, " ADD COLUMN ", @columnname, " DATE NULL AFTER graduationDate")
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ==================================================
-- STEP 2: Create career_placements table if not exists
-- ==================================================

CREATE TABLE IF NOT EXISTS `cor4edu_career_placements` (
  `placementID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,

  -- Employment Status (matches placement form requirements exactly)
  `employmentStatus` ENUM(
    'employed_related',
    'employed_unrelated',
    'self_employed_related',
    'self_employed_unrelated',
    'not_employed_seeking',
    'not_employed_not_seeking',
    'continuing_education'
  ) NOT NULL,

  -- Employment Details
  `employmentDate` DATE NULL,
  `jobTitle` VARCHAR(100) NULL,
  `employerName` VARCHAR(100) NULL,
  `employerAddress` TEXT NULL,
  `employerContactName` VARCHAR(100) NULL,
  `employerContactPhone` VARCHAR(20) NULL,
  `employerContactEmail` VARCHAR(75) NULL,

  -- Employment Type & Level
  `employmentType` ENUM('full_time','part_time','contract','internship') NULL,
  `isEntryLevel` ENUM('Y','N') NULL,

  -- Salary Information
  `salaryRange` ENUM('under_20k','20k_30k','30k_40k','40k_50k','50k_60k','over_60k') NULL,
  `salaryExact` DECIMAL(10,2) NULL,

  -- How Job Was Obtained
  `jobObtainedMethods` TEXT NULL COMMENT 'Comma-separated: institution_placement,personal_network,job_fair,online,direct_application,other',
  `jobObtainedOther` VARCHAR(100) NULL,

  -- Continuing Education (if applicable)
  `continuingEducationInstitution` VARCHAR(100) NULL,
  `continuingEducationProgram` VARCHAR(100) NULL,

  -- Verification & Compliance
  `verificationSource` ENUM('employer_confirmation','pay_stub','student_self_report','other') NULL,
  `verificationDate` DATE NULL,
  `verifiedBy` INT(10) UNSIGNED NULL,
  `verificationNotes` TEXT NULL,

  -- Licensure/Certification
  `requiresLicense` ENUM('Y','N') DEFAULT 'N',
  `licenseType` VARCHAR(100) NULL,
  `licenseObtained` ENUM('Y','N') NULL,
  `licenseNumber` VARCHAR(50) NULL,

  -- General
  `comments` TEXT NULL,
  `isCurrentRecord` ENUM('Y','N') DEFAULT 'Y',

  -- Standard audit fields
  `createdBy` INT(10) UNSIGNED NOT NULL,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED NULL,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`placementID`),
  FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`) ON DELETE CASCADE,
  FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`),
  FOREIGN KEY (`modifiedBy`) REFERENCES `cor4edu_staff`(`staffID`),
  FOREIGN KEY (`verifiedBy`) REFERENCES `cor4edu_staff`(`staffID`),

  INDEX `studentID` (`studentID`),
  INDEX `employmentStatus` (`employmentStatus`),
  INDEX `isCurrentRecord` (`isCurrentRecord`),
  INDEX `verificationDate` (`verificationDate`),
  INDEX `employmentDate` (`employmentDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT = 'Career placement records for compliance reporting and employment outcome tracking';

-- ==================================================
-- STEP 3: Migrate existing employment data from students table
-- ==================================================

-- Only insert if students have employment data AND no placement record exists
INSERT INTO `cor4edu_career_placements` (
    `studentID`,
    `employmentStatus`,
    `employmentDate`,
    `jobTitle`,
    `employerName`,
    `comments`,
    `isCurrentRecord`,
    `createdBy`,
    `createdOn`
)
SELECT
    s.`studentID`,
    CASE
        WHEN s.`employmentStatus` = 'not_graduated' THEN 'not_employed_not_seeking'
        WHEN s.`employmentStatus` = 'job_seeking' THEN 'not_employed_seeking'
        WHEN s.`employmentStatus` = 'job_placement_received' THEN 'employed_related'
        ELSE 'not_employed_not_seeking'
    END as `employmentStatus`,
    s.`jobPlacementDate` as `employmentDate`,
    s.`jobTitle`,
    s.`employerName`,
    CONCAT('Migrated from student record on ', NOW()) as `comments`,
    'Y' as `isCurrentRecord`,
    COALESCE(s.`createdBy`, 1) as `createdBy`,
    COALESCE(s.`createdOn`, NOW()) as `createdOn`
FROM `cor4edu_students` s
WHERE s.`employmentStatus` IS NOT NULL
AND (s.`jobTitle` IS NOT NULL OR s.`employerName` IS NOT NULL OR s.`employmentStatus` != 'not_graduated')
AND NOT EXISTS (
    SELECT 1 FROM `cor4edu_career_placements` cp
    WHERE cp.`studentID` = s.`studentID`
)
LIMIT 1000;  -- Limit to prevent timeout on large datasets

-- ==================================================
-- STEP 4: Update existing students to have default employment status
-- ==================================================

UPDATE `cor4edu_students`
SET `employmentStatus` = CASE
    WHEN `status` IN ('prospective', 'active') THEN 'not_graduated'
    WHEN `status` IN ('graduated', 'alumni') THEN 'job_seeking'
    WHEN `status` = 'withdrawn' THEN 'not_graduated'
    ELSE 'not_graduated'
END
WHERE `employmentStatus` IS NULL;

-- Set job seeking start date for graduated students (using graduation date + 1 day)
UPDATE `cor4edu_students`
SET `jobSeekingStartDate` = DATE_ADD(`graduationDate`, INTERVAL 1 DAY)
WHERE `status` IN ('graduated', 'alumni')
AND `graduationDate` IS NOT NULL
AND `employmentStatus` = 'job_seeking'
AND `jobSeekingStartDate` IS NULL;

-- ==================================================
-- STEP 5: Create supporting tables if not exist
-- ==================================================

CREATE TABLE IF NOT EXISTS `cor4edu_employment_tracking` (
  `trackingID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `statusFrom` ENUM('not_graduated','job_seeking','job_placement_received') NULL,
  `statusTo` ENUM('not_graduated','job_seeking','job_placement_received') NOT NULL,
  `changeDate` DATE NOT NULL,
  `notes` TEXT,
  `employerName` VARCHAR(100),
  `jobTitle` VARCHAR(100),
  `salaryRange` VARCHAR(50),
  `contactMethod` VARCHAR(100),
  `followUpRequired` ENUM('Y','N') DEFAULT 'N',
  `followUpDate` DATE NULL,
  `createdBy` INT(10) UNSIGNED NOT NULL,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`trackingID`),
  FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`) ON DELETE CASCADE,
  FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`),
  INDEX `studentID` (`studentID`),
  INDEX `statusTo` (`statusTo`),
  INDEX `changeDate` (`changeDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cor4edu_job_applications` (
  `applicationID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `companyName` VARCHAR(100) NOT NULL,
  `jobTitle` VARCHAR(100) NOT NULL,
  `applicationDate` DATE NOT NULL,
  `applicationMethod` VARCHAR(50),
  `status` ENUM('Applied','Interview','Offer','Accepted','Rejected','Withdrawn') DEFAULT 'Applied',
  `interviewDate` DATE NULL,
  `offerDate` DATE NULL,
  `responseDate` DATE NULL,
  `salaryOffered` VARCHAR(50),
  `notes` TEXT,
  `createdBy` INT(10) UNSIGNED NOT NULL,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`applicationID`),
  FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`) ON DELETE CASCADE,
  FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`),
  INDEX `studentID` (`studentID`),
  INDEX `status` (`status`),
  INDEX `applicationDate` (`applicationDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ==================================================
-- VERIFICATION QUERY
-- ==================================================

-- Check table existence and row counts
SELECT
    'cor4edu_career_placements' as table_name,
    COUNT(*) as row_count
FROM cor4edu_career_placements
UNION ALL
SELECT
    'cor4edu_students_with_employment' as table_name,
    COUNT(*) as row_count
FROM cor4edu_students
WHERE employmentStatus IS NOT NULL;
