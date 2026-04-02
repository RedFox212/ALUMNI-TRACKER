<?php
require_once 'includes/db.php';
try {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE email = ?");
    $stmt->execute(['juan@example.com']);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $out = "Alumni Info:\n";
    if ($user) {
        $out .= "Email: juan@example.com\n";
        $p_pwd1 = password_verify('password123', $user['password_hash']);
        $p_pwd2 = password_verify('password', $user['password_hash']);
        $out .= "Tests:\n";
        $out .= "- matches 'password123': " . ($p_pwd1 ? 'YES' : 'NO') . "\n";
        $out .= "- matches 'password': " . ($p_pwd2 ? 'YES' : 'NO') . "\n";
    } else {
        $out .= "User not found!";
    }
    file_put_contents('db_dump.txt', $out);
    echo "Dumped to db_dump.txt";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
