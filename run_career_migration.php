<?php
/**
 * Career Placements Migration Runner
 * Updates the career placements table to include not_graduated status
 */

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/config/database.php';

use Cor4Edu\Config\Database;

echo "COR4EDU SMS Career Placements Migration\n";
echo "======================================\n\n";

try {
    // Test database connection
    echo "Testing database connection...\n";
    $pdo = Database::getInstance();
    echo "✅ Database connection successful!\n\n";

    // Check if career placements table exists
    echo "Checking career placements table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'cor4edu_career_placements'");
    if ($stmt->rowCount() == 0) {
        echo "❌ Career placements table does not exist. Please run the initial career placements migration first.\n";
        exit(1);
    }
    echo "✅ Career placements table exists\n\n";

    // Update employmentStatus ENUM to include not_graduated
    echo "Updating employmentStatus ENUM to include 'not_graduated'...\n";
    $sql = "ALTER TABLE `cor4edu_career_placements`
            MODIFY COLUMN `employmentStatus` ENUM(
                'not_graduated',
                'employed_related',
                'employed_unrelated',
                'self_employed_related',
                'self_employed_unrelated',
                'not_employed_seeking',
                'not_employed_not_seeking',
                'continuing_education'
            ) NOT NULL COMMENT 'Employment status including not_graduated for students who have not yet graduated'";

    $pdo->exec($sql);
    echo "✅ Updated employmentStatus ENUM successfully\n\n";

    // Update table comment
    echo "Updating table comment...\n";
    $sql = "ALTER TABLE `cor4edu_career_placements`
            COMMENT = 'Comprehensive career placement tracking table for compliance reporting. Tracks final employment outcomes, verification, and licensure requirements. Includes not_graduated status for students who have not yet graduated.'";

    $pdo->exec($sql);
    echo "✅ Updated table comment successfully\n\n";

    // Verify the changes
    echo "Verifying changes...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM cor4edu_career_placements LIKE 'employmentStatus'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($column && strpos($column['Type'], 'not_graduated') !== false) {
        echo "✅ Employment status ENUM now includes 'not_graduated'\n";
    } else {
        echo "❌ Failed to verify employment status update\n";
    }

    echo "\n🎉 Career placements migration completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
    exit(1);
}