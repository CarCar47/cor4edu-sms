<?php

/**
 * Structured Logging Service
 *
 * Provides centralized logging with context and structure for Cloud Logging.
 * Implements best practices for monitoring and debugging.
 *
 * @version 1.0.0
 * @since 2025-10-04
 */

namespace Cor4Edu\Infrastructure;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Level;

class Logger
{
    private MonologLogger $logger;
    private static ?Logger $instance = null;

    /**
     * Private constructor (Singleton pattern)
     */
    private function __construct()
    {
        $this->logger = new MonologLogger('cor4edu-sms');

        // Cloud Run / Production: Log to stderr (captured by Cloud Logging)
        if ($this->isCloudEnvironment()) {
            $handler = new StreamHandler('php://stderr', Level::Info);
            $handler->setFormatter(new JsonFormatter());
            $this->logger->pushHandler($handler);
        } else {
            // Local development: Log to error_log and file
            $handler = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Level::Debug);
            $this->logger->pushHandler($handler);

            // Also log to file in local development
            if (is_writable(__DIR__ . '/../../logs')) {
                $fileHandler = new StreamHandler(__DIR__ . '/../../logs/app.log', Level::Debug);
                $this->logger->pushHandler($fileHandler);
            }
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if running in cloud environment
     */
    private function isCloudEnvironment(): bool
    {
        return getenv('K_SERVICE') !== false || getenv('GAE_SERVICE') !== false;
    }

    /**
     * Log error (system failures, 5xx errors)
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $this->enrichContext($context));
    }

    /**
     * Log warning (degraded performance, retry attempts)
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $this->enrichContext($context));
    }

    /**
     * Log info (successful operations, audit trail)
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $this->enrichContext($context));
    }

    /**
     * Log debug (detailed diagnostic info)
     */
    public function debug(string $message, array $context = []): void
    {
        // Only log debug in non-production
        if (!$this->isCloudEnvironment()) {
            $this->logger->debug($message, $this->enrichContext($context));
        }
    }

    /**
     * Log authentication event (audit trail for FERPA compliance)
     */
    public function logAuthentication(string $username, bool $success, string $ip, array $context = []): void
    {
        $eventContext = array_merge($context, [
            'event_type' => 'authentication',
            'username' => $username,
            'success' => $success,
            'ip_address' => $ip,
            'timestamp' => date('c'),
        ]);

        if ($success) {
            $this->info("Authentication successful: {$username}", $eventContext);
        } else {
            $this->warning("Authentication failed: {$username}", $eventContext);
        }
    }

    /**
     * Log data access (audit trail for FERPA compliance)
     */
    public function logDataAccess(int $staffID, string $resourceType, int $resourceID, string $action, array $context = []): void
    {
        $eventContext = array_merge($context, [
            'event_type' => 'data_access',
            'staff_id' => $staffID,
            'resource_type' => $resourceType,
            'resource_id' => $resourceID,
            'action' => $action,
            'timestamp' => date('c'),
        ]);

        $this->info("Data access: {$action} {$resourceType}#{$resourceID} by staff#{$staffID}", $eventContext);
    }

    /**
     * Log payment transaction (audit trail for financial compliance)
     */
    public function logPayment(int $paymentID, int $studentID, float $amount, string $method, int $processedBy, array $context = []): void
    {
        $eventContext = array_merge($context, [
            'event_type' => 'payment',
            'payment_id' => $paymentID,
            'student_id' => $studentID,
            'amount' => $amount,
            'payment_method' => $method,
            'processed_by' => $processedBy,
            'timestamp' => date('c'),
        ]);

        $this->info("Payment processed: \${$amount} for student#{$studentID}", $eventContext);
    }

    /**
     * Log security event (potential attacks, permission denials)
     */
    public function logSecurityEvent(string $eventType, string $description, array $context = []): void
    {
        $eventContext = array_merge($context, [
            'event_type' => 'security',
            'security_event_type' => $eventType,
            'description' => $description,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'timestamp' => date('c'),
        ]);

        $this->warning("Security event: {$eventType} - {$description}", $eventContext);
    }

    /**
     * Log performance metric
     */
    public function logPerformance(string $operation, float $durationMs, array $context = []): void
    {
        $eventContext = array_merge($context, [
            'event_type' => 'performance',
            'operation' => $operation,
            'duration_ms' => $durationMs,
            'timestamp' => date('c'),
        ]);

        // Warn if operation is slow
        if ($durationMs > 2000) {
            $this->warning("Slow operation: {$operation} took {$durationMs}ms", $eventContext);
        } else {
            $this->debug("Performance: {$operation} took {$durationMs}ms", $eventContext);
        }
    }

    /**
     * Log database query (for slow query analysis)
     */
    public function logQuery(string $query, float $durationMs, array $params = []): void
    {
        $context = [
            'event_type' => 'database_query',
            'query' => $this->sanitizeQuery($query),
            'duration_ms' => $durationMs,
            'params' => $this->sanitizeParams($params),
        ];

        // Log slow queries as warnings
        if ($durationMs > 500) {
            $this->warning("Slow query: {$durationMs}ms", $context);
        } else {
            $this->debug("Query executed: {$durationMs}ms", $context);
        }
    }

    /**
     * Enrich context with common metadata
     */
    private function enrichContext(array $context): array
    {
        return array_merge($context, [
            'service' => 'cor4edu-sms',
            'version' => '1.0.0',
            'environment' => $this->isCloudEnvironment() ? 'production' : 'development',
            'request_id' => $_SERVER['HTTP_X_CLOUD_TRACE_CONTEXT'] ?? uniqid('req_'),
        ]);
    }

    /**
     * Sanitize SQL query for logging (remove sensitive data)
     */
    private function sanitizeQuery(string $query): string
    {
        // Remove actual values from queries, keep structure
        $query = preg_replace("/'[^']*'/", "'***'", $query);
        return substr($query, 0, 500); // Limit length
    }

    /**
     * Sanitize query parameters (remove sensitive data)
     */
    private function sanitizeParams(array $params): array
    {
        $sanitized = [];
        $sensitiveKeys = ['password', 'passwordStrong', 'passwordStrongSalt', 'ssn', 'credit_card'];

        foreach ($params as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $sanitized[$key] = '***REDACTED***';
            } else {
                $sanitized[$key] = is_string($value) ? substr($value, 0, 100) : $value;
            }
        }

        return $sanitized;
    }
}
