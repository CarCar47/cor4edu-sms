-- Verify academic date columns were added successfully
SELECT
    'anticipatedGraduationDate' as column_name,
    IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING') as status
FROM information_schema.columns
WHERE table_schema = 'cor4edu_sms'
  AND table_name = 'cor4edu_students'
  AND column_name = 'anticipatedGraduationDate'

UNION ALL

SELECT
    'actualGraduationDate',
    IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.columns
WHERE table_schema = 'cor4edu_sms'
  AND table_name = 'cor4edu_students'
  AND column_name = 'actualGraduationDate'

UNION ALL

SELECT
    'lastDayOfAttendance',
    IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.columns
WHERE table_schema = 'cor4edu_sms'
  AND table_name = 'cor4edu_students'
  AND column_name = 'lastDayOfAttendance'

UNION ALL

SELECT
    'withdrawnDate',
    IF(COUNT(*) > 0, '✅ EXISTS', '❌ MISSING')
FROM information_schema.columns
WHERE table_schema = 'cor4edu_sms'
  AND table_name = 'cor4edu_students'
  AND column_name = 'withdrawnDate';
