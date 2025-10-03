-- Create only the missing employment tracking tables
-- Safe to run - uses IF NOT EXISTS

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

-- Create job applications tracking table (THIS IS THE CRITICAL ONE)
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
