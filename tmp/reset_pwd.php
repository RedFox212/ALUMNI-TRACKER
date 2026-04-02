<?php
require_once 'c:\xampp\htdocs\ALUMNI\includes\db.php';
$stmt = $pdo->query("SELECT email FROM users");
$emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
$hash = password_hash('password', PASSWORD_DEFAULT);
$update = $pdo->prepare("UPDATE users SET password_hash = ?, is_active = 1");
$update->execute([$hash]);
foreach ($emails as $email) {
    echo "Reset $email to 'password'\n";
}
