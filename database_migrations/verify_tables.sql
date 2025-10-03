-- Verify all 6 tables were created successfully
SELECT
    'cor4edu_document_requirements' as table_name,
    IF(COUNT(*) > 0, 'EXISTS', 'MISSING') as status
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms'
  AND table_name = 'cor4edu_document_requirements'

UNION ALL

SELECT
    'cor4edu_student_document_requirements' as table_name,
    IF(COUNT(*) > 0, 'EXISTS', 'MISSING') as status
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms'
  AND table_name = 'cor4edu_student_document_requirements'

UNION ALL

SELECT
    'cor4edu_faculty_notes' as table_name,
    IF(COUNT(*) > 0, 'EXISTS', 'MISSING') as status
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms'
  AND table_name = 'cor4edu_faculty_notes'

UNION ALL

SELECT
    'cor4edu_student_meetings' as table_name,
    IF(COUNT(*) > 0, 'EXISTS', 'MISSING') as status
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms'
  AND table_name = 'cor4edu_student_meetings'

UNION ALL

SELECT
    'cor4edu_academic_support_sessions' as table_name,
    IF(COUNT(*) > 0, 'EXISTS', 'MISSING') as status
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms'
  AND table_name = 'cor4edu_academic_support_sessions'

UNION ALL

SELECT
    'cor4edu_academic_interventions' as table_name,
    IF(COUNT(*) > 0, 'EXISTS', 'MISSING') as status
FROM information_schema.tables
WHERE table_schema = 'cor4edu_sms'
  AND table_name = 'cor4edu_academic_interventions';
