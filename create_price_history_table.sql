-- Create program price history table if it doesn't exist
-- This table tracks pricing changes over time for student contract protection

CREATE TABLE IF NOT EXISTS `cor4edu_program_price_history` (
  `priceId` VARCHAR(50) NOT NULL COMMENT 'Unique price version identifier',
  `programID` INT(10) UNSIGNED NOT NULL COMMENT 'Reference to program',
  `tuitionAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Base tuition cost',
  `fees` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Program fees',
  `booksAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Books cost',
  `materialsAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Materials cost',
  `applicationFee` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Application fee',
  `miscellaneousCosts` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Miscellaneous costs',
  `totalCost` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Total cost (calculated by triggers)',
  `effectiveDate` DATE NOT NULL COMMENT 'When this pricing became active',
  `createdBy` INT(10) UNSIGNED NOT NULL COMMENT 'Staff member who created this pricing',
  `createdAt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'When this record was created',
  `isActive` BOOLEAN DEFAULT FALSE COMMENT 'Whether this is the current active pricing',
  `description` TEXT NULL COMMENT 'Optional description of pricing changes',
  PRIMARY KEY (`priceId`),
  KEY `idx_price_history_program` (`programID`),
  KEY `idx_price_history_effective` (`effectiveDate`),
  KEY `idx_price_history_active` (`isActive`),
  KEY `idx_price_history_created_by` (`createdBy`),
  CONSTRAINT `fk_price_history_program`
    FOREIGN KEY (`programID`) REFERENCES `cor4edu_programs`(`programID`) ON DELETE CASCADE,
  CONSTRAINT `fk_price_history_created_by`
    FOREIGN KEY (`createdBy`) REFERENCES `cor4edu_staff`(`staffID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
COMMENT='Versioned pricing system for program pricing history and student contract protection';
