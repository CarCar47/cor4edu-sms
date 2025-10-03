-- COMPREHENSIVE FIX: Apply ALL missing tables at once
-- This combines all known missing migrations into one file

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. CAREER PLACEMENTS TABLE (MISSING - CAUSES 500 ERROR)
-- ============================================================
CREATE TABLE IF NOT EXISTS `cor4edu_career_placements` (
  `placementID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `employmentStatus` ENUM(
    'employed_related',
    'employed_unrelated',
    'self_employed_related',
    'self_employed_unrelated',
    'not_employed_seeking',
    'not_employed_not_seeking',
    'continuing_education'
  ) NOT NULL,
  `employmentDate` DATE NULL,
  `jobTitle` VARCHAR(100) NULL,
  `employerName` VARCHAR(100) NULL,
  `employerAddress` TEXT NULL,
  `employerContactName` VARCHAR(100) NULL,
  `employerContactPhone` VARCHAR(20) NULL,
  `employerContactEmail` VARCHAR(75) NULL,
  `employmentType` ENUM('full_time','part_time','contract','internship') NULL,
  `isEntryLevel` ENUM('Y','N') NULL,
  `salaryRange` ENUM('under_20k','20k_30k','30k_40k','40k_50k','50k_60k','over_60k') NULL,
  `salaryExact` DECIMAL(10,2) NULL,
  `jobObtainedMethods` TEXT NULL COMMENT 'Comma-separated methods',
  `jobObtainedOther` VARCHAR(100) NULL,
  `continuingEducationInstitution` VARCHAR(100) NULL,
  `continuingEducationProgram` VARCHAR(100) NULL,
  `verificationSource` ENUM('employer_confirmation','pay_stub','student_self_report','other') NULL,
  `verificationDate` DATE NULL,
  `verifiedBy` INT(10) UNSIGNED NULL,
  `verificationNotes` TEXT NULL,
  `requiresLicense` ENUM('Y','N') DEFAULT 'N',
  `licenseType` VARCHAR(100) NULL,
  `licenseObtained` ENUM('Y','N') NULL,
  `licenseNumber` VARCHAR(50) NULL,
  `comments` TEXT NULL,
  `isCurrentRecord` ENUM('Y','N') DEFAULT 'Y',
  `createdBy` INT(10) UNSIGNED NOT NULL,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED NULL,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`placementID`),
  INDEX `studentID` (`studentID`),
  INDEX `employmentStatus` (`employmentStatus`),
  INDEX `isCurrentRecord` (`isCurrentRecord`),
  INDEX `verificationDate` (`verificationDate`),
  INDEX `employmentDate` (`employmentDate`),
  INDEX `idx_status_current` (`employmentStatus`, `isCurrentRecord`),
  INDEX `idx_employment_type` (`employmentType`),
  INDEX `idx_salary_range` (`salaryRange`),
  INDEX `idx_verification_status` (`verificationDate`, `verifiedBy`),
  CONSTRAINT `fk_career_student` FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`) ON DELETE CASCADE,
  CONSTRAINT `fk_career_created` FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`),
  CONSTRAINT `fk_career_modified` FOREIGN KEY (`modifiedBy`) REFERENCES `cor4edu_staff`(`staffID`),
  CONSTRAINT `fk_career_verified` FOREIGN KEY (`verifiedBy`) REFERENCES `cor4edu_staff`(`staffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT = 'Career placement records for compliance reporting';

SET FOREIGN_KEY_CHECKS = 1;

-- Success message
SELECT 'ALL MISSING TABLES CREATED SUCCESSFULLY!' as message;
SELECT 'Career placements table: ✅ CREATED' as status;
