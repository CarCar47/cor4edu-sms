<?php

namespace Cor4Edu\Domain\Program;

use Cor4Edu\Domain\QueryableGateway;

/**
 * ProgramGateway following Gibbon patterns
 * Handles all program data operations
 */
class ProgramGateway extends QueryableGateway
{
    protected static $tableName = 'cor4edu_programs';
    protected static $primaryKey = 'programID';
    protected static $searchableColumns = ['name', 'programCode', 'description'];

    /**
     * Get all active programs
     * @return array
     */
    public function selectActivePrograms(): array
    {
        $sql = "SELECT * FROM cor4edu_programs
                WHERE active = 'Y'
                ORDER BY name";
        return $this->select($sql);
    }

    /**
     * Get program with pricing information
     * @param int $programID
     * @return array|false
     */
    public function getProgramWithPricing(int $programID)
    {
        $sql = "SELECT p.*,
                       pricing.studentType,
                       pricing.price,
                       pricing.currency,
                       pricing.effectiveDate
                FROM cor4edu_programs p
                LEFT JOIN cor4edu_program_pricing pricing ON p.programID = pricing.programID
                WHERE p.programID = :programID
                ORDER BY pricing.effectiveDate DESC";

        return $this->select($sql, ['programID' => $programID]);
    }

    /**
     * Get active programs for dropdown
     * @return array
     */
    public function getActiveProgramsForDropdown(): array
    {
        $sql = "SELECT programID, name
                FROM cor4edu_programs
                WHERE active = 'Y'
                ORDER BY name";
        return $this->select($sql);
    }

    /**
     * Get active program count for dashboard
     * @return int
     */
    public function getActiveProgramCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM cor4edu_programs WHERE active = 'Y'";
        $result = $this->selectOne($sql);
        return (int) $result['count'];
    }

    /**
     * Get all programs (for management interface)
     * @return array
     */
    public function selectAllPrograms(): array
    {
        $sql = "SELECT p.*,
                       creator.firstName as createdByFirstName,
                       creator.lastName as createdByLastName
                FROM cor4edu_programs p
                LEFT JOIN cor4edu_staff creator ON p.createdBy = creator.staffID
                WHERE p.active = 'Y'
                ORDER BY p.name";
        return $this->select($sql);
    }

    /**
     * Get program details by ID
     * @param int $programID
     * @return array|false
     */
    public function getProgramDetails(int $programID)
    {
        $sql = "SELECT p.*,
                       creator.firstName as createdByFirstName,
                       creator.lastName as createdByLastName
                FROM cor4edu_programs p
                LEFT JOIN cor4edu_staff creator ON p.createdBy = creator.staffID
                WHERE p.programID = :programID";

        return $this->selectOne($sql, ['programID' => $programID]);
    }

    /**
     * Get next program code
     * @return string
     */
    public function getNextProgramCode(): string
    {
        $sql = "SELECT COUNT(*) + 1 as next_id FROM cor4edu_programs";
        $result = $this->selectOne($sql);
        $nextId = $result['next_id'];

        return 'PRG' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Create new program with generated code
     * @param array $data
     * @return int
     */
    public function createProgram(array $data): int
    {
        // Generate program code if not provided
        if (!isset($data['programCode'])) {
            $data['programCode'] = $this->getNextProgramCode();
        }

        // Set default values
        $data['active'] = $data['active'] ?? 'Y';
        $data['createdOn'] = date('Y-m-d H:i:s');

        return $this->insertRecord($data);
    }

    /**
     * Search programs
     * @param string $search
     * @param string $active
     * @return array
     */
    public function searchPrograms(string $search = '', string $active = ''): array
    {
        $sql = "SELECT p.*,
                       creator.firstName as createdByFirstName,
                       creator.lastName as createdByLastName
                FROM cor4edu_programs p
                LEFT JOIN cor4edu_staff creator ON p.createdBy = creator.staffID
                WHERE 1=1";

        $params = [];

        if (!empty($search)) {
            $sql .= " AND (p.name LIKE :search OR p.programCode LIKE :search OR p.description LIKE :search)";
            $params['search'] = "%{$search}%";
        }

        if (!empty($active)) {
            $sql .= " AND p.active = :active";
            $params['active'] = $active;
        }

        $sql .= " ORDER BY p.name";

        return $this->select($sql, $params);
    }

    /**
     * Check if program code exists for another program
     * @param string $programCode
     * @param int $excludeProgramID
     * @return bool
     */
    public function programCodeExistsForOtherProgram(string $programCode, int $excludeProgramID): bool
    {
        $sql = "SELECT programID FROM cor4edu_programs WHERE programCode = ? AND programID != ?";
        $result = $this->selectOne($sql, [$programCode, $excludeProgramID]);
        return $result !== false;
    }

    /**
     * Get total program count for dashboard
     * @return int
     */
    public function getTotalProgramCount(): int
    {
        $sql = "SELECT COUNT(*) as count FROM cor4edu_programs";
        $result = $this->selectOne($sql);
        return (int) $result['count'];
    }

    /**
     * Get status counts for programs (Active/Inactive)
     * @return array
     */
    public function getStatusCounts(): array
    {
        $sql = "SELECT
                    active,
                    COUNT(*) as count
                FROM cor4edu_programs
                GROUP BY active";

        $results = $this->select($sql);

        $counts = [];
        foreach ($results as $result) {
            $status = $result['active'] === 'Y' ? 'Active' : 'Inactive';
            $counts[$status] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * Get available measurement types
     * @return array
     */
    public function getMeasurementTypes(): array
    {
        return [
            'credits' => 'Credit Hours',
            'hours' => 'Contact Hours',
            'both' => 'Credits and Hours',
            'none' => 'No Measurement'
        ];
    }

    /**
     * Format measurement display for a program
     * @param array $program
     * @return string
     */
    public function formatMeasurementDisplay(array $program): string
    {
        $type = $program['measurementType'] ?? 'credits';
        $credits = $program['creditHours'] ?? null;
        $hours = $program['contactHours'] ?? null;

        switch ($type) {
            case 'credits':
                return $credits ? $credits . ' credits' : '—';
            case 'hours':
                return $hours ? $hours . ' hours' : '—';
            case 'both':
                $parts = [];
                if ($credits) $parts[] = $credits . ' credits';
                if ($hours) $parts[] = $hours . ' hours';
                return $parts ? implode(', ', $parts) : '—';
            case 'none':
            default:
                return '—';
        }
    }

    // =====================================================
    // FINANCIAL SYSTEM ENHANCEMENTS
    // =====================================================

    /**
     * Get program with current pricing breakdown
     * @param int $programID
     * @return array|false
     */
    public function selectProgramPricing(int $programID)
    {
        $sql = "SELECT p.*,
                       creator.firstName as createdByFirstName,
                       creator.lastName as createdByLastName
                FROM cor4edu_programs p
                LEFT JOIN cor4edu_staff creator ON p.createdBy = creator.staffID
                WHERE p.programID = :programID";

        return $this->selectOne($sql, ['programID' => $programID]);
    }

    /**
     * Update program pricing and create price history version
     * @param int $programID
     * @param array $pricingData
     * @param int $createdBy
     * @return string The new priceId
     */
    public function updateProgramPricing(int $programID, array $pricingData, int $createdBy): string
    {
        // Generate new price ID
        $priceId = 'PRICE_' . $programID . '_' . time();

        // Create price history record
        $priceHistoryData = [
            'priceId' => $priceId,
            'programID' => $programID,
            'tuitionAmount' => $pricingData['tuitionAmount'] ?? 0.00,
            'fees' => $pricingData['fees'] ?? 0.00,
            'booksAmount' => $pricingData['booksAmount'] ?? 0.00,
            'materialsAmount' => $pricingData['materialsAmount'] ?? 0.00,
            'applicationFee' => $pricingData['applicationFee'] ?? 0.00,
            'miscellaneousCosts' => $pricingData['miscellaneousCosts'] ?? 0.00,
            'effectiveDate' => date('Y-m-d'),
            'createdBy' => $createdBy,
            'isActive' => true,
            'description' => $pricingData['description'] ?? 'Program pricing update'
        ];

        // Deactivate previous pricing versions
        $deactivateSql = "UPDATE cor4edu_program_price_history SET isActive = FALSE WHERE programID = :programID";
        $this->pdo->prepare($deactivateSql)->execute(['programID' => $programID]);

        // Insert new price history
        $this->insert('cor4edu_program_price_history', $priceHistoryData);

        // Update program with new pricing and link to price history
        $programUpdateData = [
            'tuitionAmount' => $pricingData['tuitionAmount'] ?? 0.00,
            'fees' => $pricingData['fees'] ?? 0.00,
            'booksAmount' => $pricingData['booksAmount'] ?? 0.00,
            'materialsAmount' => $pricingData['materialsAmount'] ?? 0.00,
            'applicationFee' => $pricingData['applicationFee'] ?? 0.00,
            'miscellaneousCosts' => $pricingData['miscellaneousCosts'] ?? 0.00,
            'currentPriceId' => $priceId,
            'modifiedBy' => $createdBy,
            'modifiedOn' => date('Y-m-d H:i:s')
        ];

        $this->updateByID($programID, $programUpdateData);

        return $priceId;
    }

    /**
     * Get price history for a program
     * @param int $programID
     * @return array
     */
    public function getPriceHistoryByProgram(int $programID): array
    {
        $sql = "SELECT ph.*,
                       s.firstName as createdByFirstName,
                       s.lastName as createdByLastName
                FROM cor4edu_program_price_history ph
                LEFT JOIN cor4edu_staff s ON ph.createdBy = s.staffID
                WHERE ph.programID = :programID
                ORDER BY ph.effectiveDate DESC, ph.createdAt DESC";

        return $this->select($sql, ['programID' => $programID]);
    }

    /**
     * Get active pricing by program
     * @param int $programID
     * @return array|false
     */
    public function getActivePriceByProgram(int $programID)
    {
        $sql = "SELECT ph.*
                FROM cor4edu_program_price_history ph
                WHERE ph.programID = :programID AND ph.isActive = TRUE
                ORDER BY ph.effectiveDate DESC
                LIMIT 1";

        return $this->selectOne($sql, ['programID' => $programID]);
    }

    /**
     * Get pricing by price ID (for student enrollment protection)
     * @param string $priceId
     * @return array|false
     */
    public function getPriceById(string $priceId)
    {
        $sql = "SELECT ph.*
                FROM cor4edu_program_price_history ph
                WHERE ph.priceId = :priceId";

        return $this->selectOne($sql, ['priceId' => $priceId]);
    }

    /**
     * Get programs with pricing for financial reporting
     * @return array
     */
    public function getProgramsWithPricing(): array
    {
        $sql = "SELECT p.*,
                       creator.firstName as createdByFirstName,
                       creator.lastName as createdByLastName
                FROM cor4edu_programs p
                LEFT JOIN cor4edu_staff creator ON p.createdBy = creator.staffID
                WHERE p.active = 'Y'
                ORDER BY p.name";

        return $this->select($sql);
    }

    /**
     * Lock student to current program pricing (enrollment protection)
     * @param int $studentID
     * @param int $programID
     * @return bool Success
     */
    public function lockStudentPricing(int $studentID, int $programID): bool
    {
        // Get current active pricing for the program
        $activePrice = $this->getActivePriceByProgram($programID);

        if (!$activePrice) {
            return false;
        }

        // Update student with enrollment price lock
        $sql = "UPDATE cor4edu_students
                SET enrollmentPriceId = :enrollmentPriceId,
                    contractLockedAt = :contractLockedAt
                WHERE studentID = :studentID";

        $params = [
            'enrollmentPriceId' => $activePrice['priceId'],
            'contractLockedAt' => date('Y-m-d H:i:s'),
            'studentID' => $studentID
        ];

        return $this->pdo->prepare($sql)->execute($params);
    }

    /**
     * Format pricing breakdown for display
     * @param array $program
     * @return array
     */
    public function formatPricingBreakdown(array $program): array
    {
        return [
            'tuition' => [
                'label' => 'Tuition',
                'amount' => (float) ($program['tuitionAmount'] ?? 0),
                'formatted' => '$' . number_format((float) ($program['tuitionAmount'] ?? 0), 2)
            ],
            'fees' => [
                'label' => 'Fees',
                'amount' => (float) ($program['fees'] ?? 0),
                'formatted' => '$' . number_format((float) ($program['fees'] ?? 0), 2)
            ],
            'books' => [
                'label' => 'Books',
                'amount' => (float) ($program['booksAmount'] ?? 0),
                'formatted' => '$' . number_format((float) ($program['booksAmount'] ?? 0), 2)
            ],
            'materials' => [
                'label' => 'Materials',
                'amount' => (float) ($program['materialsAmount'] ?? 0),
                'formatted' => '$' . number_format((float) ($program['materialsAmount'] ?? 0), 2)
            ],
            'application' => [
                'label' => 'Application Fee',
                'amount' => (float) ($program['applicationFee'] ?? 0),
                'formatted' => '$' . number_format((float) ($program['applicationFee'] ?? 0), 2)
            ],
            'miscellaneous' => [
                'label' => 'Miscellaneous',
                'amount' => (float) ($program['miscellaneousCosts'] ?? 0),
                'formatted' => '$' . number_format((float) ($program['miscellaneousCosts'] ?? 0), 2)
            ],
            'total' => [
                'label' => 'Total Cost',
                'amount' => (float) ($program['totalCost'] ?? 0),
                'formatted' => '$' . number_format((float) ($program['totalCost'] ?? 0), 2)
            ]
        ];
    }

    /**
     * Validate pricing data
     * @param array $data
     * @return array Array of validation errors
     */
    public function validatePricingData(array $data): array
    {
        $errors = [];

        // All pricing fields must be non-negative
        $pricingFields = ['tuitionAmount', 'fees', 'booksAmount', 'materialsAmount', 'applicationFee', 'miscellaneousCosts'];

        foreach ($pricingFields as $field) {
            if (isset($data[$field])) {
                $value = $data[$field];
                if (!is_numeric($value) || (float)$value < 0) {
                    $label = ucfirst(str_replace('Amount', '', $field));
                    $errors[] = "{$label} must be a non-negative number";
                }
            }
        }

        return $errors;
    }

    /**
     * Get programs with student counts for financial reporting
     * @return array
     */
    public function getProgramsWithStudentCounts(): array
    {
        $sql = "SELECT p.*,
                       COUNT(s.studentID) as studentCount,
                       COUNT(CASE WHEN s.status = 'active' THEN 1 END) as activeStudentCount
                FROM cor4edu_programs p
                LEFT JOIN cor4edu_students s ON p.programID = s.programID
                WHERE p.active = 'Y'
                GROUP BY p.programID
                ORDER BY p.name";

        return $this->select($sql);
    }
}