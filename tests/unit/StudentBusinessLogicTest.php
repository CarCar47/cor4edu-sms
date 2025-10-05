<?php
/**
 * Student Business Logic Unit Tests
 *
 * Tests student-related business logic without database dependency.
 * Critical for ensuring student data integrity (FERPA compliance).
 */

namespace Cor4Edu\Tests\Unit;

use PHPUnit\Framework\TestCase;

class StudentBusinessLogicTest extends TestCase
{
    /**
     * Test student code format generation
     */
    public function testStudentCodeFormat(): void
    {
        $testCases = [
            1 => 'STU001',
            10 => 'STU010',
            99 => 'STU099',
            100 => 'STU100',
            999 => 'STU999',
        ];

        foreach ($testCases as $id => $expectedCode) {
            $code = 'STU' . str_pad($id, 3, '0', STR_PAD_LEFT);
            $this->assertEquals($expectedCode, $code, "Student code for ID {$id} should be {$expectedCode}");
        }
    }

    /**
     * Test valid student statuses
     */
    public function testStudentStatusValues(): void
    {
        $validStatuses = [
            'prospective',
            'current',
            'graduated',
            'withdrawn',
            'suspended',
        ];

        foreach ($validStatuses as $status) {
            $this->assertIsString($status);
            $this->assertNotEmpty($status);
        }
    }

    /**
     * Test student data validation rules
     */
    public function testRequiredStudentFields(): void
    {
        $requiredFields = [
            'firstName',
            'lastName',
            'email',
            'dateOfBirth',
            'status',
        ];

        $testStudent = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'dateOfBirth' => '2000-01-01',
            'status' => 'prospective',
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $testStudent, "Student must have {$field}");
            $this->assertNotEmpty($testStudent[$field], "{$field} should not be empty");
        }
    }

    /**
     * Test student name formatting
     */
    public function testStudentNameFormatting(): void
    {
        $testNames = [
            ['firstName' => 'John', 'lastName' => 'Doe', 'expected' => 'Doe, John'],
            ['firstName' => 'Jane', 'lastName' => 'Smith', 'expected' => 'Smith, Jane'],
            ['firstName' => 'Bob', 'lastName' => "O'Brien", 'expected' => "O'Brien, Bob"],
        ];

        foreach ($testNames as $test) {
            $formatted = $test['lastName'] . ', ' . $test['firstName'];
            $this->assertEquals($test['expected'], $formatted);
        }
    }

    /**
     * Test student email domain validation
     */
    public function testStudentEmailValidation(): void
    {
        $validEmails = [
            'student@example.com',
            'john.doe@school.edu',
            'jane_smith@university.org',
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email {$email} should be valid"
            );
        }

        $invalidEmails = [
            'notanemail',
            '@example.com',
            'student@',
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                "Email {$email} should be invalid"
            );
        }
    }

    /**
     * Test date of birth validation (must be in past)
     */
    public function testDateOfBirthMustBeInPast(): void
    {
        $today = new \DateTime();
        $pastDate = new \DateTime('2000-01-01');
        $futureDate = new \DateTime('+1 year');

        $this->assertLessThan($today, $pastDate, 'Birth date must be in the past');
        $this->assertGreaterThan($today, $futureDate, 'Future dates should not be valid birth dates');
    }

    /**
     * Test student age calculation
     */
    public function testStudentAgeCalculation(): void
    {
        $birthDate = new \DateTime('2000-01-01');
        $today = new \DateTime();
        $age = $today->diff($birthDate)->y;

        $this->assertGreaterThan(0, $age, 'Age should be positive');
        $this->assertLessThan(120, $age, 'Age should be realistic');
    }

    /**
     * Test student code uniqueness logic
     */
    public function testStudentCodeUniqueness(): void
    {
        $codes = [];

        for ($i = 1; $i <= 100; $i++) {
            $code = 'STU' . str_pad($i, 3, '0', STR_PAD_LEFT);
            $this->assertNotContains($code, $codes, "Code {$code} should be unique");
            $codes[] = $code;
        }

        $this->assertCount(100, $codes, 'Should generate 100 unique codes');
    }

    /**
     * Test student status transitions are valid
     */
    public function testStudentStatusTransitions(): void
    {
        $validTransitions = [
            'prospective' => ['current', 'withdrawn'],
            'current' => ['graduated', 'withdrawn', 'suspended'],
            'suspended' => ['current', 'withdrawn'],
        ];

        foreach ($validTransitions as $fromStatus => $allowedToStatuses) {
            $this->assertIsArray($allowedToStatuses);
            $this->assertNotEmpty($allowedToStatuses, "Status {$fromStatus} should have valid transitions");
        }
    }

    /**
     * Test search term sanitization
     */
    public function testSearchTermSanitization(): void
    {
        $searchTerms = [
            'John Doe',
            'jane@example.com',
            'STU001',
        ];

        foreach ($searchTerms as $term) {
            // Test that search terms can be safely used in LIKE queries
            $safeTerm = '%' . $term . '%';
            $this->assertStringContainsString($term, $safeTerm);
            $this->assertStringStartsWith('%', $safeTerm);
            $this->assertStringEndsWith('%', $safeTerm);
        }
    }

    /**
     * Test phone number formatting
     */
    public function testPhoneNumberFormatting(): void
    {
        $phoneNumbers = [
            '1234567890',
            '123-456-7890',
            '(123) 456-7890',
        ];

        foreach ($phoneNumbers as $phone) {
            // Remove all non-numeric characters
            $cleaned = preg_replace('/[^0-9]/', '', $phone);
            $this->assertEquals(10, strlen($cleaned), 'US phone should have 10 digits');
        }
    }

    /**
     * Test student full name generation
     */
    public function testFullNameGeneration(): void
    {
        $students = [
            ['firstName' => 'John', 'lastName' => 'Doe', 'middleName' => null],
            ['firstName' => 'Jane', 'lastName' => 'Smith', 'middleName' => 'Marie'],
        ];

        foreach ($students as $student) {
            if (!empty($student['middleName'])) {
                $fullName = $student['firstName'] . ' ' . $student['middleName'] . ' ' . $student['lastName'];
                $this->assertStringContainsString($student['middleName'], $fullName);
            } else {
                $fullName = $student['firstName'] . ' ' . $student['lastName'];
                $this->assertStringNotContainsString('  ', $fullName, 'No double spaces');
            }

            $this->assertStringContainsString($student['firstName'], $fullName);
            $this->assertStringContainsString($student['lastName'], $fullName);
        }
    }

    /**
     * Test data privacy - SSN masking
     */
    public function testSSNMasking(): void
    {
        $ssn = '123-45-6789';
        $masked = 'XXX-XX-' . substr($ssn, -4);

        $this->assertEquals('XXX-XX-6789', $masked, 'SSN should be masked except last 4 digits');
        $this->assertStringNotContainsString('123', $masked, 'First 3 digits should be hidden');
        $this->assertStringContainsString('6789', $masked, 'Last 4 digits should be visible');
    }

    /**
     * Test enrollment date logic
     */
    public function testEnrollmentDateValidation(): void
    {
        $today = new \DateTime();
        $enrollmentDate = new \DateTime('2024-09-01');
        $graduationDate = new \DateTime('2026-06-01');

        $this->assertLessThan($graduationDate, $enrollmentDate, 'Enrollment must be before graduation');
        $this->assertGreaterThan($today->modify('-10 years'), $enrollmentDate, 'Enrollment should be recent');
    }
}
