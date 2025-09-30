<?php
/**
 * Migration Runner Script
 * Applies the academic dates migration to the database
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/database.php';

use Cor4Edu\Config\Database;

echo "COR4EDU SMS Migration Runner\n";
echo "============================\n\n";

try {
    // Test database connection
    echo "Testing database connection...\n";
    $pdo = Database::getInstance();
    echo "✅ Database connection successful!\n\n";

    // Execute migration file
    echo "Running academic dates migration...\n";
    $migrationFile = __DIR__ . '/database_migrations/update_academic_dates.sql';

    if (!file_exists($migrationFile)) {
        throw new RuntimeException("Migration file not found: {$migrationFile}");
    }

    // Execute each migration step individually
    echo "Step 1: Adding anticipatedGraduationDate column...\n";
    try {
        $pdo->exec("ALTER TABLE `cor4edu_students` ADD COLUMN `anticipatedGraduationDate` DATE NULL AFTER `enrollmentDate`");
        echo "✅ Added anticipatedGraduationDate column\n";
    } catch (Exception $e) {
        echo "⚠️  Warning: " . $e->getMessage() . "\n";
    }

    echo "Step 2: Adding actualGraduationDate column...\n";
    try {
        $pdo->exec("ALTER TABLE `cor4edu_students` ADD COLUMN `actualGraduationDate` DATE NULL AFTER `anticipatedGraduationDate`");
        echo "✅ Added actualGraduationDate column\n";
    } catch (Exception $e) {
        echo "⚠️  Warning: " . $e->getMessage() . "\n";
    }

    echo "Step 3: Adding withdrawnDate column...\n";
    try {
        $pdo->exec("ALTER TABLE `cor4edu_students` ADD COLUMN `withdrawnDate` DATE NULL AFTER `lastDayOfAttendance`");
        echo "✅ Added withdrawnDate column\n";
    } catch (Exception $e) {
        echo "⚠️  Warning: " . $e->getMessage() . "\n";
    }

    echo "Step 4: Migrating existing graduationDate to anticipatedGraduationDate...\n";
    try {
        $pdo->exec("UPDATE `cor4edu_students` SET `anticipatedGraduationDate` = `graduationDate` WHERE `graduationDate` IS NOT NULL");
        echo "✅ Migrated existing graduation dates\n";
    } catch (Exception $e) {
        echo "⚠️  Warning: " . $e->getMessage() . "\n";
    }

    echo "Step 5: Setting actualGraduationDate for graduated students...\n";
    try {
        $pdo->exec("UPDATE `cor4edu_students` SET `actualGraduationDate` = `graduationDate` WHERE `status` IN ('graduated', 'alumni') AND `graduationDate` IS NOT NULL");
        echo "✅ Set actual graduation dates for graduated students\n";
    } catch (Exception $e) {
        echo "⚠️  Warning: " . $e->getMessage() . "\n";
    }

    echo "Step 6: Dropping old graduationDate column...\n";
    try {
        $pdo->exec("ALTER TABLE `cor4edu_students` DROP COLUMN `graduationDate`");
        echo "✅ Dropped old graduationDate column\n";
    } catch (Exception $e) {
        echo "⚠️  Warning: " . $e->getMessage() . "\n";
    }

    echo "Step 7: Adding indexes...\n";
    try {
        $pdo->exec("ALTER TABLE `cor4edu_students` ADD INDEX `idx_anticipated_graduation` (`anticipatedGraduationDate`)");
        echo "✅ Added index for anticipatedGraduationDate\n";
    } catch (Exception $e) {
        echo "⚠️  Warning: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE `cor4edu_students` ADD INDEX `idx_actual_graduation` (`actualGraduationDate`)");
        echo "✅ Added index for actualGraduationDate\n";
    } catch (Exception $e) {
        echo "⚠️  Warning: " . $e->getMessage() . "\n";
    }

    try {
        $pdo->exec("ALTER TABLE `cor4edu_students` ADD INDEX `idx_withdrawn_date` (`withdrawnDate`)");
        echo "✅ Added index for withdrawnDate\n";
    } catch (Exception $e) {
        echo "⚠️  Warning: " . $e->getMessage() . "\n";
    }

    echo "\n🎉 Migration completed successfully!\n\n";

    // Verify new columns exist
    echo "Verifying new columns...\n";
    $stmt = $pdo->query("DESCRIBE cor4edu_students");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $newColumns = ['anticipatedGraduationDate', 'actualGraduationDate', 'withdrawnDate'];
    foreach ($newColumns as $columnName) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $columnName) {
                echo "✅ Column '{$columnName}' exists\n";
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo "❌ Column '{$columnName}' not found\n";
        }
    }

    echo "\n📊 Current student table structure:\n";
    foreach ($columns as $column) {
        echo "   - {$column['Field']} ({$column['Type']})\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
    exit(1);
}