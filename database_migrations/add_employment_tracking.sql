-- Employment Tracking Enhancement Migration
-- Adds employment status tracking fields to support career services
-- Following Gibbon patterns for table modifications

-- Add employment tracking columns to cor4edu_students table
ALTER TABLE `cor4edu_students`
ADD COLUMN `employmentStatus` ENUM('not_graduated','job_seeking','job_placement_received') DEFAULT 'not_graduated' AFTER `status`,
ADD COLUMN `jobSeekingStartDate` DATE NULL AFTER `employmentStatus`,
ADD COLUMN `jobPlacementDate` DATE NULL AFTER `jobSeekingStartDate`,
ADD COLUMN `employerName` VARCHAR(100) NULL AFTER `jobPlacementDate`,
ADD COLUMN `jobTitle` VARCHAR(100) NULL AFTER `employerName`,
ADD COLUMN `lastDayOfAttendance` DATE NULL AFTER `graduationDate`;

-- Add indexes for performance
ALTER TABLE `cor4edu_students`
ADD INDEX `idx_employment_status` (`employmentStatus`),
ADD INDEX `idx_job_seeking_start` (`jobSeekingStartDate`),
ADD INDEX `idx_job_placement` (`jobPlacementDate`);

-- Create employment tracking log table for audit trail
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

-- Create job applications tracking table
CREATE TABLE IF NOT EXISTS `cor4edu_job_applications` (
  `applicationID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `companyName` VARCHAR(100) NOT NULL,
  `jobTitle` VARCHAR(100) NOT NULL,
  `applicationDate` DATE NOT NULL,
  `applicationMethod` VARCHAR(50),
  `status` ENUM('applied','interviewing','offered','accepted','rejected','withdrawn') DEFAULT 'applied',
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

-- Update existing students to have default employment status based on their current status
UPDATE `cor4edu_students`
SET `employmentStatus` = CASE
    WHEN `status` IN ('prospective', 'active') THEN 'not_graduated'
    WHEN `status` IN ('graduated', 'alumni') THEN 'job_seeking'
    WHEN `status` = 'withdrawn' THEN 'not_graduated'
    ELSE 'not_graduated'
END;

-- Set job seeking start date for graduated students (using graduation date + 1 day)
UPDATE `cor4edu_students`
SET `jobSeekingStartDate` = DATE_ADD(`graduationDate`, INTERVAL 1 DAY)
WHERE `status` IN ('graduated', 'alumni')
AND `graduationDate` IS NOT NULL
AND `employmentStatus` = 'job_seeking';

-- Add comments for documentation
ALTER TABLE `cor4edu_students`
COMMENT = 'Student management table with employment tracking support';

ALTER TABLE `cor4edu_employment_tracking`
COMMENT = 'Audit log for employment status changes and career services interactions';

ALTER TABLE `cor4edu_job_applications`
COMMENT = 'Job application tracking for career services reporting';