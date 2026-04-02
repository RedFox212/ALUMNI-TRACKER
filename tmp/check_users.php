<?php
require_once 'c:\xampp\htdocs\ALUMNI\includes\db.php';
$stmt = $pdo->query("SELECT id, name, email, role, is_active FROM users");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('c:\xampp\htdocs\ALUMNI\tmp\user_dump.json', json_encode($users, JSON_PRETTY_PRINT));
echo "Dumped " . count($users) . " users to user_dump.json\n";
