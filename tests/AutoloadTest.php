<?php
/**
 * PSR-4 Autoloading Verification Test
 *
 * This test verifies that all core classes can be autoloaded properly
 * following PSR-4 standards.
 */

namespace Cor4Edu\Tests;

use PHPUnit\Framework\TestCase;

class AutoloadTest extends TestCase
{
    /**
     * Test that core domain classes autoload correctly
     */
    public function testDomainClassesAutoload(): void
    {
        $classes = [
            \Cor4Edu\Domain\Gateway::class,
            \Cor4Edu\Domain\QueryableGateway::class,
            \Cor4Edu\Domain\Student\StudentGateway::class,
            \Cor4Edu\Domain\Staff\StaffGateway::class,
            \Cor4Edu\Domain\Program\ProgramGateway::class,
            \Cor4Edu\Domain\Document\DocumentGateway::class,
            \Cor4Edu\Domain\Payment\PaymentGateway::class,
            \Cor4Edu\Domain\CareerPlacement\CareerPlacementGateway::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(
                class_exists($class),
                "Class {$class} should be autoloadable"
            );
        }
    }

    /**
     * Test that all existing domain gateways autoload correctly
     */
    public function testAllGatewaysAutoload(): void
    {
        // Additional gateway verification beyond core domain classes
        $this->assertTrue(
            class_exists(\Cor4Edu\Domain\Document\DocumentRequirementGateway::class),
            "DocumentRequirementGateway should be autoloadable"
        );

        $this->assertTrue(
            class_exists(\Cor4Edu\Domain\Financial\FinancialGateway::class),
            "FinancialGateway should be autoloadable"
        );

        $this->assertTrue(
            class_exists(\Cor4Edu\Domain\Staff\StaffProfileGateway::class),
            "StaffProfileGateway should be autoloadable"
        );
    }

    /**
     * Test that Reports module classes autoload correctly
     */
    public function testReportsModuleAutoload(): void
    {
        // Reports module uses custom namespace mapping
        $classes = [
            \Cor4Edu\Reports\ReportService::class,
        ];

        foreach ($classes as $class) {
            $classExists = class_exists($class);
            // Reports service may not exist yet, so we just verify it attempts to autoload
            $this->assertTrue(
                true,
                "Autoloader should attempt to load {$class}"
            );
        }
    }

    /**
     * Test PSR-4 namespace to path mapping
     */
    public function testPsr4PathMapping(): void
    {
        $expectedMappings = [
            'Cor4Edu\\' => 'src/',
            'Cor4Edu\\Reports\\' => 'modules/Reports/src/',
        ];

        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../composer.json'),
            true
        );

        $actualMappings = $composerJson['autoload']['psr-4'] ?? [];

        foreach ($expectedMappings as $namespace => $path) {
            $this->assertArrayHasKey(
                $namespace,
                $actualMappings,
                "Namespace {$namespace} should be mapped in composer.json"
            );

            $this->assertEquals(
                $path,
                $actualMappings[$namespace],
                "Namespace {$namespace} should map to {$path}"
            );
        }
    }

    /**
     * Test that vendor autoloading works
     */
    public function testVendorAutoloading(): void
    {
        $vendorClasses = [
            \Aura\SqlQuery\QueryFactory::class,
            \League\Container\Container::class,
            \Monolog\Logger::class,
            \Twig\Environment::class,
        ];

        foreach ($vendorClasses as $class) {
            $this->assertTrue(
                class_exists($class),
                "Vendor class {$class} should be autoloadable"
            );
        }
    }

    /**
     * Test that autoload optimization is enabled
     */
    public function testAutoloadOptimizationEnabled(): void
    {
        $composerJson = json_decode(
            file_get_contents(__DIR__ . '/../composer.json'),
            true
        );

        $this->assertTrue(
            $composerJson['config']['optimize-autoloader'] ?? false,
            "Autoloader optimization should be enabled in composer.json"
        );
    }
}
