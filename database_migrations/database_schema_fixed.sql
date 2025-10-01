-- COR4EDU SMS Database Schema
-- Following Gibbon patterns with proper relationships and security

SET FOREIGN_KEY_CHECKS = 0;

-- Staff Table (replacing gibbonPerson for staff authentication)
CREATE TABLE `cor4edu_staff` (
  `staffID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staffCode` VARCHAR(20) NOT NULL,
  `firstName` VARCHAR(60) NOT NULL,
  `lastName` VARCHAR(60) NOT NULL,
  `email` VARCHAR(75),
  `username` VARCHAR(50),
  `passwordStrong` VARCHAR(255),
  `passwordStrongSalt` VARCHAR(255),
  `position` VARCHAR(100),
  `department` VARCHAR(100),
  `startDate` DATE,
  `endDate` DATE,
  `active` ENUM('Y','N') DEFAULT 'Y',
  `isSuperAdmin` ENUM('Y','N') DEFAULT 'N',
  `canCreateAdmins` ENUM('Y','N') DEFAULT 'N',
  `lastLogin` TIMESTAMP NULL,
  `failedLogins` INT DEFAULT 0,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`staffID`),
  UNIQUE KEY `unique_staff_code` (`staffCode`),
  UNIQUE KEY `unique_staff_email` (`email`),
  UNIQUE KEY `unique_staff_username` (`username`),
  KEY `idx_staff_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Students Table (Non-login profiles)
CREATE TABLE `cor4edu_students` (
  `studentID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentCode` VARCHAR(20) NOT NULL,
  `firstName` VARCHAR(60) NOT NULL,
  `lastName` VARCHAR(60) NOT NULL,
  `preferredName` VARCHAR(60),
  `email` VARCHAR(75),
  `phone` VARCHAR(20),
  `dateOfBirth` DATE,
  `gender` ENUM('Male','Female','Other','Unspecified'),
  `address` TEXT,
  `city` VARCHAR(60),
  `state` VARCHAR(60),
  `zipCode` VARCHAR(10),
  `country` VARCHAR(60),
  `programID` INT(10) UNSIGNED,
  `enrollmentDate` DATE,
  `graduationDate` DATE,
  `status` ENUM('prospective','active','withdrawn','graduated','alumni') DEFAULT 'prospective',
  `notes` TEXT,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`studentID`),
  UNIQUE KEY `unique_student_code` (`studentCode`),
  KEY `idx_student_status` (`status`),
  KEY `idx_student_program` (`programID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Programs Table
CREATE TABLE `cor4edu_programs` (
  `programID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `programCode` VARCHAR(20) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `duration` VARCHAR(50),
  `creditHours` INT,
  `active` ENUM('Y','N') DEFAULT 'Y',
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`programID`),
  UNIQUE KEY `unique_program_code` (`programCode`),
  KEY `idx_program_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Program Pricing
CREATE TABLE `cor4edu_program_pricing` (
  `priceID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `programID` INT(10) UNSIGNED NOT NULL,
  `studentType` ENUM('domestic','international') NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `effectiveDate` DATE NOT NULL,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`priceID`),
  KEY `idx_pricing_program` (`programID`),
  KEY `idx_pricing_date` (`effectiveDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Granular Permissions Table
CREATE TABLE `cor4edu_staff_permissions` (
  `permissionID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staffID` INT(10) UNSIGNED NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `allowed` ENUM('Y','N') DEFAULT 'N',
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permissionID`),
  UNIQUE KEY `unique_staff_permission` (`staffID`, `module`, `action`),
  KEY `idx_perm_staff` (`staffID`),
  KEY `idx_perm_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tab Access Permissions
CREATE TABLE `cor4edu_staff_tab_access` (
  `accessID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staffID` INT(10) UNSIGNED NOT NULL,
  `tabName` VARCHAR(50) NOT NULL,
  `canView` ENUM('Y','N') DEFAULT 'N',
  `canEdit` ENUM('Y','N') DEFAULT 'N',
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`accessID`),
  UNIQUE KEY `unique_staff_tab_access` (`staffID`, `tabName`),
  KEY `idx_tab_staff` (`staffID`),
  KEY `idx_tab_name` (`tabName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Documents Storage
CREATE TABLE `cor4edu_documents` (
  `documentID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `entityType` ENUM('student','staff') NOT NULL,
  `entityID` INT(10) UNSIGNED NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `subcategory` VARCHAR(50),
  `fileName` VARCHAR(255) NOT NULL,
  `filePath` VARCHAR(500) NOT NULL,
  `fileSize` INT,
  `mimeType` VARCHAR(100),
  `uploadedBy` INT(10) UNSIGNED NOT NULL,
  `uploadedOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `notes` TEXT,
  `isArchived` ENUM('Y','N') DEFAULT 'N',
  `archivedBy` INT(10) UNSIGNED,
  `archivedOn` TIMESTAMP NULL,
  PRIMARY KEY (`documentID`),
  KEY `idx_doc_entity` (`entityType`, `entityID`),
  KEY `idx_doc_category` (`category`),
  KEY `idx_doc_uploader` (`uploadedBy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Payment Records (Bursar Tab)
CREATE TABLE `cor4edu_payments` (
  `paymentID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `invoiceNumber` VARCHAR(50),
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'USD',
  `paymentDate` DATE,
  `paymentMethod` VARCHAR(50),
  `status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
  `semester` VARCHAR(50),
  `academicYear` VARCHAR(20),
  `notes` TEXT,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`paymentID`),
  UNIQUE KEY `unique_invoice` (`invoiceNumber`),
  KEY `idx_payment_student` (`studentID`),
  KEY `idx_payment_status` (`status`),
  KEY `idx_payment_date` (`paymentDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Academic Records (Registrar Tab)
CREATE TABLE `cor4edu_academic_records` (
  `recordID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `recordType` ENUM('transcript','grade','enrollment','graduation') NOT NULL,
  `semester` VARCHAR(50),
  `academicYear` VARCHAR(20),
  `gpa` DECIMAL(3,2),
  `credits` INT,
  `status` VARCHAR(50),
  `notes` TEXT,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`recordID`),
  KEY `idx_record_student` (`studentID`),
  KEY `idx_record_type` (`recordType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Career Services Records
CREATE TABLE `cor4edu_career_services` (
  `careerID` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `studentID` INT(10) UNSIGNED NOT NULL,
  `employmentStatus` ENUM('job_seeking','job_placement_achieved','continuing_education') DEFAULT 'job_seeking',
  `employer` VARCHAR(100),
  `jobTitle` VARCHAR(100),
  `salary` DECIMAL(10,2),
  `startDate` DATE,
  `endDate` DATE,
  `notes` TEXT,
  `createdBy` INT(10) UNSIGNED,
  `createdOn` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `modifiedBy` INT(10) UNSIGNED,
  `modifiedOn` TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`careerID`),
  KEY `idx_career_student` (`studentID`),
  KEY `idx_career_status` (`employmentStatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- System Sessions Table (for session management)
CREATE TABLE `cor4edu_sessions` (
  `sessionID` VARCHAR(128) NOT NULL,
  `staffID` INT(10) UNSIGNED,
  `sessionData` TEXT,
  `lastAccessed` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `ipAddress` VARCHAR(45),
  `userAgent` TEXT,
  PRIMARY KEY (`sessionID`),
  KEY `idx_session_staff` (`staffID`),
  KEY `idx_session_accessed` (`lastAccessed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add foreign key constraints
ALTER TABLE `cor4edu_program_pricing`
ADD CONSTRAINT `fk_pricing_program`
FOREIGN KEY (`programID`) REFERENCES `cor4edu_programs`(`programID`) ON DELETE CASCADE;

ALTER TABLE `cor4edu_staff_permissions`
ADD CONSTRAINT `fk_permission_staff`
FOREIGN KEY (`staffID`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE CASCADE;

ALTER TABLE `cor4edu_staff_tab_access`
ADD CONSTRAINT `fk_tab_access_staff`
FOREIGN KEY (`staffID`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE CASCADE;

ALTER TABLE `cor4edu_students`
ADD CONSTRAINT `fk_student_program`
FOREIGN KEY (`programID`) REFERENCES `cor4edu_programs`(`programID`);

ALTER TABLE `cor4edu_documents`
ADD CONSTRAINT `fk_document_uploader`
FOREIGN KEY (`uploadedBy`) REFERENCES `cor4edu_staff`(`staffID`);

ALTER TABLE `cor4edu_payments`
ADD CONSTRAINT `fk_payment_student`
FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`);

ALTER TABLE `cor4edu_academic_records`
ADD CONSTRAINT `fk_academic_student`
FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`);

ALTER TABLE `cor4edu_career_services`
ADD CONSTRAINT `fk_career_student`
FOREIGN KEY (`studentID`) REFERENCES `cor4edu_students`(`studentID`);

ALTER TABLE `cor4edu_sessions`
ADD CONSTRAINT `fk_session_staff`
FOREIGN KEY (`staffID`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS = 1;