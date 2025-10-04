<?php
/**
 * Schema Validation Script
 *
 * Prevents schema drift by validating that all required database tables and columns exist
 * before deploying code to Cloud Run.
 *
 * This script addresses Issues #8, #9, #11 - all caused by code expecting tables/columns
 * that didn't exist in Cloud SQL.
 *
 * Usage:
 *   php validate_schema.php                    # Validates against local DB
 *   DB_HOST=34.68.38.84 php validate_schema.php # Validates against Cloud SQL
 *
 * Exit Codes:
 *   0 = All checks passed (safe to deploy)
 *   1 = Schema drift detected (DO NOT DEPLOY)
 *
 * @version 1.0.0
 * @since 2025-10-04
 */

// Load environment variables or use defaults
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'cor4edu_sms';
$dbUser = getenv('DB_USERNAME') ?: 'root';
$dbPass = getenv('DB_PASSWORD') ?: '';

echo "\n";
echo "================================================================================\n";
echo " SCHEMA VALIDATION - COR4EDU SMS\n";
echo "================================================================================\n";
echo "Target: {$dbUser}@{$dbHost}:{$dbPort}/{$dbName}\n";
echo "Date:   " . date('Y-m-d H:i:s') . "\n";
echo "================================================================================\n\n";

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    echo "✅ Database connection successful\n\n";
} catch (PDOException $e) {
    echo "❌ ERROR: Cannot connect to database\n";
    echo "   " . $e->getMessage() . "\n\n";
    exit(1);
}

// Define all required tables (based on actual production system)
$requiredTables = [
    // Core tables
    'cor4edu_students',
    'cor4edu_staff',
    'cor4edu_programs',
    'cor4edu_payments',

    // Permission system (Issue #8 - was missing, caused 500 errors)
    'cor4edu_system_permissions',
    'cor4edu_staff_permissions',
    'cor4edu_staff_role_types',
    'cor4edu_role_permission_defaults',

    // Document system (Issue #11 - was missing, caused student profile 500 errors)
    'cor4edu_document_requirements',
    'cor4edu_student_document_requirements',
    'cor4edu_documents',

    // Faculty/Academic tracking (Issue #11 - was missing)
    'cor4edu_faculty_notes',
    'cor4edu_student_meetings',
    'cor4edu_academic_support_sessions',
    'cor4edu_academic_interventions',

    // Career placement
    'cor4edu_career_placements',

    // Migration tracking (Phase 2.1)
    'cor4edu_schema_migrations',
];

// Critical columns that caused production failures
$criticalColumns = [
    'cor4edu_staff' => [
        'staffID',
        'username',
        'passwordStrong',  // Actual column name (not 'password')
        'passwordStrongSalt',
        'email',
        'firstName',
        'lastName',
        'phone',  // Issue #9 - missing column caused "cannot create staff" error
        'isSuperAdmin',
        'active',
    ],
    'cor4edu_students' => [
        'studentID',
        'firstName',
        'lastName',
        'email',
        'status',
        'dateOfBirth',
    ],
    'cor4edu_programs' => [
        'programID',
        'name',  // Actual column name (not 'programName')
        'active',  // Required for Active/Inactive tabs
    ],
];

$errors = [];
$warnings = [];

// Check 1: Validate all required tables exist
echo "CHECK 1: Required Tables\n";
echo "------------------------\n";

$stmt = $pdo->query("SHOW TABLES");
$existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

$missingTables = array_diff($requiredTables, $existingTables);

if (empty($missingTables)) {
    echo "✅ All " . count($requiredTables) . " required tables exist\n\n";
} else {
    echo "❌ CRITICAL: Missing " . count($missingTables) . " required tables:\n";
    foreach ($missingTables as $table) {
        echo "   - {$table}\n";
        $errors[] = "Missing table: {$table}";
    }
    echo "\n";
}

// Check 2: Validate critical columns exist
echo "CHECK 2: Critical Columns\n";
echo "-------------------------\n";

foreach ($criticalColumns as $table => $columns) {
    if (!in_array($table, $existingTables)) {
        echo "⚠️  Skipping {$table} (table doesn't exist)\n";
        continue;
    }

    $stmt = $pdo->query("DESCRIBE {$table}");
    $tableColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $missingColumns = array_diff($columns, $tableColumns);

    if (empty($missingColumns)) {
        echo "✅ {$table}: All " . count($columns) . " critical columns exist\n";
    } else {
        echo "❌ {$table}: Missing " . count($missingColumns) . " columns:\n";
        foreach ($missingColumns as $column) {
            echo "   - {$column}\n";
            $errors[] = "Missing column: {$table}.{$column}";
        }
    }
}

echo "\n";

// Check 3: Migration tracking operational
echo "CHECK 3: Migration Tracking\n";
echo "---------------------------\n";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM cor4edu_schema_migrations");
    $result = $stmt->fetch();
    $migrationCount = $result['count'];

    if ($migrationCount > 0) {
        echo "✅ Migration tracking active ({$migrationCount} migrations recorded)\n\n";

        // Show recent migrations
        $stmt = $pdo->query("
            SELECT version, description, DATE_FORMAT(appliedAt, '%Y-%m-%d %H:%i') as applied
            FROM cor4edu_schema_migrations
            ORDER BY appliedAt DESC
            LIMIT 5
        ");
        $recentMigrations = $stmt->fetchAll();

        echo "   Recent migrations:\n";
        foreach ($recentMigrations as $migration) {
            echo "   - {$migration['version']}: {$migration['description']} ({$migration['applied']})\n";
        }
        echo "\n";
    } else {
        $warnings[] = "Migration tracking table exists but no migrations recorded";
        echo "⚠️  Migration tracking exists but empty (no migrations recorded)\n\n";
    }
} catch (PDOException $e) {
    $errors[] = "Migration tracking not operational: " . $e->getMessage();
    echo "❌ Migration tracking check failed\n\n";
}

// Check 4: Table counts (sanity check)
echo "CHECK 4: Data Sanity\n";
echo "--------------------\n";

$dataTables = [
    'cor4edu_students' => 'Students',
    'cor4edu_staff' => 'Staff',
    'cor4edu_programs' => 'Programs',
];

foreach ($dataTables as $table => $label) {
    if (!in_array($table, $existingTables)) {
        continue;
    }

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
    $result = $stmt->fetch();
    $count = $result['count'];

    if ($count > 0) {
        echo "✅ {$label}: {$count} records\n";
    } else {
        $warnings[] = "{$table} has zero records";
        echo "⚠️  {$label}: {$count} records (empty table)\n";
    }
}

echo "\n";

// Final Report
echo "================================================================================\n";
echo " VALIDATION SUMMARY\n";
echo "================================================================================\n\n";

if (!empty($errors)) {
    echo "❌ SCHEMA DRIFT DETECTED - DO NOT DEPLOY\n\n";
    echo "Errors:\n";
    foreach ($errors as $i => $error) {
        echo "  " . ($i + 1) . ". {$error}\n";
    }
    echo "\n";
    echo "Action Required:\n";
    echo "1. Apply missing migrations to Cloud SQL\n";
    echo "2. Re-run this validation script\n";
    echo "3. Only deploy when all checks pass\n\n";
    echo "================================================================================\n\n";
    exit(1);
}

if (!empty($warnings)) {
    echo "⚠️  WARNINGS DETECTED (deployment allowed but review recommended)\n\n";
    echo "Warnings:\n";
    foreach ($warnings as $i => $warning) {
        echo "  " . ($i + 1) . ". {$warning}\n";
    }
    echo "\n";
}

echo "✅ ALL CRITICAL CHECKS PASSED - SAFE TO DEPLOY\n\n";
echo "Database Schema Status: ✅ SYNCHRONIZED\n";
echo "Total Tables: " . count($existingTables) . "\n";
echo "Required Tables: " . count($requiredTables) . " / " . count($requiredTables) . " present\n";
echo "Migration Tracking: ✅ Operational\n\n";
echo "================================================================================\n\n";

exit(0);
