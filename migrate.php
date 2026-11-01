<?php
/**
 * Smart Fire and Rescue Response System
 * Database Migration & Setup CLI Script
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

echo "=======================================================\n";
echo "MIGRATING DATABASE: " . DB_NAME . "\n";
echo "=======================================================\n";

$sqlFile = __DIR__ . '/database.sql';
if (!file_exists($sqlFile)) {
    die("Error: database.sql not found at {$sqlFile}\n");
}

$sql = file_get_contents($sqlFile);
$db = getDB();

try {
    $db->exec($sql);
    echo "✅ Database schema migrated and seeded successfully!\n";
    
    // Create pre-hashed passwords for test users if needed
    $users = [
        ['officer', 'password123', 'Captain John Ray', 'officer', 'officer@sudar.gov.in'],
        ['admin', 'admin123', 'Chief Controller Smith', 'admin', 'admin@sudar.gov.in'],
        ['dispatcher', 'dispatcher123', 'CAD Operator Adams', 'dispatcher', 'cad@sudar.gov.in']
    ];
    
    $userStmt = $db->prepare("
        INSERT INTO users (username, password_hash, full_name, role, email)
        VALUES (:u, :p, :n, :r, :e)
        ON DUPLICATE KEY UPDATE password_hash = :p2, full_name = :n2, role = :r2, email = :e2
    ");
    
    foreach ($users as $u) {
        $hash = password_hash($u[1], PASSWORD_DEFAULT);
        $userStmt->execute([
            'u'  => $u[0],
            'p'  => $hash,
            'n'  => $u[2],
            'r'  => $u[3],
            'e'  => $u[4],
            'p2' => $hash,
            'n2' => $u[2],
            'r2' => $u[3],
            'e2' => $u[4]
        ]);
    }
    echo "✅ Test user accounts verified and synced.\n";
    
    // Check tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "📊 Total active tables: " . count($tables) . " (" . implode(', ', $tables) . ")\n";
    
    $unitCount = $db->query("SELECT COUNT(*) FROM response_units")->fetchColumn();
    $roadCount = $db->query("SELECT COUNT(*) FROM roads")->fetchColumn();
    $equipCount = $db->query("SELECT COUNT(*) FROM equipment_types")->fetchColumn();
    echo "🚒 Response Units: {$unitCount} | 🛣️ Roads: {$roadCount} | ⚙️ Equipment Types: {$equipCount}\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
