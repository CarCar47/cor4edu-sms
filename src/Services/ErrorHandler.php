<?php
/**
 * Error Handler Service
 *
 * Centralized error handling following Gibbon patterns
 * Provides graceful error pages and comprehensive error logging
 *
 * Based on Gibbon's ErrorHandler.php
 *
 * Features:
 * - Custom error handler (E_ERROR, E_WARNING, etc.)
 * - Exception handler (uncaught exceptions)
 * - Fatal error shutdown handler
 * - Environment-aware display (dev vs production)
 * - Template-based error pages
 * - Stack trace capture
 *
 * @version v1.0.0
 * @since v1.0.0
 */

namespace Cor4Edu\Services;

class ErrorHandler
{
    /**
     * Environment type
     */
    protected string $environment;

    /**
     * Template renderer (Twig)
     */
    protected $templateRenderer;

    /**
     * Base path for templates
     */
    protected string $templatePath;

    /**
     * Constructor
     *
     * @param string $environment Environment type ('development', 'production')
     * @param mixed $templateRenderer Optional Twig renderer
     */
    public function __construct(string $environment = 'production', $templateRenderer = null)
    {
        $this->environment = $environment;
        $this->templateRenderer = $templateRenderer;
        $this->templatePath = __DIR__ . '/../../resources/templates';

        // Register error handlers
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleFatalErrorShutdown']);
    }

    /**
     * Handle PHP errors (E_WARNING, E_NOTICE, etc.)
     *
     * Based on Gibbon's ErrorHandler::handleError()
     *
     * @param int $code Error code
     * @param string $message Error message
     * @param string|null $file File where error occurred
     * @param int|null $line Line number
     * @return bool
     */
    public function handleError($code, $message = '', $file = null, $line = null): bool
    {
        // Determine error type
        switch ($code) {
            case ($code & (E_PARSE | E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR)):
                $type = 'Error';
                break;
            case ($code & (E_WARNING | E_USER_WARNING | E_COMPILE_WARNING | E_RECOVERABLE_ERROR)):
                $type = 'Warning';
                break;
            case ($code & (E_DEPRECATED | E_USER_DEPRECATED)):
                $type = 'Deprecated';
                break;
            case ($code & (E_NOTICE | E_USER_NOTICE)):
                $type = 'Notice';
                break;
            default:
                $type = 'Unknown Error';
                break;
        }

        // Get stack trace (slice out this error handler)
        $stackTrace = array_slice(debug_backtrace(), 2, -3);

        return $this->outputError($code, $type, $message, $stackTrace, $file, $line);
    }

    /**
     * Handle uncaught exceptions
     *
     * Based on Gibbon's ErrorHandler::handleException()
     *
     * @param \Throwable $e Exception
     * @return void
     */
    public function handleException(\Throwable $e): void
    {
        $this->outputError(
            E_ERROR,
            'Uncaught Exception',
            get_class($e) . ' - ' . $e->getMessage(),
            $e->getTrace(),
            $e->getFile(),
            $e->getLine()
        );

        $this->handleGracefulShutdown();
    }

    /**
     * Handle fatal errors during shutdown
     *
     * Based on Gibbon's ErrorHandler::handleFatalErrorShutdown()
     *
     * @return void
     */
    public function handleFatalErrorShutdown(): void
    {
        $lastError = error_get_last();

        if ($lastError && $lastError['type'] === E_ERROR) {
            $this->outputError(
                $lastError['type'],
                'Fatal Error',
                nl2br($lastError['message']),
                [],
                $lastError['file'] ?? null,
                $lastError['line'] ?? null
            );

            $this->handleGracefulShutdown();
        }
    }

    /**
     * Output error to user (environment-aware)
     *
     * @param int $code Error code
     * @param string $type Error type
     * @param string $message Error message
     * @param array $stackTrace Stack trace
     * @param string|null $file File where error occurred
     * @param int|null $line Line number
     * @return bool
     */
    protected function outputError(
        int $code,
        string $type,
        string $message,
        array $stackTrace,
        ?string $file = null,
        ?int $line = null
    ): bool {
        // Always log the error
        $this->logError($code, $type, $message, $file, $line, $stackTrace);

        // In production, only show detailed errors if display_errors is enabled
        if ($this->environment === 'production' && !ini_get('display_errors')) {
            return false; // Don't display, just log
        }

        // Development mode: show detailed error information
        if ($this->environment === 'development') {
            $this->displayDetailedError($code, $type, $message, $stackTrace, $file, $line);
        }

        return true;
    }

    /**
     * Display detailed error (development mode)
     *
     * @param int $code Error code
     * @param string $type Error type
     * @param string $message Error message
     * @param array $stackTrace Stack trace
     * @param string|null $file File
     * @param int|null $line Line
     * @return void
     */
    protected function displayDetailedError(
        int $code,
        string $type,
        string $message,
        array $stackTrace,
        ?string $file,
        ?int $line
    ): void {
        echo "\n<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 10px; border-radius: 5px;'>";
        echo "<h3 style='color: #721c24; margin-top: 0;'>⚠️ {$type}</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($message) . "</p>";

        if ($file) {
            echo "<p><strong>File:</strong> " . htmlspecialchars($file) . "</p>";
        }

        if ($line) {
            echo "<p><strong>Line:</strong> {$line}</p>";
        }

        if (!empty($stackTrace)) {
            echo "<details style='margin-top: 10px;'>";
            echo "<summary style='cursor: pointer;'><strong>Stack Trace</strong></summary>";
            echo "<pre style='background: #fff; padding: 10px; overflow: auto; max-height: 400px;'>";
            echo htmlspecialchars(print_r($stackTrace, true));
            echo "</pre>";
            echo "</details>";
        }

        echo "</div>\n";
    }

    /**
     * Log error to error log
     *
     * @param int $code Error code
     * @param string $type Error type
     * @param string $message Error message
     * @param string|null $file File
     * @param int|null $line Line
     * @param array $stackTrace Stack trace
     * @return void
     */
    protected function logError(
        int $code,
        string $type,
        string $message,
        ?string $file,
        ?int $line,
        array $stackTrace
    ): void {
        $logMessage = sprintf(
            "[%s] %s: %s in %s on line %d",
            date('Y-m-d H:i:s'),
            $type,
            $message,
            $file ?? 'unknown',
            $line ?? 0
        );

        // Add stack trace for serious errors
        if ($code & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR)) {
            $logMessage .= "\nStack trace:\n" . $this->formatStackTrace($stackTrace);
        }

        error_log($logMessage);
    }

    /**
     * Format stack trace for logging
     *
     * @param array $stackTrace Stack trace
     * @return string Formatted stack trace
     */
    protected function formatStackTrace(array $stackTrace): string
    {
        $formatted = [];

        foreach ($stackTrace as $index => $trace) {
            $file = $trace['file'] ?? 'unknown';
            $line = $trace['line'] ?? 0;
            $function = $trace['function'] ?? '';
            $class = $trace['class'] ?? '';
            $type = $trace['type'] ?? '';

            $formatted[] = sprintf(
                "#%d %s(%d): %s%s%s()",
                $index,
                $file,
                $line,
                $class,
                $type,
                $function
            );
        }

        return implode("\n", $formatted);
    }

    /**
     * Handle graceful shutdown (display user-friendly error page)
     *
     * Based on Gibbon's ErrorHandler::handleGracefulShutdown()
     *
     * @return void
     */
    protected function handleGracefulShutdown(): void
    {
        @ob_end_clean();

        // In production, show generic error page
        if ($this->environment === 'production') {
            $this->displayProductionErrorPage();
        }

        exit;
    }

    /**
     * Display production error page (user-friendly)
     *
     * @return void
     */
    protected function displayProductionErrorPage(): void
    {
        http_response_code(500);

        // Try to use Twig template if available
        $errorTemplatePath = $this->templatePath . '/errors/500.twig.html';

        if ($this->templateRenderer && file_exists($errorTemplatePath)) {
            try {
                echo $this->templateRenderer->render('errors/500.twig.html', [
                    'pageTitle' => 'System Error'
                ]);
                return;
            } catch (\Exception $e) {
                // Fall through to HTML fallback
            }
        }

        // Fallback: Simple HTML error page
        echo $this->getDefaultErrorPageHtml();
    }

    /**
     * Get default error page HTML (fallback)
     *
     * @return string HTML
     */
    protected function getDefaultErrorPageHtml(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Error - COR4EDU SMS</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .error-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 600px;
            text-align: center;
        }
        h1 {
            color: #dc3545;
            font-size: 48px;
            margin: 0 0 20px 0;
        }
        h2 {
            color: #343a40;
            font-size: 24px;
            margin: 0 0 20px 0;
        }
        p {
            color: #6c757d;
            font-size: 16px;
            line-height: 1.6;
        }
        .error-code {
            background: #f8f9fa;
            border-radius: 5px;
            padding: 20px;
            margin: 20px 0;
            font-family: monospace;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            margin-top: 20px;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1>⚠️</h1>
        <h2>System Error</h2>
        <p>We're sorry, but something went wrong while processing your request.</p>
        <div class="error-code">
            <strong>Error Code:</strong> 500 Internal Server Error
        </div>
        <p>The error has been logged and our team has been notified. Please try again later.</p>
        <a href="/" class="btn">Return to Dashboard</a>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Get environment type
     *
     * @return string Environment
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Set template renderer
     *
     * @param mixed $renderer Twig renderer
     * @return self
     */
    public function setTemplateRenderer($renderer): self
    {
        $this->templateRenderer = $renderer;
        return $this;
    }
}
