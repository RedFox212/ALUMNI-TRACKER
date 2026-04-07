<?php
// verify_db.php
require_once 'includes/db.php';

echo "Database: " . DB_NAME . "\n";

$tables = ['users', 'alumni', 'batch_officers', 'jobs', 'businesses', 'event_rsvps'];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("DESCRIBE $table");
        echo "[OK] Table '$table' exists.\n";
        $columns = $stmt->fetchAll();
        foreach ($columns as $col) {
            echo "   - " . $col['Field'] . " (" . $col['Type'] . ")\n";
        }
    } catch (Exception $e) {
        echo "[ERROR] Table '$table' missing: " . $e->getMessage() . "\n";
    }
}

// Check for users
$stmt = $pdo->query("SELECT COUNT(*) FROM users");
echo "Total users: " . $stmt->fetchColumn() . "\n";
?>
