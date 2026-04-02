<?php
// pages/verify-id.php — Public: Independent Institutional ID Verification
require_once '../includes/db.php';
require_once '../includes/helpers.php';

$alumni_id = $_GET['id'] ?? '';
$alumni = null;
$error = null;

if (!$alumni_id) {
    $error = "No ID presented for institutional verification.";
} else {
    $stmt = $pdo->prepare("SELECT u.name, a.* FROM users u JOIN alumni a ON u.id = a.user_id WHERE a.alumni_id_num = ? AND a.verification_status = 'verified'");
    $stmt->execute([$alumni_id]);
    $alumni = $stmt->fetch();
    
    if (!$alumni) {
        $error = "Invalid credential. This ID number is not found in the institutional registry.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Institutional Verification – Lyceum</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-[#0f172a] min-h-screen flex items-center justify-center p-6 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-blue-900/20 via-[#0f172a] to-[#0f172a]">

    <div class="max-w-md w-full">
        <!-- Logo -->
        <div class="flex justify-center mb-12">
            <div class="w-24 h-24 bg-white rounded-3xl flex items-center justify-center p-4 shadow-2xl shadow-blue-500/20">
                <img src="../loalogo.png" alt="Logo" class="w-full h-full object-contain">
            </div>
        </div>

        <div class="bg-white/5 backdrop-blur-2xl rounded-[48px] p-10 border border-white/10 shadow-2xl relative overflow-hidden">
            <!-- Decorative Blobs -->
            <div class="absolute -top-20 -right-20 w-40 h-40 bg-blue-600/20 rounded-full blur-3xl"></div>
            
            <?php if ($error): ?>
                <div class="text-center relative z-10">
                    <div class="text-6xl mb-8">🚫</div>
                    <h1 class="text-white text-3xl font-black uppercase tracking-tighter italic mb-4">Access Denied</h1>
                    <p class="text-slate-400 text-sm font-medium leading-relaxed italic mb-8"><?php echo $error; ?></p>
                    <a href="../index.php" class="inline-flex h-12 px-8 bg-white/5 text-white text-[10px] font-black uppercase tracking-widest rounded-2xl border border-white/10 hover:bg-white/10 transition-all">Identity Hub</a>
                </div>
            <?php else: ?>
                <div class="text-center relative z-10">
                    <div class="inline-flex h-10 px-4 items-center bg-green-500/10 text-green-400 text-[10px] font-black uppercase tracking-widest rounded-full border border-green-500/20 mb-8">
                        ✅ Authentically Verified
                    </div>
                    
                    <div class="w-32 h-32 rounded-full border-4 border-white/10 mx-auto mb-6 shadow-2xl overflow-hidden ring-4 ring-green-500/20">
                        <?php echo renderAvatar($alumni['name'], 'w-full h-full text-3xl font-black bg-white text-slate-900'); ?>
                    </div>
                    
                    <h2 class="text-white text-3xl font-black uppercase tracking-tighter italic mb-1 lowercase first-letter:uppercase"><?php echo htmlspecialchars($alumni['name']); ?></h2>
                    <p class="text-blue-500 font-bold text-xs uppercase tracking-[2px] mb-8 italic"><?php echo htmlspecialchars($alumni['program']); ?></p>
                    
                    <div class="grid grid-cols-2 gap-4 text-left border-t border-white/5 pt-8">
                        <div>
                            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 italic">Issued ID</p>
                            <p class="text-sm font-black text-white tracking-widest italic"><?php echo $alumni['alumni_id_num']; ?></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-black text-slate-500 uppercase tracking-widest mb-1 italic">Class Year</p>
                            <p class="text-sm font-black text-white tracking-widest italic"><?php echo $alumni['batch_year']; ?></p>
                        </div>
                    </div>

                    <div class="mt-12 text-[10px] font-black text-slate-400 uppercase tracking-widest opacity-60 italic">
                        Verified by Institutional Registry
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <p class="text-center text-slate-600 text-[10px] font-black uppercase tracking-[5px] mt-12 italic">Lyceum of Alabang Alumni Tracking System</p>
    </div>

</body>
</html>
