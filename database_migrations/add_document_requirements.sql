-- COR4EDU SMS Document Requirements Migration
-- Adds document requirements system with dual-storage architecture
-- Generated: 2025-09-28

SET FOREIGN_KEY_CHECKS = 0;

-- Create document requirements definition table
CREATE TABLE IF NOT EXISTS `cor4edu_document_requirements` (
  `requirementID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `requirementCode` VARCHAR(50) NOT NULL,
  `tabName` VARCHAR(50) NOT NULL,
  `displayName` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `allowMultiple` ENUM('Y','N') DEFAULT 'N',
  `isActive` ENUM('Y','N') DEFAULT 'Y',
  `displayOrder` INT DEFAULT 0,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`requirementID`),
  UNIQUE KEY `unique_requirement_code` (`requirementCode`),
  INDEX `tab_active` (`tabName`, `isActive`),
  INDEX `display_order` (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create student document requirements tracking table
CREATE TABLE IF NOT EXISTS `cor4edu_student_document_requirements` (
  `studentRequirementID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `requirementCode` VARCHAR(50) NOT NULL,
  `currentDocumentID` INT(10) UNSIGNED NULL,
  `status` ENUM('missing','submitted') DEFAULT 'missing',
  `submittedOn` TIMESTAMP NULL,
  `notes` TEXT,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`studentRequirementID`),
  UNIQUE KEY `unique_student_requirement` (`studentID`, `requirementCode`),
  FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`) ON DELETE CASCADE,
  FOREIGN KEY (`currentDocumentID`) REFERENCES `cor4edu_documents`(`documentID`) ON DELETE SET NULL,
  INDEX `student_status` (`studentID`, `status`),
  INDEX `requirement_code` (`requirementCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add requirement linking column to existing documents table
ALTER TABLE `cor4edu_documents`
ADD COLUMN IF NOT EXISTS `linkedRequirementCode` VARCHAR(50) NULL AFTER `notes`,
ADD INDEX IF NOT EXISTS `requirement_link` (`linkedRequirementCode`);

-- Insert default document requirements
INSERT INTO `cor4edu_document_requirements` (`requirementCode`, `tabName`, `displayName`, `description`, `allowMultiple`, `displayOrder`) VALUES
('id_verification', 'information', 'ID Verification', 'Government-issued photo identification document', 'N', 1),
('enrollment_agreement', 'admissions', 'Enrollment Agreement', 'Signed enrollment agreement document', 'N', 1),
('hs_diploma_transcripts', 'admissions', 'High School Diploma, GED, Official Transcripts', 'High school completion documentation or equivalent', 'N', 2),
('payment_plan_agreement', 'bursar', 'Payment Plan Agreement', 'Signed payment plan agreement document', 'N', 1),
('current_resume', 'career', 'Current Resume/CV', 'Most recent resume or curriculum vitae', 'N', 1),
('school_degree', 'graduation', 'School Degree Earned', 'Official degree certificate from institution', 'N', 1),
('school_transcript', 'graduation', 'School Official Transcript', 'Official academic transcript from institution', 'N', 2);

-- Add foreign key constraint for requirement linking
ALTER TABLE `cor4edu_student_document_requirements`
ADD CONSTRAINT `fk_student_requirement_code`
FOREIGN KEY (`requirementCode`) REFERENCES `cor4edu_document_requirements`(`requirementCode`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;

-- Success message
SELECT 'Document requirements system tables created successfully!' as message;