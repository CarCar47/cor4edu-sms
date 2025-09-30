<?php
/**
 * Faculty Notes System Migration Runner
 * Creates tables for comprehensive faculty documentation and student support tracking
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/database.php';

use Cor4Edu\Config\Database;

echo "COR4EDU SMS Faculty Notes System Migration\n";
echo "=========================================\n\n";

try {
    // Test database connection
    echo "Testing database connection...\n";
    $pdo = Database::getInstance();
    echo "✅ Database connection successful!\n\n";

    // Read and execute migration file
    echo "Running faculty notes system migration...\n";
    $migrationFile = __DIR__ . '/database_migrations/create_faculty_notes_system.sql';

    if (!file_exists($migrationFile)) {
        throw new RuntimeException("Migration file not found: {$migrationFile}");
    }

    $sql = file_get_contents($migrationFile);

    // Split the SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\s*--/', $stmt);
        }
    );

    $pdo->beginTransaction();

    foreach ($statements as $statement) {
        if (trim($statement)) {
            echo "Executing: " . substr(trim($statement), 0, 50) . "...\n";
            $pdo->exec($statement);
        }
    }

    $pdo->commit();
    echo "✅ Faculty notes system migration completed successfully!\n\n";

    // Verify tables were created
    echo "Verifying table creation...\n";
    $tables = [
        'cor4edu_faculty_notes' => 'Faculty notes system',
        'cor4edu_academic_support_sessions' => 'Academic support sessions tracking',
        'cor4edu_student_meetings' => 'Student meetings documentation',
        'cor4edu_academic_interventions' => 'Academic interventions tracking'
    ];

    foreach ($tables as $tableName => $description) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        if ($stmt->rowCount() > 0) {
            echo "✅ {$description} table created successfully\n";
        } else {
            echo "❌ Failed to create {$description} table\n";
        }
    }

    echo "\n📊 Faculty Notes System Tables Summary:\n";
    foreach ($tables as $tableName => $description) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$tableName}'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   - {$tableName}: {$result['count']} columns\n";
    }

    echo "\n🎉 Faculty Notes System is ready for use!\n";
    echo "\nNext Steps:\n";
    echo "1. Update the academic tab interface to include faculty notes\n";
    echo "2. Create the CRUD operations for notes and sessions\n";
    echo "3. Implement the meeting documentation features\n";
    echo "4. Add reporting capabilities for administrative review\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
    exit(1);
}