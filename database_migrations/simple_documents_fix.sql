-- Simple migration to add missing columns to documents table
-- Run each statement individually if you get errors

-- Add expirationDate column (if it doesn't exist)
ALTER TABLE `cor4edu_documents`
ADD COLUMN `expirationDate` DATE NULL COMMENT 'For documents that expire' AFTER `subcategory`;

-- Add replacesDocumentID column (if it doesn't exist)
ALTER TABLE `cor4edu_documents`
ADD COLUMN `replacesDocumentID` int(11) NULL COMMENT 'If this document replaces another' AFTER `expirationDate`;

-- Add foreign key constraint (if it doesn't exist)
ALTER TABLE `cor4edu_documents`
ADD FOREIGN KEY (`replacesDocumentID`) REFERENCES `cor4edu_documents`(`documentID`) ON DELETE SET NULL;