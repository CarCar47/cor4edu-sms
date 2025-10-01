-- Add only the missing columns to cor4edu_documents table

-- Add expirationDate column
ALTER TABLE `cor4edu_documents`
ADD COLUMN `expirationDate` DATE NULL COMMENT 'For documents that expire' AFTER `subcategory`;

-- Add replacesDocumentID column
ALTER TABLE `cor4edu_documents`
ADD COLUMN `replacesDocumentID` int(11) NULL COMMENT 'If this document replaces another' AFTER `expirationDate`;

-- Add foreign key constraint
ALTER TABLE `cor4edu_documents`
ADD FOREIGN KEY (`replacesDocumentID`) REFERENCES `cor4edu_documents`(`documentID`) ON DELETE SET NULL;