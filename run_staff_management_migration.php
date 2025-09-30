<?php
/**
 * Staff Management System Migration Runner
 * Creates enhanced staff system with role-based permissions and document management
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/database.php';

use Cor4Edu\Config\Database;

echo "COR4EDU SMS Staff Management System Migration\n";
echo "============================================\n\n";

try {
    // Test database connection
    echo "Testing database connection...\n";
    $pdo = Database::getInstance();
    echo "✅ Database connection successful!\n\n";

    // Read and execute migration file
    echo "Running staff management system migration...\n";
    $migrationFile = __DIR__ . '/database_migrations/create_staff_management_system.sql';

    if (!file_exists($migrationFile)) {
        throw new RuntimeException("Migration file not found: {$migrationFile}");
    }

    $sql = file_get_contents($migrationFile);

    // Split the SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^\\s*--/', $stmt);
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
    echo "✅ Staff management system migration completed successfully!\n\n";

    // Verify tables were created/modified
    echo "Verifying table structure...\n";
    $tables = [
        'cor4edu_staff_role_types' => 'Staff role types and permission templates',
        'cor4edu_staff_document_requirements' => 'Staff document requirements definitions',
        'cor4edu_staff_document_status' => 'Staff document status tracking'
    ];

    foreach ($tables as $tableName => $description) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        if ($stmt->rowCount() > 0) {
            echo "✅ {$description} table created successfully\n";
        } else {
            echo "❌ Failed to create {$description} table\n";
        }
    }

    // Check staff table modifications
    echo "\nVerifying staff table enhancements...\n";
    $stmt = $pdo->query("DESCRIBE cor4edu_staff");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $newColumns = ['roleTypeID', 'phone', 'address', 'city', 'state', 'zipCode', 'country', 'dateOfBirth', 'emergencyContact', 'emergencyPhone', 'teachingPrograms', 'notes'];
    foreach ($newColumns as $column) {
        if (in_array($column, $columns)) {
            echo "✅ Staff table column '{$column}' added successfully\n";
        } else {
            echo "❌ Failed to add staff table column '{$column}'\n";
        }
    }

    echo "\n📊 Staff Management System Summary:\n";
    foreach ($tables as $tableName => $description) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = '{$tableName}'");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   - {$tableName}: {$result['count']} columns\n";
    }

    // Show default role types
    echo "\n👥 Default Staff Role Types:\n";
    $stmt = $pdo->query("SELECT roleTypeName, description FROM cor4edu_staff_role_types ORDER BY roleTypeID");
    $roleTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($roleTypes as $role) {
        echo "   - {$role['roleTypeName']}: {$role['description']}\n";
    }

    // Show document requirements
    echo "\n📄 Staff Document Requirements:\n";
    $stmt = $pdo->query("SELECT name, description FROM cor4edu_staff_document_requirements ORDER BY displayOrder");
    $requirements = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($requirements as $req) {
        echo "   - {$req['name']}: {$req['description']}\n";
    }

    echo "\n🎉 Staff Management System is ready!\n";
    echo "\nNext Steps:\n";
    echo "1. Create staff profile interface with 3 sections\n";
    echo "2. Build admin staff management interface\n";
    echo "3. Implement role-based student tab access\n";
    echo "4. Add staff document upload functionality\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
    exit(1);
}