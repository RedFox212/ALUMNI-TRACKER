<?php
// tmp/fix_db.php
require_once '../includes/db.php';
try {
    echo "Attempting to add column 'category' to 'announcements' table...\n";
    $pdo->exec("ALTER TABLE announcements ADD COLUMN category VARCHAR(50) DEFAULT 'General' AFTER content");
    echo "Success!\n";
} catch (PDOException $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
?>
