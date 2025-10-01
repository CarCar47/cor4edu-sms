-- Faculty Notes and Academic Support System
-- Creates tables for comprehensive faculty documentation and student support tracking

-- Faculty Notes Table
CREATE TABLE IF NOT EXISTS `cor4edu_faculty_notes` (
    `noteID` int(11) NOT NULL AUTO_INCREMENT,
    `studentID` int(11) NOT NULL,
    `facultyID` int(11) NOT NULL,
    `category` ENUM('positive', 'concern', 'neutral', 'disciplinary') NOT NULL DEFAULT 'neutral' COMMENT 'Note categorization for quick identification',
    `content` TEXT NOT NULL COMMENT 'The actual note content',
    `isPrivate` ENUM('Y', 'N') NOT NULL DEFAULT 'N' COMMENT 'Whether note is visible to other faculty',
    `createdOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `createdBy` int(11) NOT NULL,
    `modifiedOn` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    `modifiedBy` int(11) NULL,
    PRIMARY KEY (`noteID`),
    KEY `student_notes` (`studentID`),
    KEY `faculty_notes` (`facultyID`),
    KEY `note_category` (`category`),
    KEY `note_date` (`createdOn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Faculty notes system for documenting student academic progress and interventions';

-- Academic Support Sessions Table
CREATE TABLE IF NOT EXISTS `cor4edu_academic_support_sessions` (
    `sessionID` int(11) NOT NULL AUTO_INCREMENT,
    `studentID` int(11) NOT NULL,
    `facultyID` int(11) NOT NULL,
    `sessionType` ENUM('tutoring', 'study_group', 'counseling', 'other') NOT NULL COMMENT 'Type of support session',
    `sessionDate` DATE NOT NULL,
    `duration` int(11) NULL COMMENT 'Duration in minutes',
    `subject` varchar(100) NULL COMMENT 'Subject or topic covered',
    `description` TEXT NOT NULL COMMENT 'Details of what was covered or discussed',
    `participants` TEXT NULL COMMENT 'Other participants if group session',
    `followUpRequired` ENUM('Y', 'N') NOT NULL DEFAULT 'N',
    `followUpDate` DATE NULL,
    `followUpNotes` TEXT NULL,
    `createdOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `createdBy` int(11) NOT NULL,
    `modifiedOn` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    `modifiedBy` int(11) NULL,
    PRIMARY KEY (`sessionID`),
    KEY `student_sessions` (`studentID`),
    KEY `faculty_sessions` (`facultyID`),
    KEY `session_type` (`sessionType`),
    KEY `session_date` (`sessionDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Academic support sessions tracking (tutoring, study groups, counseling)';

-- Student Meetings Table
CREATE TABLE IF NOT EXISTS `cor4edu_student_meetings` (
    `meetingID` int(11) NOT NULL AUTO_INCREMENT,
    `studentID` int(11) NOT NULL,
    `facultyID` int(11) NOT NULL,
    `meetingDate` DATE NOT NULL,
    `meetingType` ENUM('concerned', 'potential_failure', 'normal', 'disciplinary') NOT NULL COMMENT 'Type of meeting for categorization',
    `topicsDiscussed` TEXT NOT NULL COMMENT 'Main topics covered in the meeting',
    `currentPerformance` TEXT NULL COMMENT 'Notes about current academic performance',
    `upcomingAssessments` TEXT NULL COMMENT 'Upcoming quizzes, tests, or assignments discussed',
    `actionItems` TEXT NULL COMMENT 'Follow-up actions and next steps',
    `nextMeetingDate` DATE NULL,
    `parentNotified` ENUM('Y', 'N') NOT NULL DEFAULT 'N',
    `parentNotificationDate` DATE NULL,
    `createdOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `createdBy` int(11) NOT NULL,
    `modifiedOn` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    `modifiedBy` int(11) NULL,
    PRIMARY KEY (`meetingID`),
    KEY `student_meetings` (`studentID`),
    KEY `faculty_meetings` (`facultyID`),
    KEY `meeting_type` (`meetingType`),
    KEY `meeting_date` (`meetingDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'One-on-one student meetings documentation and tracking';

-- Academic Interventions Table (for tracking systematic interventions)
CREATE TABLE IF NOT EXISTS `cor4edu_academic_interventions` (
    `interventionID` int(11) NOT NULL AUTO_INCREMENT,
    `studentID` int(11) NOT NULL,
    `facultyID` int(11) NOT NULL,
    `interventionType` ENUM('tutoring_plan', 'study_schedule', 'accommodation', 'counseling_referral', 'parent_contact', 'other') NOT NULL,
    `description` TEXT NOT NULL COMMENT 'Details of the intervention',
    `startDate` DATE NOT NULL,
    `targetDate` DATE NULL COMMENT 'Expected completion or review date',
    `status` ENUM('planned', 'active', 'completed', 'discontinued') NOT NULL DEFAULT 'planned',
    `outcome` TEXT NULL COMMENT 'Results and effectiveness of intervention',
    `relatedSessionID` int(11) NULL COMMENT 'Link to support session if applicable',
    `relatedMeetingID` int(11) NULL COMMENT 'Link to meeting if applicable',
    `createdOn` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `createdBy` int(11) NOT NULL,
    `modifiedOn` DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    `modifiedBy` int(11) NULL,
    PRIMARY KEY (`interventionID`),
    KEY `student_interventions` (`studentID`),
    KEY `faculty_interventions` (`facultyID`),
    KEY `intervention_type` (`interventionType`),
    KEY `intervention_status` (`status`),
    FOREIGN KEY (`relatedSessionID`) REFERENCES `cor4edu_academic_support_sessions`(`sessionID`) ON DELETE SET NULL,
    FOREIGN KEY (`relatedMeetingID`) REFERENCES `cor4edu_student_meetings`(`meetingID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT = 'Systematic academic interventions and their tracking';

-- Add indexes for better performance on common queries
CREATE INDEX `idx_faculty_notes_student_date` ON `cor4edu_faculty_notes` (`studentID`, `createdOn`);
CREATE INDEX `idx_support_sessions_student_date` ON `cor4edu_academic_support_sessions` (`studentID`, `sessionDate`);
CREATE INDEX `idx_meetings_student_date` ON `cor4edu_student_meetings` (`studentID`, `meetingDate`);
CREATE INDEX `idx_interventions_student_status` ON `cor4edu_academic_interventions` (`studentID`, `status`);