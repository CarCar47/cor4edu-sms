-- Comprehensive check for ALL missing tables
-- This checks every table the application expects to exist

SELECT 'CHECKING ALL REQUIRED TABLES' as status;

-- Core Tables
SELECT 'cor4edu_students' as table_name,
       IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING') as status
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_students'
UNION ALL
SELECT 'cor4edu_staff', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_staff'
UNION ALL
SELECT 'cor4edu_programs', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_programs'
UNION ALL
SELECT 'cor4edu_role_types', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_role_types'

-- Document System Tables
UNION ALL
SELECT 'cor4edu_documents', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_documents'
UNION ALL
SELECT 'cor4edu_document_requirements', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_document_requirements'
UNION ALL
SELECT 'cor4edu_student_document_requirements', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_student_document_requirements'

-- Career/Employment Tables
UNION ALL
SELECT 'cor4edu_career_placements', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_career_placements'

-- Faculty Notes System Tables
UNION ALL
SELECT 'cor4edu_faculty_notes', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_faculty_notes'
UNION ALL
SELECT 'cor4edu_student_meetings', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_student_meetings'
UNION ALL
SELECT 'cor4edu_academic_support_sessions', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_academic_support_sessions'
UNION ALL
SELECT 'cor4edu_academic_interventions', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_academic_interventions'

-- Financial System Tables
UNION ALL
SELECT 'cor4edu_payments', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_payments'
UNION ALL
SELECT 'cor4edu_program_prices', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_program_prices'
UNION ALL
SELECT 'cor4edu_program_price_history', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_program_price_history'

-- Permission System Tables
UNION ALL
SELECT 'cor4edu_system_permissions', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_system_permissions'
UNION ALL
SELECT 'cor4edu_role_permission_defaults', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_role_permission_defaults'
UNION ALL
SELECT 'cor4edu_staff_permissions', IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms' AND table_name = 'cor4edu_staff_permissions';
