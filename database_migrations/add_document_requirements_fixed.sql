-- COR4EDU SMS Document Requirements Migration
-- Adds document requirements system with dual-storage architecture
-- Generated: 2025-09-28
-- FIXED: Removed IF NOT EXISTS from ALTER TABLE (not supported in MySQL)

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

-- Check if column exists before adding (MySQL safe method)
SET @dbname = DATABASE();
SET @tablename = 'cor4edu_documents';
SET @columnname = 'linkedRequirementCode';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_documents ADD COLUMN linkedRequirementCode VARCHAR(50) NULL AFTER notes'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Check if index exists before adding
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = 'requirement_link')
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_documents ADD INDEX requirement_link (linkedRequirementCode)'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Insert default document requirements
INSERT INTO `cor4edu_document_requirements` (`requirementCode`, `tabName`, `displayName`, `description`, `allowMultiple`, `displayOrder`) VALUES
('id_verification', 'information', 'ID Verification', 'Government-issued photo identification document', 'N', 1),
('enrollment_agreement', 'admissions', 'Enrollment Agreement', 'Signed enrollment agreement document', 'N', 1),
('hs_diploma_transcripts', 'admissions', 'High School Diploma, GED, Official Transcripts', 'High school completion documentation or equivalent', 'N', 2),
('payment_plan_agreement', 'bursar', 'Payment Plan Agreement', 'Signed payment plan agreement document', 'N', 1),
('current_resume', 'career', 'Current Resume/CV', 'Most recent resume or curriculum vitae', 'N', 1),
('school_degree', 'graduation', 'School Degree Earned', 'Official degree certificate from institution', 'N', 1),
('school_transcript', 'graduation', 'School Official Transcript', 'Official academic transcript from institution', 'N', 2)
ON DUPLICATE KEY UPDATE displayName=displayName;

-- Add foreign key constraint for requirement linking
-- Check if constraint exists first
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE
      CONSTRAINT_SCHEMA = @dbname
      AND TABLE_NAME = 'cor4edu_student_document_requirements'
      AND CONSTRAINT_NAME = 'fk_student_requirement_code'
  ) > 0,
  'SELECT 1',
  'ALTER TABLE cor4edu_student_document_requirements ADD CONSTRAINT fk_student_requirement_code FOREIGN KEY (requirementCode) REFERENCES cor4edu_document_requirements(requirementCode) ON DELETE CASCADE'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET FOREIGN_KEY_CHECKS = 1;

-- Success message
SELECT 'Document requirements system tables created successfully!' as message;
