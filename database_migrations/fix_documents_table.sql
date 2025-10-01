-- Safe migration to fix documents table - checks for existing columns
-- Run this in phpMyAdmin to safely add missing columns

-- Check and add subcategory column if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'cor4edu_documents'
  AND COLUMN_NAME = 'subcategory';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `cor4edu_documents` ADD COLUMN `subcategory` varchar(50) NULL COMMENT ''For required vs other documents'' AFTER `category`',
    'SELECT ''Column subcategory already exists'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add expirationDate column if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'cor4edu_documents'
  AND COLUMN_NAME = 'expirationDate';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `cor4edu_documents` ADD COLUMN `expirationDate` DATE NULL COMMENT ''For documents that expire'' AFTER `subcategory`',
    'SELECT ''Column expirationDate already exists'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add replacesDocumentID column if it doesn't exist
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'cor4edu_documents'
  AND COLUMN_NAME = 'replacesDocumentID';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `cor4edu_documents` ADD COLUMN `replacesDocumentID` int(11) NULL COMMENT ''If this document replaces another'' AFTER `expirationDate`',
    'SELECT ''Column replacesDocumentID already exists'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add foreign key constraint if it doesn't exist
SET @fk_exists = 0;
SELECT COUNT(*) INTO @fk_exists
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'cor4edu_documents'
  AND COLUMN_NAME = 'replacesDocumentID'
  AND REFERENCED_TABLE_NAME = 'cor4edu_documents';

SET @sql = IF(@fk_exists = 0 AND @col_exists > 0,
    'ALTER TABLE `cor4edu_documents` ADD FOREIGN KEY (`replacesDocumentID`) REFERENCES `cor4edu_documents`(`documentID`) ON DELETE SET NULL',
    'SELECT ''Foreign key constraint already exists or column missing'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;