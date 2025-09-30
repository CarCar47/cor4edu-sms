<?php
/**
 * Clean Database Script
 * Drops all tables to start fresh
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/database.php';

use Cor4Edu\Config\Database;

echo "COR4EDU SMS Database Cleanup\n";
echo "============================\n\n";

try {
    $pdo = Database::getInstance();
    echo "✅ Database connection successful!\n\n";

    // Disable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // Get all tables in the database
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($tables) > 0) {
        echo "Dropping existing tables...\n";
        foreach ($tables as $table) {
            echo "  - Dropping {$table}\n";
            $pdo->exec("DROP TABLE IF EXISTS `{$table}`");
        }
        echo "✅ All tables dropped successfully!\n\n";
    } else {
        echo "No tables found to drop.\n\n";
    }

    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    echo "🎉 Database cleaned successfully!\n";
    echo "You can now run the setup script again.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}