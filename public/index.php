<?php
/**
 * COR4EDU SMS Front Controller
 * Following Gibbon's simple pattern - NO complex frameworks
 */

// Start session first
session_start();

// Bootstrap - Gibbon-style service container
try {
    $container = require_once __DIR__ . '/../bootstrap.php';
} catch (Exception $e) {
    // Show detailed bootstrap error
    die('Bootstrap failed: ' . $e->getMessage());
}

// Error reporting for development
if ($_ENV['APP_DEBUG'] === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
}

// Simple Twig setup (no complex middleware)
$loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../resources/templates');
$twig = new \Twig\Environment($loader, [
    'cache' => false,  // Temporarily disabled to force template recompilation
    'debug' => $_ENV['APP_DEBUG'] === 'true',
]);

// Add global variables to Twig
$twig->addGlobal('app_name', $_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'COR4EDU SMS');
$twig->addGlobal('app_url', $_ENV['APP_URL'] ?? getenv('APP_URL') ?: 'https://sms-edu-938209083489.us-central1.run.app');

// Get the current page - Gibbon style routing
$q = $_GET['q'] ?? '';

// TEMPORARY DEBUG - Show what $q contains
if (isset($_GET['debug'])) {
    echo "<pre>DEBUG: \$q = '" . htmlspecialchars($q) . "'\n";
    echo "DEBUG: \$_GET = " . print_r($_GET, true) . "\n";
    echo "DEBUG: \$_SERVER['REQUEST_URI'] = " . ($_SERVER['REQUEST_URI'] ?? 'not set') . "</pre>";
    exit;
}

// Simple routing based on Gibbon patterns
switch ($q) {
    case '':
    case '/':
        // Home page
        echo $twig->render('index.twig.html', [
            'title' => 'Welcome to COR4EDU SMS',
            'message' => 'Student Management System'
        ]);
        break;

    case '/health':
        // Health check endpoint for Cloud Run
        header('Content-Type: application/json');
        http_response_code(200);

        // Test database connection
        try {
            $db = \Cor4Edu\Config\Database::getInstance();
            $db->query('SELECT 1');
            $dbStatus = 'healthy';
        } catch (Exception $e) {
            $dbStatus = 'unhealthy';
            http_response_code(503);
        }

        echo json_encode([
            'status' => $dbStatus === 'healthy' ? 'healthy' : 'unhealthy',
            'timestamp' => date('c'),
            'service' => 'cor4edu-sms',
            'database' => $dbStatus
        ]);
        exit;

    case '/check-files':
        // DIAGNOSTIC: Check if files exist in container
        echo "<!DOCTYPE html><html><head><title>File Check</title></head><body>";
        echo "<h1>🔍 Container File Existence Check</h1>";
        echo "<p><strong>Active Revision:</strong> " . ($_ENV['K_REVISION'] ?? 'Unknown') . "</p>";

        $filesToCheck = [
            '/var/www/html/modules/Programs/program_manage.php' => 'Programs Main',
            '/var/www/html/modules/Programs/program_manage_delete.php' => 'Delete Handler (NEW FILE)',
            '/var/www/html/resources/templates/programs/index.twig.html' => 'Programs Template',
            '/var/www/html/resources/templates/programs/delete_confirm.twig.html' => 'Delete Confirm (NEW)',
            '/var/www/html/modules/Admin/Staff/staff_manage_view.php' => 'Staff View',
        ];

        echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #333; color: white;'><th>Description</th><th>Path</th><th>Status</th><th>Size</th></tr>";

        foreach ($filesToCheck as $file => $desc) {
            $exists = file_exists($file);
            $size = $exists ? filesize($file) : 0;
            $status = $exists ? '✅ EXISTS' : '❌ MISSING';
            $bg = $exists ? '#d4f4dd' : '#f4d4d4';

            echo "<tr style='background: {$bg};'>";
            echo "<td><strong>{$desc}</strong></td>";
            echo "<td><code>{$file}</code></td>";
            echo "<td style='font-size: 20px;'>{$status}</td>";
            echo "<td>" . ($exists ? number_format($size) . ' bytes' : 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        echo "<h2>📁 Directory Listing: /var/www/html/modules/Programs/</h2>";
        $dir = '/var/www/html/modules/Programs/';
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            echo "<ul>";
            foreach ($files as $file) {
                echo "<li>{$file}</li>";
            }
            echo "</ul>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>❌ DIRECTORY NOT FOUND!</p>";
        }

        echo "<p><a href='/'>← Back to Home</a></p>";
        echo "</body></html>";
        exit;

    case '/login':
        // Login page
        echo $twig->render('auth/login.twig.html', [
            'title' => 'Login - COR4EDU SMS'
        ]);
        break;

    case '/loginProcess':
        // Process login
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        try {
            // Database authentication
            $pdo = $container['db'];

            $stmt = $pdo->prepare("SELECT * FROM cor4edu_staff WHERE username = ? AND active = 'Y'");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['passwordStrong'])) {
                // Authentication successful - set session variables
                $_SESSION['cor4edu'] = [
                    'staffID' => $user['staffID'],
                    'username' => $user['username'],
                    'firstName' => $user['firstName'],
                    'lastName' => $user['lastName'],
                    'email' => $user['email'],
                    'is_super_admin' => ($user['isSuperAdmin'] === 'Y'),
                    'loggedIn' => true
                ];

                // Update last login
                $updateStmt = $pdo->prepare("UPDATE cor4edu_staff SET lastLogin = NOW() WHERE staffID = ?");
                $updateStmt->execute([$user['staffID']]);

                // Redirect to dashboard
                header('Location: index.php?q=/dashboard');
                exit;
            } else {
                // Login failed
                echo $twig->render('auth/login.twig.html', [
                    'title' => 'Login - COR4EDU SMS',
                    'error' => 'Invalid username or password'
                ]);
            }
        } catch (Exception $e) {
            // Database error - show detailed error in development
            $errorMessage = $_ENV['APP_DEBUG'] === 'true'
                ? 'Database error: ' . $e->getMessage()
                : 'System error. Please try again later.';

            echo $twig->render('auth/login.twig.html', [
                'title' => 'Login - COR4EDU SMS',
                'error' => $errorMessage
            ]);
        }
        break;

    case '/dashboard':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Load dashboard data - Gibbon style
        $studentGateway = getGateway('Cor4Edu\Domain\Student\StudentGateway');
        $programGateway = getGateway('Cor4Edu\Domain\Program\ProgramGateway');
        $staffGateway = getGateway('Cor4Edu\Domain\Staff\StaffGateway');

        // Get statistics using public methods
        $totalStudents = $studentGateway->getTotalStudentCount();
        $activeStudents = $studentGateway->getActiveStudentCount();
        $totalPrograms = $programGateway->getActiveProgramCount();
        $totalStaff = $staffGateway->getTotalStaffCount();

        // Get user permissions for navigation using new permission system
        $navigationPermissions = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);

        // Clear any stored flash errors from session
        unset($_SESSION['cor4edu']['flash_errors']);

        // Dashboard
        echo $twig->render('dashboard.twig.html', [
            'title' => 'Dashboard - COR4EDU SMS',
            'user' => array_merge($_SESSION['cor4edu'], ['permissions' => $navigationPermissions]),
            'totalStudents' => $totalStudents,
            'activeStudents' => $activeStudents,
            'totalPrograms' => $totalPrograms,
            'totalStaff' => $totalStaff
        ]);
        break;

    case '/modules/Students/student_manage.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // SuperAdmin bypass - always allow SuperAdmin access
        if (!$_SESSION['cor4edu']['is_super_admin']) {
            // Check if user has permission to view students
            if (!hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_information_tab')) {
                header('Location: index.php?q=/access_denied');
                exit;
            }
        }

        // Include the student management module
        require_once __DIR__ . '/../modules/Students/student_manage.php';
        break;

    case '/modules/Students/student_manage_add.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the student add module
        require_once __DIR__ . '/../modules/Students/student_manage_add.php';
        break;

    case '/modules/Students/student_manage_addProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the student add process module
        require_once __DIR__ . '/../modules/Students/student_manage_addProcess.php';
        break;

    case '/modules/Students/student_manage_view.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // SuperAdmin bypass - always allow SuperAdmin access
        if (!$_SESSION['cor4edu']['is_super_admin']) {
            // Check if user has permission to view at least one student tab
            $hasStudentAccess = hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_information_tab') ||
                               hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_admissions_tab') ||
                               hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_bursar_tab') ||
                               hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_registrar_tab') ||
                               hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_academics_tab') ||
                               hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_career_tab') ||
                               hasPermission($_SESSION['cor4edu']['staffID'], 'students', 'view_graduation_tab');

            if (!$hasStudentAccess) {
                header('Location: index.php?q=/access_denied');
                exit;
            }
        }

        // Include the student view module
        require_once __DIR__ . '/../modules/Students/student_manage_view.php';
        break;

    case '/modules/Students/student_manage_edit.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the student edit module
        require_once __DIR__ . '/../modules/Students/student_manage_edit.php';
        break;

    case '/modules/Students/student_manage_editProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the student edit process module
        require_once __DIR__ . '/../modules/Students/student_manage_editProcess.php';
        break;

    case '/modules/Students/payment_addProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the payment add process module
        require_once __DIR__ . '/../modules/Students/payment_addProcess.php';
        break;

    case '/modules/Students/payment_editProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the payment edit process module
        require_once __DIR__ . '/../modules/Students/payment_editProcess.php';
        break;

    case '/modules/Students/employment_status_updateProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the employment status update process module
        require_once __DIR__ . '/../modules/Students/employment_status_updateProcess.php';
        break;

    case '/modules/Students/placement_record_process.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the placement record process module
        require_once __DIR__ . '/../modules/Students/placement_record_process.php';
        break;

    case '/modules/Students/academic_info_updateProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the academic info update process module
        require_once __DIR__ . '/../modules/Students/academic_info_updateProcess.php';
        break;

    case '/modules/Students/FacultyNotes/note_process.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the faculty note process module
        require_once __DIR__ . '/../modules/Students/FacultyNotes/note_process.php';
        break;

    case '/modules/Students/FacultyNotes/meeting_process.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the meeting process module
        require_once __DIR__ . '/../modules/Students/FacultyNotes/meeting_process.php';
        break;

    case '/modules/Students/FacultyNotes/support_session_process.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the support session process module
        require_once __DIR__ . '/../modules/Students/FacultyNotes/support_session_process.php';
        break;

    case '/modules/Staff/staff_profile_view.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the staff profile view module
        require_once __DIR__ . '/../modules/Staff/staff_profile_view.php';
        break;

    case '/modules/Staff/staff_profile_updateProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the staff profile update process module
        require_once __DIR__ . '/../modules/Staff/staff_profile_updateProcess.php';
        break;

    case '/modules/Programs/program_manage.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // SuperAdmin bypass - always allow SuperAdmin access
        if (!$_SESSION['cor4edu']['is_super_admin']) {
            // Check if user has permission to view programs
            if (!hasPermission($_SESSION['cor4edu']['staffID'], 'programs', 'view_programs')) {
                header('Location: index.php?q=/access_denied');
                exit;
            }
        }

        // Include the program management module
        require_once __DIR__ . '/../modules/Programs/program_manage.php';
        break;

    case '/modules/Programs/program_manage_add.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the program add module
        require_once __DIR__ . '/../modules/Programs/program_manage_add.php';
        break;

    case '/modules/Programs/program_manage_addProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the program add process module
        require_once __DIR__ . '/../modules/Programs/program_manage_addProcess.php';
        break;

    case '/modules/Programs/program_manage_edit.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the program edit module
        require_once __DIR__ . '/../modules/Programs/program_manage_edit.php';
        break;

    case '/modules/Programs/program_manage_editProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the program edit process module
        require_once __DIR__ . '/../modules/Programs/program_manage_editProcess.php';
        break;

    case '/modules/Students/Documents/document_upload.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the document upload module
        require_once __DIR__ . '/../modules/Students/Documents/document_upload.php';
        break;

    case '/modules/Students/Documents/document_uploadProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the document upload process module
        require_once __DIR__ . '/../modules/Students/Documents/document_uploadProcess.php';
        break;

    case '/modules/Students/Documents/document_deleteConfirm.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the document delete confirmation module
        require_once __DIR__ . '/../modules/Students/Documents/document_deleteConfirm.php';
        break;

    case '/modules/Students/Documents/document_delete.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the document delete process module
        require_once __DIR__ . '/../modules/Students/Documents/document_delete.php';
        break;

    case '/modules/Students/Requirements/requirement_upload.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the requirement upload module
        require_once __DIR__ . '/../modules/Students/Requirements/requirement_upload.php';
        break;

    case '/modules/Students/Requirements/requirement_uploadProcess.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the requirement upload process module
        require_once __DIR__ . '/../modules/Students/Requirements/requirement_uploadProcess.php';
        break;

    case '/modules/Students/Requirements/requirement_view.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the requirement view module
        require_once __DIR__ . '/../modules/Students/Requirements/requirement_view.php';
        break;

    case '/modules/Students/Requirements/requirement_delete.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the requirement delete process module
        require_once __DIR__ . '/../modules/Students/Requirements/requirement_delete.php';
        break;

    case '/modules/Admin/Documents/document_history_manage.php':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the admin document history management module
        require_once __DIR__ . '/../modules/Admin/Documents/document_history_manage.php';
        break;

    case '/modules/Admin/Documents/document_deleteProcess.php':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the admin document delete process module
        require_once __DIR__ . '/../modules/Admin/Documents/document_deleteProcess.php';
        break;

    case '/modules/Admin/Documents/document_bulkDeleteProcess.php':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Include the admin bulk delete process module
        require_once __DIR__ . '/../modules/Admin/Documents/document_bulkDeleteProcess.php';
        break;

    case '/serve-file':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // File serving functionality
        $documentID = $_GET['documentID'] ?? '';

        if (empty($documentID)) {
            http_response_code(400);
            echo "Document ID required";
            exit;
        }

        try {
            $documentGateway = getGateway('Cor4Edu\\Domain\\Document\\DocumentGateway');
            $document = $documentGateway->getDocumentDetails((int)$documentID);

            if (!$document) {
                http_response_code(404);
                echo "Document not found";
                exit;
            }

            // Check permissions - admin can view all, staff can only view if they uploaded it or it's for their students
            $canView = false;

            if ($_SESSION['cor4edu']['is_super_admin']) {
                $canView = true;
            } elseif ($document['uploadedBy'] == $_SESSION['cor4edu']['staffID']) {
                $canView = true;
            }
            // Add more permission logic as needed for specific entity access

            if (!$canView) {
                http_response_code(403);
                echo "Access denied";
                exit;
            }

            $filePath = __DIR__ . '/../' . $document['filePath'];

            // Debug information for development
            if ($_ENV['APP_DEBUG'] === 'true') {
                error_log("Debug file serving for document ID: " . $documentID);
                error_log("Document data: " . print_r($document, true));
                error_log("Constructed file path: " . $filePath);
                error_log("File exists check: " . (file_exists($filePath) ? 'YES' : 'NO'));

                // Also check if the relative path exists
                $relativePath = $document['filePath'];
                error_log("Relative path: " . $relativePath);
                error_log("Working directory: " . getcwd());
                error_log("__DIR__: " . __DIR__);
            }

            if (!file_exists($filePath)) {
                http_response_code(404);
                if ($_ENV['APP_DEBUG'] === 'true') {
                    echo "File not found on disk. Expected path: " . $filePath .
                         ". Document filePath field: " . $document['filePath'];
                } else {
                    echo "File not found on disk";
                }
                exit;
            }

            // Serve the file
            $mimeType = mime_content_type($filePath) ?: 'application/octet-stream';

            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($filePath));
            header('Content-Disposition: inline; filename="' . basename($document['fileName']) . '"');
            header('Cache-Control: private, must-revalidate');
            header('Pragma: private');
            header('Expires: 0');

            readfile($filePath);
            exit;

        } catch (Exception $e) {
            http_response_code(500);
            echo "Error serving file: " . ($container['app']['debug'] ? $e->getMessage() : 'Internal server error');
            exit;
        }
        break;

    case '/logout':
        // Logout
        session_destroy();
        header('Location: index.php?q=/login');
        exit;
        break;

    case '/access_denied':
        // Access Denied page
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        echo $twig->render('errors/access_denied.twig.html', [
            'title' => 'Access Denied - COR4EDU SMS',
            'message' => 'You do not have permission to access this resource.',
            'user' => $_SESSION['cor4edu']
        ]);
        break;

    case '/debug-permissions':
        // Debug permissions for SuperAdmin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }

        echo "<h1>Permission Debug for SuperAdmin</h1>";
        echo "<h2>Session Data:</h2>";
        echo "<pre>" . htmlspecialchars(print_r($_SESSION['cor4edu'], true)) . "</pre>";

        try {
            echo "<h2>hasPermission() Test:</h2>";
            $result = hasPermission($_SESSION['cor4edu']['staffID'], 'permissions', 'manage_permissions');
            echo "<p>hasPermission(staffID=" . $_SESSION['cor4edu']['staffID'] . ", 'permissions', 'manage_permissions') = " . ($result ? 'TRUE' : 'FALSE') . "</p>";

            echo "<h2>Staff Record:</h2>";
            $staffGateway = getGateway('Cor4Edu\Domain\Staff\StaffGateway');
            $staff = $staffGateway->getStaffById($_SESSION['cor4edu']['staffID']);
            echo "<pre>" . htmlspecialchars(print_r($staff, true)) . "</pre>";

            echo "<h2>Navigation Permissions:</h2>";
            $navPerms = getUserPermissionsForNavigation($_SESSION['cor4edu']['staffID']);
            echo "<pre>" . htmlspecialchars(print_r($navPerms, true)) . "</pre>";

        } catch (Exception $e) {
            echo "<h2>ERROR:</h2>";
            echo "<p style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
        }

        echo "<p><a href='index.php?q=/dashboard'>Back to Dashboard</a></p>";
        break;

    case '/debug-documents':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }

        // Debug documents and file system sync
        try {
            $documentGateway = getGateway('Cor4Edu\\Domain\\Document\\DocumentGateway');
            $pdo = $container['db'];

            echo "<h1>Document Debug Information</h1>";

            // Get all documents
            $stmt = $pdo->query("SELECT documentID, fileName, filePath, entityType, entityID, category, uploadedOn FROM cor4edu_documents ORDER BY documentID");
            $documents = $stmt->fetchAll();

            echo "<h2>Documents in Database:</h2>";
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>File Name</th><th>File Path</th><th>Entity</th><th>Entity ID</th><th>Category</th><th>Uploaded</th><th>File Exists</th></tr>";

            foreach ($documents as $doc) {
                $fullPath = __DIR__ . '/../' . $doc['filePath'];
                $fileExists = file_exists($fullPath) ? '✅' : '❌';

                echo "<tr>";
                echo "<td>" . htmlspecialchars($doc['documentID']) . "</td>";
                echo "<td>" . htmlspecialchars($doc['fileName']) . "</td>";
                echo "<td>" . htmlspecialchars($doc['filePath']) . "</td>";
                echo "<td>" . htmlspecialchars($doc['entityType']) . "</td>";
                echo "<td>" . htmlspecialchars($doc['entityID']) . "</td>";
                echo "<td>" . htmlspecialchars($doc['category']) . "</td>";
                echo "<td>" . htmlspecialchars($doc['uploadedOn']) . "</td>";
                echo "<td>" . $fileExists . "</td>";
                echo "</tr>";
            }
            echo "</table>";

            // Check file system
            echo "<h2>Files on Disk (Student 1):</h2>";
            $studentDir = __DIR__ . '/../storage/uploads/students/1';
            if (is_dir($studentDir)) {
                $files = scandir($studentDir);
                echo "<ul>";
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..') {
                        echo "<li>" . htmlspecialchars($file) . "</li>";
                    }
                }
                echo "</ul>";
            } else {
                echo "<p>Student directory doesn't exist: " . $studentDir . "</p>";
            }

            echo "<p><a href='index.php?q=/modules/Admin/Documents/document_history_manage.php'>Back to Document Management</a></p>";

        } catch (Exception $e) {
            echo "<h1>Debug Error</h1>";
            echo "<p>Error: " . $e->getMessage() . "</p>";
        }
        break;

    case '/test-db':
        // Test database connection
        try {
            $pdo = $container['db'];
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM cor4edu_staff");
            $result = $stmt->fetch();

            echo "<h1>Database Test</h1>";
            echo "<p>✅ Database connection successful!</p>";
            echo "<p>Staff count: " . $result['count'] . "</p>";

            // Check table structure
            $stmt = $pdo->query("DESCRIBE cor4edu_staff");
            $columns = $stmt->fetchAll();
            echo "<h2>Staff Table Columns:</h2>";
            echo "<ul>";
            foreach ($columns as $column) {
                echo "<li>" . htmlspecialchars($column['Field']) . " (" . htmlspecialchars($column['Type']) . ")</li>";
            }
            echo "</ul>";

            // Check superadmin user with all columns
            $stmt = $pdo->query("SELECT * FROM cor4edu_staff WHERE username = 'superadmin'");
            $user = $stmt->fetch();
            if ($user) {
                echo "<h2>Superadmin User Data:</h2>";
                echo "<pre>" . htmlspecialchars(print_r($user, true)) . "</pre>";

                // Test password verification
                $testPasswords = ['admin123', 'password', 'secret', 'admin', 'superadmin'];
                echo "<h3>Password Tests:</h3>";
                foreach ($testPasswords as $testPassword) {
                    $isValid = password_verify($testPassword, $user['passwordStrong']);
                    echo "<p>Password '{$testPassword}': " . ($isValid ? '✅ Valid' : '❌ Invalid') . "</p>";
                }
            } else {
                echo "<p>❌ Superadmin user not found!</p>";
            }

            // Check programs table data
            echo "<h2>Programs Table Data:</h2>";
            $stmt = $pdo->query("SELECT * FROM cor4edu_programs");
            $programs = $stmt->fetchAll();
            if ($programs) {
                echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
                echo "<tr><th>ID</th><th>Name</th><th>Code</th><th>Active</th><th>Created</th></tr>";
                foreach ($programs as $program) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($program['programID']) . "</td>";
                    echo "<td>" . htmlspecialchars($program['name']) . "</td>";
                    echo "<td>" . htmlspecialchars($program['programCode']) . "</td>";
                    echo "<td>" . htmlspecialchars($program['active']) . "</td>";
                    echo "<td>" . htmlspecialchars($program['createdOn']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "<p>Total programs: " . count($programs) . "</p>";
                echo "<p>Active programs: " . count(array_filter($programs, function($p) { return $p['active'] === 'Y'; })) . "</p>";
            } else {
                echo "<p>No programs found in table.</p>";
            }

            echo "<p><a href='index.php?q=/dashboard'>Back to Dashboard</a></p>";
        } catch (Exception $e) {
            echo "<h1>Database Test</h1>";
            echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
            echo "<p><a href='index.php?q=/dashboard'>Back to Dashboard</a></p>";
        }
        break;

    case '/debug-session':
        // Debug endpoint to check session and database values
        echo "<h1>Debug Session & Database</h1>";

        echo "<h2>Session Data:</h2>";
        if (isset($_SESSION['cor4edu'])) {
            echo "<pre>" . htmlspecialchars(print_r($_SESSION['cor4edu'], true)) . "</pre>";

            echo "<h3>Key Values:</h3>";
            echo "<ul>";
            echo "<li>is_super_admin: " . (isset($_SESSION['cor4edu']['is_super_admin']) ?
                ($_SESSION['cor4edu']['is_super_admin'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "</li>";
            echo "<li>staffID: " . ($_SESSION['cor4edu']['staffID'] ?? 'NOT SET') . "</li>";
            echo "<li>username: " . ($_SESSION['cor4edu']['username'] ?? 'NOT SET') . "</li>";
            echo "</ul>";
        } else {
            echo "<p>❌ No session found - user not logged in</p>";
        }

        echo "<h2>Database - Superadmin User:</h2>";
        try {
            $pdo = $container['db'];
            $stmt = $pdo->query("SELECT staffID, username, email, isSuperAdmin, active FROM cor4edu_staff WHERE username = 'superadmin'");
            $user = $stmt->fetch();
            if ($user) {
                echo "<pre>" . htmlspecialchars(print_r($user, true)) . "</pre>";
                echo "<p><strong>isSuperAdmin value: '" . $user['isSuperAdmin'] . "'</strong></p>";
            } else {
                echo "<p>❌ Superadmin user not found in database</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ Database error: " . $e->getMessage() . "</p>";
        }

        echo "<h2>File Checks:</h2>";
        $filesToCheck = [
            '/var/www/html/modules/Programs/program_manage_delete.php',
            '/var/www/html/modules/Admin/Staff/staff_manage_view.php',
            '/var/www/html/resources/templates/programs/index.twig.html',
            '/var/www/html/resources/templates/programs/delete_confirm.twig.html'
        ];
        echo "<ul>";
        foreach ($filesToCheck as $file) {
            $exists = file_exists($file) ? '✅ EXISTS' : '❌ MISSING';
            echo "<li>{$file}: {$exists}</li>";
        }
        echo "</ul>";

        echo "<p><a href='index.php?q=/dashboard'>Back to Dashboard</a></p>";
        break;

    // Staff Management Routes
    case '/modules/Admin/Staff/staff_manage.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }

        // SuperAdmin bypass - always allow SuperAdmin access
        if (!$_SESSION['cor4edu']['is_super_admin']) {
            // Check if user has permission to view staff list
            if (!hasPermission($_SESSION['cor4edu']['staffID'], 'staff', 'view_staff_list')) {
                header('Location: index.php?q=/access_denied');
                exit;
            }
        }

        // Include the staff management module
        require_once __DIR__ . '/../modules/Admin/Staff/staff_manage.php';
        break;

    case '/modules/Admin/Staff/staff_manage_add.php':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the staff add module
        require_once __DIR__ . '/../modules/Admin/Staff/staff_manage_add.php';
        break;

    case '/modules/Admin/Staff/staff_manage_view.php':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the staff view module
        require_once __DIR__ . '/../modules/Admin/Staff/staff_manage_view.php';
        break;

    case '/modules/Admin/Staff/staff_manage_edit.php':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the staff edit module
        require_once __DIR__ . '/../modules/Admin/Staff/staff_manage_edit.php';
        break;

    case '/modules/Admin/Staff/staff_permissions.php':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the staff permissions module
        require_once __DIR__ . '/../modules/Admin/Staff/staff_permissions.php';
        break;

    // Staff Document Management Routes
    case '/modules/Admin/Staff/Documents/staff_document_upload.php':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the staff document upload module
        require_once __DIR__ . '/../modules/Admin/Staff/Documents/staff_document_upload.php';
        break;

    case '/modules/Admin/Staff/Documents/staff_document_uploadProcess.php':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the staff document upload process module
        require_once __DIR__ . '/../modules/Admin/Staff/Documents/staff_document_uploadProcess.php';
        break;

    case '/modules/Admin/Staff/Documents/staff_document_manage.php':
        // Check if logged in and is super admin
        if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the staff document management module
        require_once __DIR__ . '/../modules/Admin/Staff/Documents/staff_document_manage.php';
        break;

    // Reports Module Routes
    case '/modules/Reports/reports_overview.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the overview reports module
        require_once __DIR__ . '/../modules/Reports/reports_overview.php';
        break;

    case '/modules/Reports/reports_admissions.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the admissions reports module
        require_once __DIR__ . '/../modules/Reports/reports_admissions.php';
        break;

    case '/modules/Reports/reports_financial.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the financial reports module
        require_once __DIR__ . '/../modules/Reports/reports_financial.php';
        break;

    case '/modules/Reports/reports_career.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the career reports module
        require_once __DIR__ . '/../modules/Reports/reports_career.php';
        break;

    case '/modules/Reports/reports_academic.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the academic reports module
        require_once __DIR__ . '/../modules/Reports/reports_academic.php';
        break;

    case '/modules/Reports/export_process.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the export process module
        require_once __DIR__ . '/../modules/Reports/export_process.php';
        break;

    case '/modules/Reports/reports_career_export.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the career reports export module
        require_once __DIR__ . '/../modules/Reports/reports_career_export.php';
        break;

    case '/modules/Reports/reports_academic_export.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        // Include the academic reports export module
        require_once __DIR__ . '/../modules/Reports/export_process.php';
        break;

    // Permission Management Routes
    case '/modules/Admin/Permissions/permissions_manage.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        // SuperAdmin bypass - always allow SuperAdmin access
        if (!$_SESSION['cor4edu']['is_super_admin']) {
            // Check if user has permission to manage permissions
            if (!hasPermission($_SESSION['cor4edu']['staffID'], 'permissions', 'manage_permissions')) {
                header('Location: index.php?q=/access_denied');
                exit;
            }
        }
        // Include the permissions management module
        require_once __DIR__ . '/../modules/Admin/Permissions/permissions_manage.php';
        break;

    case '/modules/Admin/Permissions/staff_permissions_edit.php':
        // Check if logged in
        if (!isset($_SESSION['cor4edu'])) {
            header('Location: index.php?q=/login');
            exit;
        }
        // SuperAdmin bypass - always allow SuperAdmin access
        if (!$_SESSION['cor4edu']['is_super_admin']) {
            // Check if user has permission to manage staff permissions
            if (!hasPermission($_SESSION['cor4edu']['staffID'], 'permissions', 'manage_staff_permissions')) {
                header('Location: index.php?q=/access_denied');
                exit;
            }
        }
        // Include the staff permissions edit module
        require_once __DIR__ . '/../modules/Admin/Permissions/staff_permissions_edit.php';
        break;

    default:
        // 404 Not Found
        http_response_code(404);
        echo $twig->render('errors/404.twig.html', [
            'title' => '404 - Page Not Found',
            'message' => 'The page you requested could not be found.'
        ]);
        break;
}