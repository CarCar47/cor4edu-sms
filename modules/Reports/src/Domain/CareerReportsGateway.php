<?php
/**
 * COR4EDU SMS Career Services Reports Gateway
 * Specialized gateway for career placement and employment tracking reports
 */

namespace Cor4Edu\Reports\Domain;

class CareerReportsGateway
{
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get placement rates summary by program
     * @param array $filters Optional filters
     * @return array Placement statistics
     */
    public function getPlacementSummaryByProgram(array $filters = []): array
    {
        $sql = "SELECT
                    p.programID,
                    p.name as programName,
                    p.programCode,

                    -- Graduate counts
                    COUNT(DISTINCT s.studentID) as totalGraduates,

                    -- Employment status breakdown
                    SUM(CASE WHEN cp.employmentStatus IN ('employed_related', 'employed_unrelated', 'self_employed_related', 'self_employed_unrelated') THEN 1 ELSE 0 END) as employedCount,
                    SUM(CASE WHEN cp.employmentStatus = 'not_employed_seeking' THEN 1 ELSE 0 END) as jobSeekingCount,
                    SUM(CASE WHEN cp.employmentStatus = 'continuing_education' THEN 1 ELSE 0 END) as continuingEducationCount,
                    0 as notSeekingCount,
                    0 as militaryServiceCount,
                    0 as unknownStatusCount,

                    -- Placement rate calculation
                    CASE
                        WHEN COUNT(DISTINCT s.studentID) > 0 THEN
                            ROUND((SUM(CASE WHEN cp.employmentStatus IN ('employed_related', 'employed_unrelated', 'self_employed_related', 'self_employed_unrelated') THEN 1 ELSE 0 END) * 100.0) / COUNT(DISTINCT s.studentID), 2)
                        ELSE 0
                    END as placementRate,

                    -- Average time to placement (in days)
                    AVG(CASE
                        WHEN cp.employmentStatus IN ('employed_related', 'employed_unrelated', 'self_employed_related', 'self_employed_unrelated') AND cp.employmentDate IS NOT NULL
                        AND (CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END) IS NOT NULL
                        THEN DATEDIFF(cp.employmentDate, (CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END))
                        ELSE NULL
                    END) as avgDaysToPlacement,

                    -- Field-related employment
                    -- Field-related employment tracking not available in current schema
                    0 as fieldRelatedEmployment,

                    -- Salary information
                    AVG(CASE WHEN cp.employmentStatus IN ('employed_related', 'employed_unrelated', 'self_employed_related', 'self_employed_unrelated') AND cp.salaryExact > 0 THEN cp.salaryExact ELSE NULL END) as avgStartingSalary

                FROM cor4edu_programs p
                LEFT JOIN cor4edu_students s ON p.programID = s.programID
                LEFT JOIN cor4edu_career_placements cp ON s.studentID = cp.studentID AND cp.isCurrentRecord = 'Y'";

        $conditions = ["s.studentID IS NOT NULL"]; // Only include programs with students
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "p.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['studentStatus'])) {
            $conditions[] = "s.status IN (" . implode(',', array_fill(0, count($filters['studentStatus']), '?')) . ")";
            $params = array_merge($params, $filters['studentStatus']);
        }

        if (!empty($filters['graduationDateStart'])) {
            $conditions[] = "(CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END) >= ?";
            $params[] = $filters['graduationDateStart'];
        }

        if (!empty($filters['graduationDateEnd'])) {
            $conditions[] = "(CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END) <= ?";
            $params[] = $filters['graduationDateEnd'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY p.programID, p.name, p.programCode
                  ORDER BY placementRate DESC, p.name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get detailed career tracking report for individual students
     * @param array $filters Optional filters
     * @return array Student career details
     */
    public function getStudentCareerDetails(array $filters = []): array
    {
        $sql = "SELECT
                    s.studentID,
                    s.firstName,
                    s.lastName,
                    s.email,
                    s.phone,
                    CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END as graduationDate,
                    p.name as programName,
                    p.programCode,

                    -- Employment information
                    cp.employmentStatus,
                    cp.employerName,
                    cp.jobTitle,
                    cp.employmentDate,
                    cp.salaryExact,


                    -- Time calculations
                    CASE
                        WHEN cp.employmentDate IS NOT NULL AND (CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END) IS NOT NULL
                        THEN DATEDIFF(cp.employmentDate, (CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END))
                        ELSE NULL
                    END as daysToPlacement,

                    -- License/Certification
                    cp.requiresLicense,
                    cp.licenseObtained,
                    cp.licenseType,

                    -- Job application tracking
                    ja.totalApplications,
                    ja.interviewsReceived,
                    ja.offersReceived

                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_career_placements cp ON s.studentID = cp.studentID AND cp.isCurrentRecord = 'Y'
                LEFT JOIN (
                    SELECT
                        studentID,
                        COUNT(*) as totalApplications,
                        SUM(CASE WHEN status = 'Interview' THEN 1 ELSE 0 END) as interviewsReceived,
                        SUM(CASE WHEN status = 'Offer' THEN 1 ELSE 0 END) as offersReceived
                    FROM cor4edu_job_applications
                    GROUP BY studentID
                ) ja ON s.studentID = ja.studentID";

        $conditions = []; // Status filter applied via WHERE clause, not hardcoded
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "s.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['studentStatus'])) {
            $conditions[] = "s.status IN (" . implode(',', array_fill(0, count($filters['studentStatus']), '?')) . ")";
            $params = array_merge($params, $filters['studentStatus']);
        }

        if (!empty($filters['employmentStatus'])) {
            // Handle empty string as NULL filter
            $hasNullFilter = in_array('', $filters['employmentStatus'], true);
            $nonNullStatuses = array_filter($filters['employmentStatus'], function($val) { return $val !== ''; });

            if ($hasNullFilter && !empty($nonNullStatuses)) {
                // Both NULL and specific statuses
                $conditions[] = "(cp.employmentStatus IS NULL OR cp.employmentStatus IN (" . implode(',', array_fill(0, count($nonNullStatuses), '?')) . "))";
                $params = array_merge($params, array_values($nonNullStatuses));
            } elseif ($hasNullFilter) {
                // Only NULL
                $conditions[] = "cp.employmentStatus IS NULL";
            } else {
                // Only specific statuses
                $conditions[] = "cp.employmentStatus IN (" . implode(',', array_fill(0, count($nonNullStatuses), '?')) . ")";
                $params = array_merge($params, array_values($nonNullStatuses));
            }
        }

        if (!empty($filters['graduationDateStart'])) {
            $conditions[] = "(CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END) >= ?";
            $params[] = $filters['graduationDateStart'];
        }

        if (!empty($filters['graduationDateEnd'])) {
            $conditions[] = "(CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END) <= ?";
            $params[] = $filters['graduationDateEnd'];
        }

        // Field-related filtering not available in current schema
        // if (!empty($filters['fieldRelatedOnly']) && $filters['fieldRelatedOnly']) {
        //     $conditions[] = "cs.isFieldRelated = 'Y'";
        // }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY graduationDate DESC, s.lastName, s.firstName";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get job application tracking report
     * @param array $filters Optional filters
     * @return array Job application statistics
     */
    public function getJobApplicationReport(array $filters = []): array
    {
        $sql = "SELECT
                    s.studentID,
                    s.firstName,
                    s.lastName,
                    p.name as programName,
                    s.actualGraduationDate,

                    -- Application statistics
                    COUNT(ja.applicationID) as totalApplications,
                    SUM(CASE WHEN ja.status = 'Applied' THEN 1 ELSE 0 END) as appliedCount,
                    SUM(CASE WHEN ja.status = 'Interview' THEN 1 ELSE 0 END) as interviewCount,
                    SUM(CASE WHEN ja.status = 'Offer' THEN 1 ELSE 0 END) as offerCount,
                    SUM(CASE WHEN ja.status = 'Rejected' THEN 1 ELSE 0 END) as rejectedCount,
                    SUM(CASE WHEN ja.status = 'Withdrawn' THEN 1 ELSE 0 END) as withdrawnCount,

                    -- Success rates
                    CASE
                        WHEN COUNT(ja.applicationID) > 0 THEN
                            ROUND((SUM(CASE WHEN ja.status = 'Interview' THEN 1 ELSE 0 END) * 100.0) / COUNT(ja.applicationID), 2)
                        ELSE 0
                    END as interviewRate,

                    CASE
                        WHEN COUNT(ja.applicationID) > 0 THEN
                            ROUND((SUM(CASE WHEN ja.status = 'Offer' THEN 1 ELSE 0 END) * 100.0) / COUNT(ja.applicationID), 2)
                        ELSE 0
                    END as offerRate,

                    -- Latest application activity
                    MAX(ja.applicationDate) as lastApplicationDate,

                    -- Current employment status
                    cp.employmentStatus as currentEmploymentStatus

                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_job_applications ja ON s.studentID = ja.studentID
                LEFT JOIN cor4edu_career_placements cp ON s.studentID = cp.studentID AND cp.isCurrentRecord = 'Y'";

        $conditions = ["s.status = 'Graduated'"];
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "s.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['applicationDateStart'])) {
            $conditions[] = "ja.applicationDate >= ?";
            $params[] = $filters['applicationDateStart'];
        }

        if (!empty($filters['applicationDateEnd'])) {
            $conditions[] = "ja.applicationDate <= ?";
            $params[] = $filters['applicationDateEnd'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY s.studentID, s.firstName, s.lastName, p.name, s.actualGraduationDate, cp.employmentStatus
                  ORDER BY totalApplications DESC, s.lastName, s.firstName";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get compliance report for regulatory requirements
     * @param array $filters Optional filters
     * @return array Compliance data
     */
    public function getComplianceReport(array $filters = []): array
    {
        $sql = "SELECT
                    p.programID,
                    p.name as programName,

                    -- Graduate counts for compliance calculations
                    COUNT(DISTINCT s.studentID) as totalGraduates,

                    -- Employment verification status
                    SUM(CASE WHEN cp.verificationDate IS NOT NULL AND cp.verifiedBy IS NOT NULL THEN 1 ELSE 0 END) as verifiedCount,
                    SUM(CASE WHEN cp.verificationDate IS NULL OR cp.verifiedBy IS NULL THEN 1 ELSE 0 END) as pendingVerification,
                    0 as unableToContact,

                    -- Verification rate
                    CASE
                        WHEN COUNT(DISTINCT s.studentID) > 0 THEN
                            ROUND((SUM(CASE WHEN cp.verificationDate IS NOT NULL AND cp.verifiedBy IS NOT NULL THEN 1 ELSE 0 END) * 100.0) / COUNT(DISTINCT s.studentID), 2)
                        ELSE 0
                    END as verificationRate,

                    -- Licensure tracking (for programs requiring licenses)
                    SUM(CASE WHEN cp.requiresLicense = 'Y' AND cp.licenseObtained = 'Y' THEN 1 ELSE 0 END) as licensesObtained,
                    SUM(CASE WHEN cp.requiresLicense = 'Y' THEN 1 ELSE 0 END) as licensesRequired,

                    -- Licensure rate
                    CASE
                        WHEN SUM(CASE WHEN cp.requiresLicense = 'Y' THEN 1 ELSE 0 END) > 0 THEN
                            ROUND((SUM(CASE WHEN cp.requiresLicense = 'Y' AND cp.licenseObtained = 'Y' THEN 1 ELSE 0 END) * 100.0) /
                                  SUM(CASE WHEN cp.requiresLicense = 'Y' THEN 1 ELSE 0 END), 2)
                        ELSE NULL
                    END as licensureRate,

                    -- Data collection completeness
                    SUM(CASE WHEN cp.placementID IS NOT NULL THEN 1 ELSE 0 END) as recordsWithData,

                    CASE
                        WHEN COUNT(DISTINCT s.studentID) > 0 THEN
                            ROUND((SUM(CASE WHEN cp.placementID IS NOT NULL THEN 1 ELSE 0 END) * 100.0) / COUNT(DISTINCT s.studentID), 2)
                        ELSE 0
                    END as dataCompletenessRate

                FROM cor4edu_programs p
                LEFT JOIN cor4edu_students s ON p.programID = s.programID AND s.status = 'Graduated'
                LEFT JOIN cor4edu_career_placements cp ON s.studentID = cp.studentID AND cp.isCurrentRecord = 'Y'";

        $conditions = [];
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "p.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['graduationDateStart'])) {
            $conditions[] = "s.actualGraduationDate >= ?";
            $params[] = $filters['graduationDateStart'];
        }

        if (!empty($filters['graduationDateEnd'])) {
            $conditions[] = "s.actualGraduationDate <= ?";
            $params[] = $filters['graduationDateEnd'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " GROUP BY p.programID, p.name
                  ORDER BY verificationRate DESC, p.name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get available employment statuses for filters
     * @return array Employment status options
     */
    public function getAvailableEmploymentStatuses(): array
    {
        return [
            ['value' => '', 'label' => 'No Status Set'],
            ['value' => 'employed_related', 'label' => 'Employed (Field-Related)'],
            ['value' => 'employed_unrelated', 'label' => 'Employed (Unrelated)'],
            ['value' => 'self_employed_related', 'label' => 'Self-Employed (Field-Related)'],
            ['value' => 'self_employed_unrelated', 'label' => 'Self-Employed (Unrelated)'],
            ['value' => 'not_employed_seeking', 'label' => 'Not Employed (Seeking)'],
            ['value' => 'not_employed_not_seeking', 'label' => 'Not Employed (Not Seeking)'],
            ['value' => 'continuing_education', 'label' => 'Continuing Education']
        ];
    }

    /**
     * Get detailed job placement verification report (for state auditors)
     * Includes all student contact info, employer contact info, and verification details
     * @param array $filters Optional filters
     * @return array Detailed placement records with all verification data
     */
    public function getJobPlacementVerificationReport(array $filters = []): array
    {
        $sql = "SELECT
                    s.studentID,
                    s.firstName as studentFirstName,
                    s.lastName as studentLastName,
                    s.studentCode,
                    s.phone as studentPhone,
                    s.email as studentEmail,
                    p.name as programName,
                    p.programCode,
                    s.enrollmentDate,
                    s.actualGraduationDate,
                    CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END as graduationDate,

                    cp.employmentStatus,
                    cp.employmentDate,
                    cp.jobTitle,
                    cp.employerName,
                    cp.employerAddress,
                    cp.employerContactName,
                    cp.employerContactPhone,
                    cp.employerContactEmail,
                    cp.employmentType,
                    cp.isEntryLevel,
                    cp.salaryRange,
                    cp.salaryExact,

                    cp.verificationDate,
                    cp.verificationSource,
                    verifier.firstName as verifiedByFirstName,
                    verifier.lastName as verifiedByLastName,
                    cp.verificationNotes,

                    cp.requiresLicense,
                    cp.licenseType,
                    cp.licenseObtained,
                    cp.licenseNumber,

                    cp.continuingEducationInstitution,
                    cp.continuingEducationProgram,
                    cp.comments

                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_career_placements cp ON s.studentID = cp.studentID AND cp.isCurrentRecord = 'Y'
                LEFT JOIN cor4edu_staff verifier ON cp.verifiedBy = verifier.staffID";

        $conditions = [];
        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $conditions[] = "p.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['studentStatus'])) {
            $conditions[] = "s.status IN (" . implode(',', array_fill(0, count($filters['studentStatus']), '?')) . ")";
            $params = array_merge($params, $filters['studentStatus']);
        }

        if (!empty($filters['employmentStatus'])) {
            // Handle empty string as NULL filter
            $hasNullFilter = in_array('', $filters['employmentStatus'], true);
            $nonNullStatuses = array_filter($filters['employmentStatus'], function($val) { return $val !== ''; });

            if ($hasNullFilter && !empty($nonNullStatuses)) {
                // Both NULL and specific statuses
                $conditions[] = "(cp.employmentStatus IS NULL OR cp.employmentStatus IN (" . implode(',', array_fill(0, count($nonNullStatuses), '?')) . "))";
                $params = array_merge($params, array_values($nonNullStatuses));
            } elseif ($hasNullFilter) {
                // Only NULL
                $conditions[] = "cp.employmentStatus IS NULL";
            } else {
                // Only specific statuses
                $conditions[] = "cp.employmentStatus IN (" . implode(',', array_fill(0, count($nonNullStatuses), '?')) . ")";
                $params = array_merge($params, array_values($nonNullStatuses));
            }
        }

        if (!empty($filters['verificationStatus'])) {
            if ($filters['verificationStatus'] === 'verified') {
                $conditions[] = "cp.verificationDate IS NOT NULL AND cp.verifiedBy IS NOT NULL";
            } elseif ($filters['verificationStatus'] === 'unverified') {
                $conditions[] = "(cp.verificationDate IS NULL OR cp.verifiedBy IS NULL)";
            }
        }

        if (!empty($filters['graduationDateStart'])) {
            $conditions[] = "(CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END) >= ?";
            $params[] = $filters['graduationDateStart'];
        }

        if (!empty($filters['graduationDateEnd'])) {
            $conditions[] = "(CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END) <= ?";
            $params[] = $filters['graduationDateEnd'];
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY p.name, s.lastName, s.firstName";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get unverified placements (action items for career services staff)
     * @param array $filters Optional filters
     * @return array Placements requiring verification
     */
    public function getUnverifiedPlacements(array $filters = []): array
    {
        $sql = "SELECT
                    s.studentID,
                    s.firstName as studentFirstName,
                    s.lastName as studentLastName,
                    s.studentCode,
                    s.phone as studentPhone,
                    s.email as studentEmail,
                    p.name as programName,
                    s.actualGraduationDate,
                    CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END as graduationDate,
                    cp.employmentStatus,
                    cp.employmentDate,
                    cp.jobTitle,
                    cp.employerName,
                    cp.createdOn as recordCreatedOn,
                    DATEDIFF(CURDATE(), cp.createdOn) as daysSinceCreated

                FROM cor4edu_students s
                JOIN cor4edu_programs p ON s.programID = p.programID
                LEFT JOIN cor4edu_career_placements cp ON s.studentID = cp.studentID AND cp.isCurrentRecord = 'Y'
                WHERE cp.employmentStatus IN ('employed_related', 'employed_unrelated', 'self_employed_related', 'self_employed_unrelated')
                AND (cp.verificationDate IS NULL OR cp.verifiedBy IS NULL)";

        $params = [];

        // Apply filters
        if (!empty($filters['programID'])) {
            $sql .= " AND p.programID IN (" . implode(',', array_fill(0, count($filters['programID']), '?')) . ")";
            $params = array_merge($params, $filters['programID']);
        }

        if (!empty($filters['studentStatus'])) {
            $sql .= " AND s.status IN (" . implode(',', array_fill(0, count($filters['studentStatus']), '?')) . ")";
            $params = array_merge($params, $filters['studentStatus']);
        }

        if (!empty($filters['graduationDateStart'])) {
            $sql .= " AND (CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END) >= ?";
            $params[] = $filters['graduationDateStart'];
        }

        if (!empty($filters['graduationDateEnd'])) {
            $sql .= " AND (CASE WHEN s.status = 'Active' THEN s.anticipatedGraduationDate ELSE s.actualGraduationDate END) <= ?";
            $params[] = $filters['graduationDateEnd'];
        }

        $sql .= " ORDER BY daysSinceCreated DESC, graduationDate DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get available programs for filter dropdowns
     * @return array Program list
     */
    public function getAvailablePrograms(): array
    {
        $sql = "SELECT programID, name, programCode
                FROM cor4edu_programs
                WHERE active = 'Y'
                ORDER BY name";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Get available student statuses for filter dropdowns
     * @return array Student status list
     */
    public function getAvailableStudentStatuses(): array
    {
        return [
            ['value' => 'Active', 'label' => 'Active (Upcoming Graduates)'],
            ['value' => 'Graduated', 'label' => 'Graduated'],
            ['value' => 'Alumni', 'label' => 'Alumni']
        ];
    }
}