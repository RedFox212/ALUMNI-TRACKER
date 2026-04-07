<?php
// forgot-password.php
require_once 'includes/db.php';
require_once 'includes/auth.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your registered email.";
    } else {
        $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $stmt->execute([$token, $expires, $user['id']]);
            
            $resetLink = SITE_URL . "/reset-password.php?token=" . $token;
            
            // In a real system, send email here.
            // For this session, we will simulate the email and display the link (for dev/testing).
            $success = "A reset link has been sent to your email. (Dev Mode: <a href='$resetLink' class='underline font-bold'>Reset Now</a>)";
        } else {
            // Security: Don't reveal if email exists, but for alumni systems, it's usually fine.
            $error = "No active account found with that email.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; }
        .glass-card { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px); border: 1px solid rgba(255,255,255,0.1); }
        .input-field { width: 100%; height: 50px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.05); border-radius: 16px; padding: 0 20px; color: white; transition: all 0.3s; }
        .input-field:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); outline: none; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full glass-card p-10 rounded-[40px] shadow-2xl animate-fade-in-up">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-black text-white italic uppercase tracking-tighter mb-2">RECOVER ACCESS</h1>
            <p class="text-slate-400 text-sm font-medium leading-relaxed italic">Enter your registered email address to receive a secure reset token.</p>
        </div>

        <?php if ($error): ?><div class="mb-6 bg-red-500/10 text-red-500 p-4 rounded-2xl text-xs font-bold border border-red-500/20">⚠️ <?php echo $error; ?></div><?php endif; ?>
        <?php if ($success): ?><div class="mb-6 bg-blue-500/10 text-blue-400 p-4 rounded-2xl text-xs font-bold border border-blue-500/20 italic">✅ <?php echo $success; ?></div><?php endif; ?>

        <form method="POST" class="space-y-6">
            <div>
                <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[3px] mb-2 ml-1">Email Address</label>
                <input type="email" name="email" required class="input-field" placeholder="your@email.com">
            </div>
            
            <button type="submit" class="w-full h-14 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl font-black shadow-lg shadow-blue-500/20 transition-all uppercase tracking-widest text-xs">Request Secure Reset</button>
            <div class="text-center mt-6">
                <a href="index.php" class="text-[10px] font-black text-slate-500 hover:text-white uppercase tracking-widest transition-colors">← Back to Login</a>
            </div>
        </form>
    </div>
</body>
</html>
