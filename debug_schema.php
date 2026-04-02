<?php
require_once 'includes/db.php';
try {
    $res = $pdo->query("DESCRIBE batch_officers");
    foreach ($res->fetchAll() as $row) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
