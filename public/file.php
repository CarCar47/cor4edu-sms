<?php
/**
 * COR4EDU SMS - File Serving Handler
 * Following Gibbon patterns for secure file serving
 */

// Start session
session_start();

// Bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Check if user is logged in
if (!isset($_SESSION['cor4edu']) || !isset($_SESSION['cor4edu']['staffID'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

try {
    // Get file path from query parameter
    $filePath = $_GET['path'] ?? '';

    if (empty($filePath)) {
        header('HTTP/1.1 400 Bad Request');
        exit('File path required');
    }

    // Security: Prevent directory traversal
    $filePath = str_replace(['../', '.\\', '..\\'], '', $filePath);

    // Build absolute file path
    $absolutePath = __DIR__ . '/../' . $filePath;

    // Check if file exists
    if (!file_exists($absolutePath)) {
        header('HTTP/1.1 404 Not Found');
        exit('File not found');
    }

    // Security: Ensure file is in allowed directories
    $realPath = realpath($absolutePath);
    $allowedBase = realpath(__DIR__ . '/../storage/uploads/');

    if (strpos($realPath, $allowedBase) !== 0) {
        header('HTTP/1.1 403 Forbidden');
        exit('Access denied');
    }

    // Get file info
    $filename = basename($absolutePath);
    $mimeType = mime_content_type($absolutePath);
    $fileSize = filesize($absolutePath);

    // Set appropriate headers
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . $filename . '"');

    // Prevent caching for sensitive documents
    header('Cache-Control: private, no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output file
    readfile($absolutePath);

} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    exit('Error serving file');
}
?>