<?php
/**
 * COR4EDU SMS Database Migration Runner
 *
 * SECURITY: This script should only be accessible to SuperAdmin
 * Access via: https://your-cloud-run-url.run.app/run_migration.php
 *
 * This will add missing permission tables to the Cloud SQL database
 */

// Start session
session_start();

// Bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Security check - must be logged in as SuperAdmin
if (!isset($_SESSION['cor4edu']) || !$_SESSION['cor4edu']['is_super_admin']) {
    die('⛔ Access Denied: SuperAdmin access required');
}

// Get password confirmation from query string (for extra security)
$confirm = $_GET['confirm'] ?? '';
if ($confirm !== 'YES_RUN_MIGRATION') {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Migration - COR4EDU SMS</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
            .warning { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 5px; }
            .button { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; }
            .button:hover { background: #0056b3; }
            pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        </style>
    </head>
    <body>
        <h1>🔧 Database Migration Runner</h1>

        <div class="warning">
            <strong>⚠️ WARNING:</strong> This will modify your database structure by adding missing permission tables.
            <br><br>
            <strong>What this migration does:</strong>
            <ul>
                <li>Adds <code>cor4edu_staff_role_types</code> table (if missing)</li>
                <li>Adds <code>cor4edu_system_permissions</code> table (if missing)</li>
                <li>Adds <code>cor4edu_role_permission_defaults</code> table (if missing)</li>
                <li>Populates default role types (Admissions, Bursar, Registrar, etc.)</li>
                <li>Populates system permissions registry</li>
                <li>Updates foreign key constraints</li>
            </ul>
            <br>
            <strong>This migration is SAFE to run multiple times</strong> - it uses <code>CREATE TABLE IF NOT EXISTS</code> and <code>INSERT ... ON DUPLICATE KEY UPDATE</code>.
        </div>

        <p>
            <a href="?confirm=YES_RUN_MIGRATION" class="button">✅ Run Migration Now</a>
            &nbsp;&nbsp;
            <a href="../index.php?q=/dashboard">← Back to Dashboard</a>
        </p>
    </body>
    </html>
    <?php
    exit;
}

// Run the migration
echo "<!DOCTYPE html><html><head><title>Migration Running...</title>";
echo "<style>body { font-family: monospace; padding: 20px; } .success { color: green; } .error { color: red; }</style>";
echo "</head><body>";
echo "<h1>Database Migration in Progress...</h1><pre>\n";

try {
    $pdo = $container['db'];

    echo "✅ Connected to database\n\n";

    // Read the migration SQL file
    $migrationFile = __DIR__ . '/../database_complete_schema.sql';

    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: {$migrationFile}");
    }

    echo "📄 Reading migration file...\n";
    $sql = file_get_contents($migrationFile);

    if ($sql === false) {
        throw new Exception("Could not read migration file");
    }

    echo "✅ Migration file loaded (" . number_format(strlen($sql)) . " bytes)\n\n";

    // Split SQL into individual statements and execute
    echo "🔧 Executing migration statements...\n";
    echo "=====================================\n\n";

    // Execute the entire SQL at once (since it uses transactions)
    $pdo->exec($sql);

    echo "\n✅ Migration completed successfully!\n\n";

    // Verify the tables were created
    echo "🔍 Verifying tables...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'cor4edu_%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "📊 Total tables: " . count($tables) . "\n\n";

    $criticalTables = [
        'cor4edu_staff_role_types',
        'cor4edu_system_permissions',
        'cor4edu_role_permission_defaults'
    ];

    echo "Checking critical permission tables:\n";
    foreach ($criticalTables as $table) {
        $exists = in_array($table, $tables);
        echo ($exists ? '✅' : '❌') . " {$table}\n";
    }

    echo "\n";

    // Count permissions
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cor4edu_system_permissions");
        $count = $stmt->fetch()['count'];
        echo "📝 System permissions registered: {$count}\n";
    } catch (Exception $e) {
        echo "⚠️  Could not count permissions (table may not exist yet)\n";
    }

    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cor4edu_staff_role_types");
        $count = $stmt->fetch()['count'];
        echo "👥 Role types created: {$count}\n";
    } catch (Exception $e) {
        echo "⚠️  Could not count role types (table may not exist yet)\n";
    }

    echo "\n";
    echo "<span class='success'>✅ MIGRATION COMPLETE!</span>\n\n";
    echo "You can now:\n";
    echo "1. Return to the dashboard and verify tabs appear correctly\n";
    echo "2. Check the Permissions tab to manage permissions\n";
    echo "3. Verify the Staff tab is visible\n\n";

    echo "<a href='../index.php?q=/dashboard'>← Return to Dashboard</a>\n";

} catch (PDOException $e) {
    echo "\n<span class='error'>❌ Migration failed!</span>\n\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "SQL Error Code: " . $e->getCode() . "\n\n";

    if (isset($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
        echo "Stack trace:\n";
        echo $e->getTraceAsString() . "\n";
    }
} catch (Exception $e) {
    echo "\n<span class='error'>❌ Error!</span>\n\n";
    echo $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='../index.php?q=/dashboard'>← Return to Dashboard</a></p>";
echo "</body></html>";
