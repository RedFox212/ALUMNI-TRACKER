<?php
// reset-password.php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$error = null;
$success = null;
$token = $_GET['token'] ?? '';

if (empty($token)) {
    header('Location: index.php'); exit;
}

// Verify token
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE reset_token = ? AND reset_expires > NOW() AND is_active = 1");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    die("<div style='background:#fef2f2; color:#ef4444; padding:2rem; font-family:sans-serif; text-align:center;'>This reset link is invalid or has expired. <br><br><a href='forgot-password.php' style='color:#ef4444; font-weight:bold;'>Try again?</a></div>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    
    if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[^\w]/', $password)) {
        $error = "Password must be at least 8 characters and include numbers and special characters.";
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$password_hash, $user['id']]);
        
        $success = "Password successfully reset! You can now <a href='index.php' class='underline font-bold'>Login</a>.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; }
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); }
        .input-field { width: 100%; height: 50px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 0 20px; color: white; transition: all 0.3s; }
        .input-field:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); outline: none; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6 text-white">
    <div class="max-w-md w-full glass-card p-10 rounded-[40px] shadow-2xl animate-fade-in-up">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-white italic uppercase tracking-tighter mb-2">RESTORE SECURE</h1>
            <p class="text-slate-400 text-sm font-medium leading-relaxed italic">Hello, <?php echo htmlspecialchars($user['name']); ?>. Please choose a strong new password.</p>
        </div>

        <?php if ($error): ?><div class="mb-6 bg-red-500/10 text-red-500 p-4 rounded-2xl text-xs font-bold border border-red-500/20 italic">⚠️ <?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="mb-6 bg-emerald-500/10 text-emerald-400 p-4 rounded-2xl text-xs font-bold border border-emerald-500/20 italic">✅ <?php echo $success; ?></div><?php endif; ?>

        <?php if (!$success): ?>
        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[3px] mb-2 ml-1">New Password</label>
                <input type="password" name="password" required class="input-field" placeholder="••••••••">
                <p class="text-[9px] text-slate-500 mt-2 italic">8+ Characters, Numbers, and Symbols.</p>
            </div>
            
            <button type="submit" class="w-full h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-black shadow-lg shadow-blue-500/20 transition-all uppercase tracking-widest text-xs">Authorize Update</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>
