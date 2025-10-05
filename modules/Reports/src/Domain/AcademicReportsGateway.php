<?php

namespace Cor4Edu\Reports\Domain;

use PDO;

/**
 * Academic Reports Gateway
 * Handles all academic performance, intervention, and faculty activity queries
 * Based on actual tables: cor4edu_faculty_notes, cor4edu_student_meetings, cor4edu_academic_support_sessions
 */
class AcademicReportsGateway
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get faculty notes summary by program
     * Returns aggregate statistics of faculty notes grouped by program
     *
     * @param array $filters Optional filters (programID, dateStart, dateEnd, category)
     * @return array Faculty notes summary by program
     */
    public function getFacultyNotesSummaryByProgram(array $filters = []): array
    {
        $sql = "SELECT
                    p.programID,
                    p.name as programName,
                    p.programCode,
                    COUNT(DISTINCT fn.noteID) as totalNotes,
                    COUNT(DISTINCT fn.studentID) as studentsWithNotes,
                    COUNT(DISTINCT fn.facultyID) as activeFaculty,
                    SUM(CASE WHEN fn.category = 'positive' THEN 1 ELSE 0 END) as positiveNotes,
                    SUM(CASE WHEN fn.category = 'concern' THEN 1 ELSE 0 END) as concernNotes,
                    SUM(CASE WHEN fn.category = 'neutral' THEN 1 ELSE 0 END) as neutralNotes,
                    SUM(CASE WHEN fn.category = 'disciplinary' THEN 1 ELSE 0 END) as disciplinaryNotes,
                    MAX(fn.createdOn) as lastNoteDate

                FROM cor4edu_programs p
                LEFT JOIN cor4edu_students s ON p.programID = s.programID
                LEFT JOIN cor4edu_faculty_notes fn ON s.studentID = fn.studentID";

        $conditions = ["s.studentID IS NOT NULL"]; // Only programs with students
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "p.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['status'])) {
            $conditions[] = "s.status IN (" . implode(',', array_fill(0, count($filters['status']), '?')) . ")";
            $params = array_merge($params, $filters['status']);
        }

        if (!empty($filters['category'])) {
            $conditions[] = "fn.category IN (" . implode(',', array_fill(0, count($filters['category']), '?')) . ")";
            $params = array_merge($params, $filters['category']);
        }

        if (!empty($filters['dateStart'])) {
            $conditions[] = "fn.createdOn >= ?";
            $params[] = $filters['dateStart'];
        }

        if (!empty($filters['dateEnd'])) {
            $conditions[] = "fn.createdOn <= ?";
            $params[] = $filters['dateEnd'] . ' 23:59:59';
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY p.programID, p.name, p.programCode
                  ORDER BY totalNotes DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get at-risk students based on concerns, disciplinary notes, and intervention meetings
     *
     * @param array $filters Optional filters (programID, status, dateStart, dateEnd)
     * @return array At-risk students with intervention details
     */
    public function getAtRiskStudents(array $filters = []): array
    {
        $sql = "SELECT
                    s.studentID,
                    s.firstName,
                    s.lastName,
                    s.email,
                    s.phone,
                    s.status,
                    p.name as programName,
                    p.programCode,

                    -- Note counts
                    SUM(CASE WHEN fn.category = 'concern' THEN 1 ELSE 0 END) as concernCount,
                    SUM(CASE WHEN fn.category = 'disciplinary' THEN 1 ELSE 0 END) as disciplinaryCount,

                    -- Meeting counts
                    SUM(CASE WHEN sm.meetingType = 'concerned' THEN 1 ELSE 0 END) as concernedMeetings,
                    SUM(CASE WHEN sm.meetingType = 'potential_failure' THEN 1 ELSE 0 END) as potentialFailureMeetings,
                    SUM(CASE WHEN sm.meetingType = 'disciplinary' THEN 1 ELSE 0 END) as disciplinaryMeetings,

                    -- Risk score calculation
                    (SUM(CASE WHEN fn.category = 'disciplinary' THEN 1 ELSE 0 END) * 3 +
                     SUM(CASE WHEN fn.category = 'concern' THEN 1 ELSE 0 END) * 2 +
                     SUM(CASE WHEN sm.meetingType = 'potential_failure' THEN 1 ELSE 0 END) * 2) as riskScore,

                    -- Latest intervention details
                    (SELECT CONCAT(staff.firstName, ' ', staff.lastName)
                     FROM cor4edu_faculty_notes fn2
                     JOIN cor4edu_staff staff ON fn2.facultyID = staff.staffID
                     WHERE fn2.studentID = s.studentID
                     ORDER BY fn2.createdOn DESC LIMIT 1) as lastFacultyMember,

                    (SELECT fn2.createdOn
                     FROM cor4edu_faculty_notes fn2
                     WHERE fn2.studentID = s.studentID
                     ORDER BY fn2.createdOn DESC LIMIT 1) as lastInterventionDate,

                    (SELECT fn2.category
                     FROM cor4edu_faculty_notes fn2
                     WHERE fn2.studentID = s.studentID
                     ORDER BY fn2.createdOn DESC LIMIT 1) as lastInterventionType,

                    -- Pending follow-ups
                    COUNT(DISTINCT CASE WHEN ass.followUpRequired = 'Y' AND ass.followUpDate IS NULL THEN ass.sessionID END) as pendingFollowUps

                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_faculty_notes fn ON s.studentID = fn.studentID
                LEFT JOIN cor4edu_student_meetings sm ON s.studentID = sm.studentID
                LEFT JOIN cor4edu_academic_support_sessions ass ON s.studentID = ass.studentID";

        $conditions = [];
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "s.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['status'])) {
            $conditions[] = "s.status IN (" . implode(',', array_fill(0, count($filters['status']), '?')) . ")";
            $params = array_merge($params, $filters['status']);
        }

        if (!empty($filters['dateStart'])) {
            $conditions[] = "(fn.createdOn >= ? OR sm.meetingDate >= ? OR ass.sessionDate >= ?)";
            $params[] = $filters['dateStart'];
            $params[] = $filters['dateStart'];
            $params[] = $filters['dateStart'];
        }

        if (!empty($filters['dateEnd'])) {
            $conditions[] = "(fn.createdOn <= ? OR sm.meetingDate <= ? OR ass.sessionDate <= ?)";
            $params[] = $filters['dateEnd'] . ' 23:59:59';
            $params[] = $filters['dateEnd'];
            $params[] = $filters['dateEnd'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY s.studentID, s.firstName, s.lastName, s.email, s.phone, s.status, p.name, p.programCode
                  HAVING (concernCount > 0 OR disciplinaryCount > 0 OR concernedMeetings > 0 OR potentialFailureMeetings > 0 OR disciplinaryMeetings > 0)
                  ORDER BY riskScore DESC, lastInterventionDate DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get student engagement details - support sessions and intervention tracking
     *
     * @param array $filters Optional filters (programID, status, dateStart, dateEnd)
     * @return array Student engagement details
     */
    public function getStudentEngagementDetails(array $filters = []): array
    {
        $sql = "SELECT
                    s.studentID,
                    s.firstName,
                    s.lastName,
                    s.email,
                    s.phone,
                    s.status,
                    p.name as programName,
                    p.programCode,

                    -- Support session statistics
                    COUNT(DISTINCT ass.sessionID) as totalSupportSessions,
                    SUM(CASE WHEN ass.sessionType = 'tutoring' THEN 1 ELSE 0 END) as tutoringSessions,
                    SUM(CASE WHEN ass.sessionType = 'study_group' THEN 1 ELSE 0 END) as studyGroupSessions,
                    SUM(CASE WHEN ass.sessionType = 'counseling' THEN 1 ELSE 0 END) as counselingSessions,
                    SUM(CASE WHEN ass.sessionType = 'other' THEN 1 ELSE 0 END) as otherSessions,
                    SUM(COALESCE(ass.duration, 0)) as totalMinutesOfSupport,
                    MAX(ass.sessionDate) as lastSupportDate,

                    -- Faculty notes statistics
                    COUNT(DISTINCT fn.noteID) as totalNotes,
                    SUM(CASE WHEN fn.category = 'positive' THEN 1 ELSE 0 END) as positiveNotes,
                    SUM(CASE WHEN fn.category = 'concern' THEN 1 ELSE 0 END) as concernNotes,
                    SUM(CASE WHEN fn.category = 'neutral' THEN 1 ELSE 0 END) as neutralNotes,
                    SUM(CASE WHEN fn.category = 'disciplinary' THEN 1 ELSE 0 END) as disciplinaryNotes,

                    -- Meeting statistics
                    COUNT(DISTINCT sm.meetingID) as totalMeetings,
                    MAX(sm.meetingDate) as lastMeetingDate

                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_academic_support_sessions ass ON s.studentID = ass.studentID
                LEFT JOIN cor4edu_faculty_notes fn ON s.studentID = fn.studentID
                LEFT JOIN cor4edu_student_meetings sm ON s.studentID = sm.studentID";

        $conditions = [];
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "s.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['status'])) {
            $conditions[] = "s.status IN (" . implode(',', array_fill(0, count($filters['status']), '?')) . ")";
            $params = array_merge($params, $filters['status']);
        }

        if (!empty($filters['dateStart'])) {
            $conditions[] = "(ass.sessionDate >= ? OR fn.createdOn >= ? OR sm.meetingDate >= ?)";
            $params[] = $filters['dateStart'];
            $params[] = $filters['dateStart'];
            $params[] = $filters['dateStart'];
        }

        if (!empty($filters['dateEnd'])) {
            $conditions[] = "(ass.sessionDate <= ? OR fn.createdOn <= ? OR sm.meetingDate <= ?)";
            $params[] = $filters['dateEnd'];
            $params[] = $filters['dateEnd'] . ' 23:59:59';
            $params[] = $filters['dateEnd'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY s.studentID, s.firstName, s.lastName, s.email, s.phone, s.status, p.name, p.programCode
                  HAVING (totalSupportSessions > 0 OR totalNotes > 0 OR totalMeetings > 0)
                  ORDER BY totalSupportSessions DESC, totalNotes DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get chronological intervention activity log for students
     *
     * @param array $filters Optional filters (programID, status, studentID, dateStart, dateEnd, category, meetingType)
     * @return array Intervention activity log entries
     */
    public function getInterventionActivityLog(array $filters = []): array
    {
        // Union query to combine notes, meetings, and support sessions
        $sql = "SELECT * FROM (
                    SELECT
                        'note' as recordType,
                        fn.noteID as recordID,
                        fn.createdOn as activityDate,
                        s.studentID,
                        s.firstName,
                        s.lastName,
                        s.email,
                        s.phone,
                        s.status,
                        p.programID,
                        p.name as programName,
                        p.programCode,
                        CONCAT(staff.firstName, ' ', staff.lastName) as facultyMember,
                        fn.category as typeCategory,
                        LEFT(fn.content, 200) as description,
                        NULL as followUpStatus
                    FROM cor4edu_faculty_notes fn
                    JOIN cor4edu_students s ON fn.studentID = s.studentID
                    JOIN cor4edu_programs p ON s.programID = p.programID
                    JOIN cor4edu_staff staff ON fn.facultyID = staff.staffID

                    UNION ALL

                    SELECT
                        'meeting' as recordType,
                        sm.meetingID as recordID,
                        sm.meetingDate as activityDate,
                        s.studentID,
                        s.firstName,
                        s.lastName,
                        s.email,
                        s.phone,
                        s.status,
                        p.programID,
                        p.name as programName,
                        p.programCode,
                        CONCAT(staff.firstName, ' ', staff.lastName) as facultyMember,
                        sm.meetingType as typeCategory,
                        LEFT(sm.topicsDiscussed, 200) as description,
                        CASE WHEN sm.nextMeetingDate IS NOT NULL THEN 'scheduled' ELSE 'completed' END as followUpStatus
                    FROM cor4edu_student_meetings sm
                    JOIN cor4edu_students s ON sm.studentID = s.studentID
                    JOIN cor4edu_programs p ON s.programID = p.programID
                    JOIN cor4edu_staff staff ON sm.facultyID = staff.staffID

                    UNION ALL

                    SELECT
                        'support_session' as recordType,
                        ass.sessionID as recordID,
                        ass.sessionDate as activityDate,
                        s.studentID,
                        s.firstName,
                        s.lastName,
                        s.email,
                        s.phone,
                        s.status,
                        p.programID,
                        p.name as programName,
                        p.programCode,
                        CONCAT(staff.firstName, ' ', staff.lastName) as facultyMember,
                        ass.sessionType as typeCategory,
                        LEFT(ass.description, 200) as description,
                        CASE WHEN ass.followUpRequired = 'Y' THEN
                            CASE WHEN ass.followUpDate IS NOT NULL THEN 'scheduled' ELSE 'required' END
                        ELSE 'none' END as followUpStatus
                    FROM cor4edu_academic_support_sessions ass
                    JOIN cor4edu_students s ON ass.studentID = s.studentID
                    JOIN cor4edu_programs p ON s.programID = p.programID
                    JOIN cor4edu_staff staff ON ass.facultyID = staff.staffID
                ) combined_activities
                WHERE 1=1";

        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $sql .= " AND programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['status'])) {
            $sql .= " AND status IN (" . implode(',', array_fill(0, count($filters['status']), '?')) . ")";
            $params = array_merge($params, $filters['status']);
        }

        if (!empty($filters['studentID'])) {
            $sql .= " AND studentID = ?";
            $params[] = $filters['studentID'];
        }

        if (!empty($filters['category'])) {
            $sql .= " AND typeCategory IN (" . implode(',', array_fill(0, count($filters['category']), '?')) . ")";
            $params = array_merge($params, $filters['category']);
        }

        if (!empty($filters['dateStart'])) {
            $sql .= " AND activityDate >= ?";
            $params[] = $filters['dateStart'];
        }

        if (!empty($filters['dateEnd'])) {
            $sql .= " AND activityDate <= ?";
            $params[] = $filters['dateEnd'] . ' 23:59:59';
        }

        $sql .= " ORDER BY activityDate DESC, studentID";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get faculty activity report - track faculty engagement with students
     *
     * @param array $filters Optional filters (programID, facultyID, dateStart, dateEnd)
     * @return array Faculty activity statistics
     */
    public function getFacultyActivityReport(array $filters = []): array
    {
        $sql = "SELECT
                    staff.staffID,
                    CONCAT(staff.firstName, ' ', staff.lastName) as facultyName,
                    staff.email as facultyEmail,

                    -- Note statistics
                    COUNT(DISTINCT fn.noteID) as totalNotes,
                    SUM(CASE WHEN fn.category = 'positive' THEN 1 ELSE 0 END) as positiveNotes,
                    SUM(CASE WHEN fn.category = 'concern' THEN 1 ELSE 0 END) as concernNotes,
                    SUM(CASE WHEN fn.category = 'neutral' THEN 1 ELSE 0 END) as neutralNotes,
                    SUM(CASE WHEN fn.category = 'disciplinary' THEN 1 ELSE 0 END) as disciplinaryNotes,

                    -- Meeting statistics
                    COUNT(DISTINCT sm.meetingID) as totalMeetings,
                    SUM(CASE WHEN sm.meetingType = 'concerned' THEN 1 ELSE 0 END) as concernedMeetings,
                    SUM(CASE WHEN sm.meetingType = 'potential_failure' THEN 1 ELSE 0 END) as potentialFailureMeetings,
                    SUM(CASE WHEN sm.meetingType = 'normal' THEN 1 ELSE 0 END) as normalMeetings,
                    SUM(CASE WHEN sm.meetingType = 'disciplinary' THEN 1 ELSE 0 END) as disciplinaryMeetings,

                    -- Support session statistics
                    COUNT(DISTINCT ass.sessionID) as totalSupportSessions,
                    SUM(COALESCE(ass.duration, 0)) as totalSupportMinutes,

                    -- Student reach
                    COUNT(DISTINCT COALESCE(fn.studentID, sm.studentID, ass.studentID)) as uniqueStudentsHelped,

                    -- Program reach
                    COUNT(DISTINCT s.programID) as programsServed,

                    -- Recent activity
                    MAX(GREATEST(
                        COALESCE(fn.createdOn, '1970-01-01'),
                        COALESCE(sm.meetingDate, '1970-01-01'),
                        COALESCE(ass.sessionDate, '1970-01-01')
                    )) as lastActivityDate

                FROM cor4edu_staff staff
                LEFT JOIN cor4edu_faculty_notes fn ON staff.staffID = fn.facultyID
                LEFT JOIN cor4edu_student_meetings sm ON staff.staffID = sm.facultyID
                LEFT JOIN cor4edu_academic_support_sessions ass ON staff.staffID = ass.facultyID
                LEFT JOIN cor4edu_students s ON (fn.studentID = s.studentID OR sm.studentID = s.studentID OR ass.studentID = s.studentID)";

        $conditions = [];
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "s.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['facultyID'])) {
            $conditions[] = "staff.staffID = ?";
            $params[] = $filters['facultyID'];
        }

        if (!empty($filters['dateStart'])) {
            $conditions[] = "(fn.createdOn >= ? OR sm.meetingDate >= ? OR ass.sessionDate >= ?)";
            $params[] = $filters['dateStart'];
            $params[] = $filters['dateStart'];
            $params[] = $filters['dateStart'];
        }

        if (!empty($filters['dateEnd'])) {
            $conditions[] = "(fn.createdOn <= ? OR sm.meetingDate <= ? OR ass.sessionDate <= ?)";
            $params[] = $filters['dateEnd'] . ' 23:59:59';
            $params[] = $filters['dateEnd'];
            $params[] = $filters['dateEnd'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY staff.staffID, staff.firstName, staff.lastName, staff.email
                  HAVING (totalNotes > 0 OR totalMeetings > 0 OR totalSupportSessions > 0)
                  ORDER BY (totalNotes + totalMeetings + totalSupportSessions) DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get available faculty members for filter dropdown
     *
     * @return array Available faculty members
     */
    public function getAvailableFaculty(): array
    {
        $sql = "SELECT DISTINCT
                    staff.staffID as facultyID,
                    CONCAT(staff.firstName, ' ', staff.lastName) as facultyName
                FROM cor4edu_staff staff
                WHERE staff.staffID IN (
                    SELECT facultyID FROM cor4edu_faculty_notes
                    UNION
                    SELECT facultyID FROM cor4edu_student_meetings
                    UNION
                    SELECT facultyID FROM cor4edu_academic_support_sessions
                )
                ORDER BY facultyName";

        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get available note categories for filter
     *
     * @return array Available categories
     */
    public function getAvailableNoteCategories(): array
    {
        return [
            ['value' => 'positive', 'label' => 'Positive'],
            ['value' => 'concern', 'label' => 'Concern'],
            ['value' => 'neutral', 'label' => 'Neutral'],
            ['value' => 'disciplinary', 'label' => 'Disciplinary']
        ];
    }

    /**
     * Get available meeting types for filter
     *
     * @return array Available meeting types
     */
    public function getAvailableMeetingTypes(): array
    {
        return [
            ['value' => 'concerned', 'label' => 'Concerned Student'],
            ['value' => 'potential_failure', 'label' => 'Potential Failure'],
            ['value' => 'normal', 'label' => 'Regular Check-in'],
            ['value' => 'disciplinary', 'label' => 'Disciplinary']
        ];
    }
}
