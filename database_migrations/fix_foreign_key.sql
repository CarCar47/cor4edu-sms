-- Fix the replacesDocumentID column type and add foreign key constraint

-- First, modify the column to match documentID type exactly
ALTER TABLE `cor4edu_documents`
MODIFY COLUMN `replacesDocumentID` int(10) unsigned NULL COMMENT 'If this document replaces another';

-- Now add the foreign key constraint
ALTER TABLE `cor4edu_documents`
ADD FOREIGN KEY (`replacesDocumentID`) REFERENCES `cor4edu_documents`(`documentID`) ON DELETE SET NULL;