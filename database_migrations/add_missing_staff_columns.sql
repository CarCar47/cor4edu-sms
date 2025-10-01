-- Add missing audit trail columns to cor4edu_staff table
-- These columns are referenced in the staff management code but missing from the table

-- Add lastModifiedBy and lastModifiedOn columns
ALTER TABLE `cor4edu_staff`
ADD COLUMN `lastModifiedBy` int(11) NULL COMMENT 'Staff ID who last modified this record' AFTER `createdBy`,
ADD COLUMN `lastModifiedOn` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'When this record was last modified' AFTER `lastModifiedBy`;

-- Add foreign key constraint for lastModifiedBy (optional, but good for data integrity)
ALTER TABLE `cor4edu_staff`
ADD FOREIGN KEY (`lastModifiedBy`) REFERENCES `cor4edu_staff`(`staffID`) ON DELETE SET NULL;

-- Verify the columns were added
DESCRIBE cor4edu_staff;