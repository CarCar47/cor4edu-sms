-- Add missing columns one by one
-- MySQL will skip if column already exists (in newer versions)
-- or error which is fine - we just want the missing ones added

SET @dbname = 'cor4edu_sms';
SET @tablename = 'cor4edu_staff';
SET @columnname = 'phone';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT ''Column exists, skipping'' AS msg;',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(20) NULL AFTER roleTypeID;')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add address
SET @columnname = 'address';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = @tablename AND table_schema = @dbname AND column_name = @columnname) > 0,
  'SELECT ''Column exists'' AS msg;',
  'ALTER TABLE cor4edu_staff ADD COLUMN address VARCHAR(255) NULL;'
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add other missing columns
ALTER TABLE cor4edu_staff
ADD COLUMN IF NOT EXISTS city VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS state VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS zipCode VARCHAR(20) NULL,
ADD COLUMN IF NOT EXISTS country VARCHAR(100) DEFAULT 'United States',
ADD COLUMN IF NOT EXISTS emergencyContact VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS emergencyPhone VARCHAR(20) NULL;

SELECT 'Columns added!' AS Status;
