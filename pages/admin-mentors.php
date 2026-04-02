<?php
// pages/admin-mentors.php — Admin: Verify and approve mentorship applications
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireRole('admin');

$success = $error = null;

// Handle decisions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['decide'])) {
    if (verifyCsrf()) {
        $aid = (int)$_POST['alumni_id'];
        $decision = $_POST['decision']; // approved or declined
        $stmt = $pdo->prepare("UPDATE alumni SET mentor_status = ?, is_mentor = ? WHERE id = ?");
        $stmt->execute([$decision, ($decision === 'approved' ? 1 : 0), $aid]);
        $success = "Mentorship request " . strtoupper($decision) . " successfully.";
    }
}

// Fetch pending applications
$pending = $pdo->query("SELECT u.name, a.* 
                        FROM users u 
                        JOIN alumni a ON u.id = a.user_id 
                        WHERE a.mentor_status = 'pending' 
                        ORDER BY a.created_at ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Mentors – Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-slate-50 transition-colors duration-500">
    <?php require_once '../includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col lg:ml-64">
        <?php $topbar_title = 'Mentor Verification'; $topbar_subtitle = 'Maintain Professional Standards'; require_once '../includes/topbar.php'; ?>

        <div class="p-8 max-w-5xl mx-auto w-full">
            <div class="mb-10">
                <h1 class="text-3xl font-black text-slate-800 dark:text-white italic uppercase tracking-tighter">MENTOR APPLICATIONS</h1>
                <p class="text-slate-400 text-sm font-medium italic">Review and verify alumni expertise before they can mentor others.</p>
            </div>

            <?php if ($success): ?><div class="mb-8 p-4 bg-green-50 text-green-700 rounded-2xl text-sm font-bold border border-green-100">✅ <?php echo $success; ?></div><?php endif; ?>

            <?php if (empty($pending)): ?>
            <div class="h-64 flex flex-col items-center justify-center bg-white dark:bg-slate-800 rounded-[40px] border border-slate-100 dark:border-slate-700 shadow-sm">
                <span class="text-4xl mb-4">📜</span>
                <p class="text-slate-400 font-bold italic">No pending applications at the moment.</p>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 gap-6">
                <?php foreach ($pending as $p): ?>
                <div class="bg-white dark:bg-slate-800 rounded-[32px] p-8 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex flex-col md:flex-row justify-between items-start gap-8">
                        <div class="flex gap-6">
                            <?php echo renderAvatar($p['name'], 'w-16 h-16 border-4 border-white dark:border-slate-700 shadow-lg'); ?>
                            <div>
                                <h3 class="text-xl font-black text-slate-800 dark:text-white uppercase tracking-tight"><?php echo htmlspecialchars($p['name']); ?></h3>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    <span class="px-3 py-1 bg-blue-50 text-blue-600 text-[10px] font-black rounded-full uppercase tracking-widest"><?php echo htmlspecialchars($p['program']); ?></span>
                                    <span class="px-3 py-1 bg-slate-100 dark:bg-slate-900 text-slate-400 text-[10px] font-black rounded-full uppercase tracking-widest">BATCH <?php echo $p['batch_year']; ?></span>
                                </div>
                                <p class="text-sm font-bold text-slate-500 mt-4"><?php echo htmlspecialchars($p['job_title'] ?? 'Independent Professional'); ?> at <?php echo htmlspecialchars($p['company'] ?? 'Various'); ?></p>
                            </div>
                        </div>

                        <div class="flex gap-3 shrink-0">
                            <form method="POST">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="alumni_id" value="<?php echo $p['id']; ?>">
                                <input type="hidden" name="decide" value="1">
                                <button name="decision" value="approved" class="px-6 py-3 bg-blue-600 text-white rounded-2xl text-[10px] font-black tracking-widest hover:bg-blue-700 transition-all shadow-lg shadow-blue-200">APPROVE</button>
                                <button name="decision" value="declined" class="px-6 py-3 bg-rose-50 text-rose-500 rounded-2xl text-[10px] font-black tracking-widest hover:bg-rose-500 hover:text-white transition-all ml-2">DECLINE</button>
                            </form>
                        </div>
                    </div>
                    
                    <div class="mt-8 pt-8 border-t border-slate-50 dark:border-slate-700">
                        <p class="text-[10px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-widest mb-3">Professional Statement / Proof</p>
                        <div class="bg-slate-50 dark:bg-slate-900/50 p-6 rounded-2xl italic text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            "<?php echo nl2br(htmlspecialchars($p['mentor_bio'])); ?>"
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
