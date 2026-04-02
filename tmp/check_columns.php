<?php
// tmp/check_columns.php
require_once '../includes/db.php';
try {
    $stmt = $pdo->query("DESCRIBE announcements");
    $columns = $stmt->fetchAll();
    echo "Columns in 'announcements':\n";
    foreach($columns as $col) {
        echo "- " . $col['Field'] . "\n";
    }
} catch (PDOException $e) {
    echo "Fail: " . $e->getMessage() . "\n";
}
?>
