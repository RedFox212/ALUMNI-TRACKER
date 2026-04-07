<?php
// ajax/chat_handler.php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'alumni';

// Auto-init Table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        is_read TINYINT(1) DEFAULT 0
    )");
} catch(Exception $e) {}

$action = $_GET['action'] ?? '';

if ($action === 'send') {
    $msg = trim($_POST['message'] ?? '');
    $to  = (int)($_POST['receiver_id'] ?? 1); // Default to first admin if not specified

    if ($msg) {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $to, $msg]);
        echo json_encode(['status' => 'success']);
    }
    exit;
}

if ($action === 'messages') {
    $chat_with = (int)($_GET['with'] ?? ($user_role === 'admin' ? 0 : 1));
    
    // Mark as Read
    $pdo->prepare("UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?")
        ->execute([$chat_with, $user_id]);

    if ($user_role === 'admin') {
        // Admin chatting with a specific user
        $stmt = $pdo->prepare("SELECT * FROM chat_messages 
                               WHERE (sender_id = ? AND receiver_id = ?) 
                                  OR (sender_id = ? AND receiver_id = ?) 
                               ORDER BY created_at ASC");
        $stmt->execute([$user_id, $chat_with, $chat_with, $user_id]);
    } else {
        // Alumni chatting with 'Admins' (any admin)
        // Mark ANY admin message to this user as read
        $pdo->prepare("UPDATE chat_messages m JOIN users u ON m.sender_id = u.id SET m.is_read = 1 
                       WHERE u.role = 'admin' AND m.receiver_id = ?")
           ->execute([$user_id]);

        $stmt = $pdo->prepare("SELECT m.*, u.role as sender_role FROM chat_messages m 
                               JOIN users u ON m.sender_id = u.id
                               WHERE (m.sender_id = ? AND u.role = 'alumni')
                                  OR (m.receiver_id = ? AND u.role = 'admin')
                               ORDER BY m.created_at ASC");
        $stmt->execute([$user_id, $user_id]);
    }
    
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($messages);
    exit;
}

if ($action === 'conversations' && $user_role === 'admin') {
    // List unique alumni who sent messages
    $stmt = $pdo->prepare("SELECT DISTINCT u.id, u.name, 
                           (SELECT message FROM chat_messages WHERE (sender_id = u.id OR receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_msg,
                           (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count
                           FROM users u 
                           JOIN chat_messages m ON (m.sender_id = u.id OR m.receiver_id = u.id)
                           WHERE u.role = 'alumni'
                           ORDER BY (SELECT MAX(created_at) FROM chat_messages WHERE sender_id = u.id OR receiver_id = u.id) DESC");
    $stmt->execute([$user_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

if ($action === 'clear') {
    $with = (int)($_GET['with'] ?? 0);
    if ($with) {
        $pdo->prepare("DELETE FROM chat_messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)")
           ->execute([$user_id, $with, $with, $user_id]);
        echo json_encode(['status' => 'cleared']);
    }
    exit;
}

if ($action === 'unread_total') {
    echo $pdo->query("SELECT COUNT(DISTINCT sender_id) FROM chat_messages WHERE receiver_id = $user_id AND is_read = 0")->fetchColumn();
    exit;
}
