-- Career Placements Table Migration
-- Creates comprehensive placement tracking for compliance reporting
-- Following Gibbon patterns with proper relationships and indexes

-- Create career placements table for final employment outcomes
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

  -- How Job Was Obtained (using TEXT for compatibility - can store comma-separated values)
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

  -- Gibbon Standard Fields
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

-- Add indexes for reporting and performance
ALTER TABLE `cor4edu_career_placements`
ADD INDEX `idx_status_current` (`employmentStatus`, `isCurrentRecord`),
ADD INDEX `idx_employment_type` (`employmentType`),
ADD INDEX `idx_salary_range` (`salaryRange`),
ADD INDEX `idx_verification_status` (`verificationDate`, `verifiedBy`);

-- Migrate existing employment data from students table to placements table
-- This creates initial placement records for students who already have employment data
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
    `studentID`,
    CASE
        WHEN `employmentStatus` = 'not_graduated' THEN 'not_employed_not_seeking'
        WHEN `employmentStatus` = 'job_seeking' THEN 'not_employed_seeking'
        WHEN `employmentStatus` = 'job_placement_received' THEN 'employed_related'
        ELSE 'not_employed_not_seeking'
    END as `employmentStatus`,
    `jobPlacementDate` as `employmentDate`,
    `jobTitle`,
    `employerName`,
    CONCAT('Migrated from student record on ', NOW()) as `comments`,
    'Y' as `isCurrentRecord`,
    COALESCE(`createdBy`, 1) as `createdBy`,
    COALESCE(`createdOn`, NOW()) as `createdOn`
FROM `cor4edu_students`
WHERE `employmentStatus` IS NOT NULL
AND (`jobTitle` IS NOT NULL OR `employerName` IS NOT NULL OR `employmentStatus` != 'not_graduated');

-- Add comments for documentation
ALTER TABLE `cor4edu_career_placements`
COMMENT = 'Comprehensive career placement tracking table for compliance reporting. Tracks final employment outcomes, verification, and licensure requirements following institutional placement form standards.';