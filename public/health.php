<?php
/**
 * Health Check Endpoint
 *
 * Returns system health status for monitoring and load balancers.
 * Used by Cloud Run health checks and external monitoring.
 *
 * Returns:
 *   200 OK - All systems operational
 *   503 Service Unavailable - System degraded or down
 */

header('Content-Type: application/json');

$health = [
    'status' => 'healthy',
    'timestamp' => date('c'),
    'checks' => [],
    'version' => '1.0.0',
];

// Check 1: Database connectivity
try {
    $dbHost = getenv('DB_HOST') ?: ($_ENV['DB_SOCKET'] ?? 'localhost');
    $dbPort = getenv('DB_PORT') ?: '3306';
    $dbName = getenv('DB_NAME') ?: 'cor4edu_sms';
    $dbUser = getenv('DB_USERNAME') ?: 'root';
    $dbPass = getenv('DB_PASSWORD') ?: '';

    // Use Unix socket for Cloud SQL if available
    if (isset($_ENV['DB_SOCKET'])) {
        $dsn = "mysql:unix_socket={$_ENV['DB_SOCKET']};dbname={$dbName};charset=utf8mb4";
    } else {
        $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    }

    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 2, // 2 second timeout
    ]);

    // Simple query to verify database is responsive
    $stmt = $pdo->query("SELECT 1");
    $result = $stmt->fetch();

    $health['checks']['database'] = [
        'status' => 'healthy',
        'message' => 'Database connection successful',
    ];

} catch (PDOException $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['database'] = [
        'status' => 'unhealthy',
        'message' => 'Database connection failed',
        'error' => $e->getMessage(),
    ];
}

// Check 2: File system write access
try {
    $tempFile = sys_get_temp_dir() . '/health_check_' . time() . '.tmp';
    file_put_contents($tempFile, 'test');
    unlink($tempFile);

    $health['checks']['filesystem'] = [
        'status' => 'healthy',
        'message' => 'File system writable',
    ];

} catch (Exception $e) {
    $health['status'] = 'unhealthy';
    $health['checks']['filesystem'] = [
        'status' => 'unhealthy',
        'message' => 'File system not writable',
        'error' => $e->getMessage(),
    ];
}

// Check 3: PHP configuration
$health['checks']['php'] = [
    'status' => 'healthy',
    'version' => PHP_VERSION,
    'memory_limit' => ini_get('memory_limit'),
];

// Check 4: Required extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

if (!empty($missingExtensions)) {
    $health['status'] = 'unhealthy';
    $health['checks']['extensions'] = [
        'status' => 'unhealthy',
        'message' => 'Missing required PHP extensions',
        'missing' => $missingExtensions,
    ];
} else {
    $health['checks']['extensions'] = [
        'status' => 'healthy',
        'message' => 'All required extensions loaded',
    ];
}

// Set HTTP status code
http_response_code($health['status'] === 'healthy' ? 200 : 503);

echo json_encode($health, JSON_PRETTY_PRINT);
