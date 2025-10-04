<?php
/**
 * Error Handler Test
 *
 * Tests centralized error handling following Gibbon patterns
 */

namespace Cor4Edu\Tests;

use PHPUnit\Framework\TestCase;
use Cor4Edu\Services\ErrorHandler;

class ErrorHandlerTest extends TestCase
{
    /**
     * Test ErrorHandler instantiation
     */
    public function testErrorHandlerInstantiation(): void
    {
        $handler = new ErrorHandler('development');

        $this->assertInstanceOf(ErrorHandler::class, $handler);
        $this->assertEquals('development', $handler->getEnvironment());
    }

    /**
     * Test production environment
     */
    public function testProductionEnvironment(): void
    {
        $handler = new ErrorHandler('production');

        $this->assertEquals('production', $handler->getEnvironment());
    }

    /**
     * Test error handler registration
     */
    public function testErrorHandlerRegistration(): void
    {
        // Store original error handler
        $originalHandler = set_error_handler(function() {});
        restore_error_handler();

        // Create new error handler
        $handler = new ErrorHandler('development');

        // Verify error handler was registered
        $currentHandler = set_error_handler(function() {});
        restore_error_handler();

        $this->assertIsArray($currentHandler);
        $this->assertInstanceOf(ErrorHandler::class, $currentHandler[0]);
        $this->assertEquals('handleError', $currentHandler[1]);
    }

    /**
     * Test exception handler registration
     */
    public function testExceptionHandlerRegistration(): void
    {
        $handler = new ErrorHandler('development');

        $currentHandler = set_exception_handler(function() {});
        restore_exception_handler();

        $this->assertIsArray($currentHandler);
        $this->assertInstanceOf(ErrorHandler::class, $currentHandler[0]);
        $this->assertEquals('handleException', $currentHandler[1]);
    }

    /**
     * Test error handling method exists
     */
    public function testErrorHandlingMethodExists(): void
    {
        $handler = new ErrorHandler('development');

        $this->assertTrue(
            method_exists($handler, 'handleError'),
            'ErrorHandler should have handleError method'
        );

        $this->assertTrue(
            method_exists($handler, 'handleException'),
            'ErrorHandler should have handleException method'
        );

        $this->assertTrue(
            method_exists($handler, 'handleFatalErrorShutdown'),
            'ErrorHandler should have handleFatalErrorShutdown method'
        );
    }

    /**
     * Test template renderer can be set
     */
    public function testTemplateRendererSetting(): void
    {
        $handler = new ErrorHandler('production');

        $mockRenderer = new \stdClass();
        $result = $handler->setTemplateRenderer($mockRenderer);

        $this->assertSame($handler, $result, 'setTemplateRenderer should return self for chaining');
    }

    /**
     * Test error templates exist
     */
    public function testErrorTemplatesExist(): void
    {
        $templatePath = __DIR__ . '/../resources/templates/errors';

        $this->assertFileExists(
            $templatePath . '/500.twig.html',
            '500 error template should exist'
        );

        $this->assertFileExists(
            $templatePath . '/404.twig.html',
            '404 error template should exist'
        );

        $this->assertFileExists(
            $templatePath . '/403.twig.html',
            '403 error template should exist'
        );
    }

    /**
     * Test error templates are valid HTML
     */
    public function testErrorTemplatesAreValidHtml(): void
    {
        $templatePath = __DIR__ . '/../resources/templates/errors';

        $templates = ['500.twig.html', '404.twig.html', '403.twig.html'];

        foreach ($templates as $template) {
            $content = file_get_contents($templatePath . '/' . $template);

            $this->assertStringContainsString('<!DOCTYPE html>', $content);
            $this->assertStringContainsString('<html', $content);
            $this->assertStringContainsString('</html>', $content);
            $this->assertStringContainsString('COR4EDU SMS', $content);
        }
    }

    /**
     * Test 500 template contains error information
     */
    public function test500TemplateContainsErrorInformation(): void
    {
        $templatePath = __DIR__ . '/../resources/templates/errors';
        $content = file_get_contents($templatePath . '/500.twig.html');

        $this->assertStringContainsString('System Error', $content);
        $this->assertStringContainsString('500', $content);
        $this->assertStringContainsString('Internal Server Error', $content);
    }

    /**
     * Test 404 template contains not found information
     */
    public function test404TemplateContainsNotFoundInformation(): void
    {
        $templatePath = __DIR__ . '/../resources/templates/errors';
        $content = file_get_contents($templatePath . '/404.twig.html');

        $this->assertStringContainsString('404', $content);
        $this->assertStringContainsString('Not Found', $content);
    }

    /**
     * Test 403 template contains access denied information
     */
    public function test403TemplateContainsAccessDeniedInformation(): void
    {
        $templatePath = __DIR__ . '/../resources/templates/errors';
        $content = file_get_contents($templatePath . '/403.twig.html');

        $this->assertStringContainsString('403', $content);
        $this->assertStringContainsString('Access Denied', $content);
        $this->assertStringContainsString('permission', $content);
    }
}
