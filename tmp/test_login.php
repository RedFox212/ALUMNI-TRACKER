<?php
require_once 'c:\xampp\htdocs\ALUMNI\includes\db.php';
require_once 'c:\xampp\htdocs\ALUMNI\includes\auth.php';

$email = 'admin@lyceum.edu.ph';
$password = 'password';

$result = handleLogin($email, $password, $pdo);
echo "Result: " . json_encode($result) . "\n";

// Check the hash in DB manually
$stmt = $pdo->prepare("SELECT password_hash FROM users WHERE email = ?");
$stmt->execute([$email]);
$hash = $stmt->fetchColumn();
echo "Hash in DB: " . $hash . "\n";
echo "Verify Test: " . (password_verify($password, $hash) ? "MATCH" : "NO MATCH") . "\n";
