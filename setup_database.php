<?php
/**
 * Database Setup Script
 * Executes schema and seed data SQL files
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/database.php';

use Cor4Edu\Config\Database;

echo "COR4EDU SMS Database Setup\n";
echo "==========================\n\n";

try {
    // Test database connection
    echo "Testing database connection...\n";
    $pdo = Database::getInstance();
    echo "✅ Database connection successful!\n\n";

    // Execute schema file
    echo "Creating database schema...\n";
    $schemaFile = __DIR__ . '/database_schema_fixed.sql';

    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Schema file not found: {$schemaFile}");
    }

    $sql = file_get_contents($schemaFile);
    $pdo->exec($sql);
    echo "✅ Database schema created successfully!\n\n";

    // Execute seed data file
    echo "Inserting seed data...\n";
    $seedFile = __DIR__ . '/seed_data.sql';

    if (!file_exists($seedFile)) {
        throw new RuntimeException("Seed file not found: {$seedFile}");
    }

    $sql = file_get_contents($seedFile);
    $pdo->exec($sql);
    echo "✅ Seed data inserted successfully!\n\n";

    // Verify setup
    echo "Verifying setup...\n";

    // Check tables created
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "📊 Tables created: " . count($tables) . "\n";
    foreach ($tables as $table) {
        echo "   - {$table}\n";
    }
    echo "\n";

    // Check superadmin user
    $stmt = $pdo->query("SELECT staffCode, firstName, lastName, username, isSuperAdmin FROM cor4edu_staff WHERE isSuperAdmin = 'Y'");
    $admin = $stmt->fetch();

    if ($admin) {
        echo "👤 SuperAdmin user created:\n";
        echo "   - Name: {$admin['firstName']} {$admin['lastName']}\n";
        echo "   - Username: {$admin['username']}\n";
        echo "   - Staff Code: {$admin['staffCode']}\n";
        echo "   - Password: admin123\n\n";
    }

    // Check sample student
    $stmt = $pdo->query("SELECT studentCode, firstName, lastName, status FROM cor4edu_students LIMIT 1");
    $student = $stmt->fetch();

    if ($student) {
        echo "🎓 Sample student created:\n";
        echo "   - Name: {$student['firstName']} {$student['lastName']}\n";
        echo "   - Student Code: {$student['studentCode']}\n";
        echo "   - Status: {$student['status']}\n\n";
    }

    // Check permissions
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cor4edu_staff_permissions");
    $permCount = $stmt->fetch()['count'];
    echo "🔐 Permissions created: {$permCount}\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cor4edu_staff_tab_access");
    $tabCount = $stmt->fetch()['count'];
    echo "📑 Tab access rules: {$tabCount}\n\n";

    echo "🎉 Database setup completed successfully!\n";
    echo "You can now proceed with application setup.\n\n";
    echo "SuperAdmin Login Credentials:\n";
    echo "Username: superadmin\n";
    echo "Password: admin123\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
    exit(1);
}