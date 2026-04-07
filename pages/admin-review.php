<?php
// pages/admin-review.php — Unified Verification & Moderation Center
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireRole('admin');

$success = $error = null;
$tab = $_GET['tab'] ?? 'alumni';

// Handle global decisions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verifyCsrf()) {
        $id = (int)$_POST['id'];
        $decision = $_POST['decision']; // verified/approved or rejected/declined
        $type = $_POST['type'];

        if ($type === 'alumni') {
            if ($decision === 'verified') {
                $stmt = $pdo->prepare("SELECT id, batch_year FROM alumni WHERE id = ?");
                $stmt->execute([$id]);
                $al = $stmt->fetch();
                $id_num = generateAlumniId($al['batch_year'] ?? date('Y'), $al['id']);
                $qr_secret = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("UPDATE alumni SET verification_status = ?, alumni_id_num = ?, id_qr_secret = ? WHERE id = ?");
                $stmt->execute([$decision, $id_num, $qr_secret, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE alumni SET verification_status = ? WHERE id = ?");
                $stmt->execute([$decision, $id]);
            }
            logAudit("ALUMNI_VERIFY", "Identity was " . $decision, "alumni", $id);
            $success = "Alumni identity " . strtoupper($decision) . ".";
        } elseif ($type === 'spotlight') {
            $stmt = $pdo->prepare("UPDATE announcements SET status = ? WHERE id = ?");
            $stmt->execute([$decision, $id]);
            logAudit("SPOTLIGHT_MODERATION", "Post/Spotlight moderated as " . $decision, "announcement", $id);
            $success = "Spotlight/Post moderation " . strtoupper($decision) . ".";
            $success = "Spotlight/Post moderation " . strtoupper($decision) . ".";
        }
    }
}

// Fetch data based on tab
$pending = [];
if ($tab === 'alumni') {
    $pending = $pdo->query("SELECT u.name, a.* FROM users u JOIN alumni a ON u.id = a.user_id WHERE a.verification_status = 'pending' ORDER BY a.created_at ASC")->fetchAll();
} elseif ($tab === 'spotlight') {
    $pending = $pdo->query("SELECT u.name, an.* FROM users u JOIN announcements an ON u.id = an.created_by WHERE an.status = 'pending' ORDER BY an.created_at ASC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Center – Official Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Flash Guard -->
    <script>(function(){const t=localStorage.getItem('lats-theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark');})();</script>
    <style>
        :root { background-color: #f1f5f9; }
        .dark { background-color: #0c111d; }
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 h-screen flex overflow-hidden">
    <?php require_once '../includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col lg:ml-72 h-screen overflow-hidden">
        <?php $topbar_title = 'Review Center'; $topbar_subtitle = 'Global Moderation & Identity Control'; require_once '../includes/topbar.php'; ?>

        <div class="flex-1 flex flex-col h-full min-h-0">
            <div class="flex-1 p-8 max-w-7xl mx-auto w-full flex flex-col min-h-0">
            <!-- Tabs -->
            <div class="flex-shrink-0 flex gap-6 p-2 bg-slate-100 dark:bg-slate-900 rounded-[40px] mb-8 w-fit">
                <a href="?tab=alumni" class="px-8 py-3 rounded-[32px] text-[10px] font-black tracking-[2px] uppercase transition-all <?php echo $tab === 'alumni' ? 'bg-white dark:bg-slate-800 text-blue-600 shadow-xl' : 'text-slate-400 hover:text-slate-600'; ?>">Waitlist</a>
                <a href="?tab=spotlight" class="px-8 py-3 rounded-[32px] text-[10px] font-black tracking-[2px] uppercase transition-all <?php echo $tab === 'spotlight' ? 'bg-white dark:bg-slate-800 text-blue-600 shadow-xl' : 'text-slate-400 hover:text-slate-600'; ?>">Spotlights</a>
                <a href="?tab=logs" class="px-8 py-3 rounded-[32px] text-[10px] font-black tracking-[2px] uppercase transition-all <?php echo $tab === 'logs' ? 'bg-white dark:bg-slate-800 text-blue-600 shadow-xl' : 'text-slate-400 hover:text-slate-600'; ?>">System History</a>
            </div>
<?php 
if ($tab === 'logs') {
    $logs = $pdo->query("SELECT al.*, u.name as admin_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 50")->fetchAll();
}
?>

            <?php if ($success): ?><div class="flex-shrink-0 mb-8 p-4 bg-green-50 text-green-700 rounded-2xl text-sm font-bold border border-green-100">✅ <?php echo $success; ?></div><?php endif; ?>

            <div class="flex-1 overflow-y-auto custom-scrollbar min-h-0">
                <?php if (empty($pending) && $tab !== 'logs'): ?>
                <div class="h-96 flex flex-col items-center justify-center bg-white dark:bg-slate-800 rounded-[48px] border border-slate-100 dark:border-slate-700">
                    <span class="text-6xl mb-6">🏜️</span>
                    <p class="text-slate-400 font-bold italic uppercase tracking-widest">Queue is currently empty.</p>
                </div>
                <?php elseif ($tab === 'logs'): ?>
                <div class="bg-white dark:bg-slate-800 rounded-[40px] shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden mb-8">
                    <table class="w-full text-left">
                        <thead class="bg-slate-50 dark:bg-slate-900/50">
                            <tr>
                                <th class="px-8 py-6 text-[11px] font-black text-slate-400 uppercase tracking-[2px]">Time</th>
                                <th class="px-8 py-6 text-[11px] font-black text-slate-400 uppercase tracking-[2px]">Admin</th>
                                <th class="px-8 py-6 text-[11px] font-black text-slate-400 uppercase tracking-[2px]">Action</th>
                                <th class="px-8 py-6 text-[11px] font-black text-slate-400 uppercase tracking-[2px]">Details</th>
                                <th class="px-8 py-6 text-[11px] font-black text-slate-400 uppercase tracking-[2px]">IP ORIGIN</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-slate-700">
                            <?php foreach($logs as $log): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-900/30 transition-all font-medium">
                                <td class="px-8 py-6 text-xs text-slate-400"><?php echo formatDateTime($log['created_at']); ?></td>
                                <td class="px-8 py-6 text-sm font-black text-slate-800 dark:text-white"><?php echo htmlspecialchars($log['admin_name']); ?></td>
                                <td class="px-8 py-6"><span class="text-[10px] font-black uppercase text-blue-600 bg-blue-50 dark:bg-blue-600/10 rounded-2xl px-4 py-1.5"><?php echo $log['action']; ?></span></td>
                                <td class="px-8 py-6 text-xs text-slate-600 dark:text-gray-400 leading-relaxed max-w-xs"><?php echo htmlspecialchars($log['details']); ?></td>
                                <td class="px-8 py-6 text-[10px] font-black uppercase text-slate-300"><?php echo $log['ip_address']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="grid grid-cols-1 gap-8 mb-8">
                    <?php foreach ($pending as $p): ?>
                    <div class="bg-white dark:bg-slate-800 rounded-3xl p-6 shadow-sm border border-slate-100 dark:border-slate-700 hover:shadow-md transition-all">
                        <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                            <div class="flex items-center gap-5">
                                <?php 
                                $name = $p['name'] ?? ($p['biz_name'] ?? ($p['title'] ?? 'Review Item'));
                                echo renderAvatar($name, 'w-14 h-14 border-2 border-white dark:border-slate-700 shadow-lg rounded-2xl flex-shrink-0'); 
                                ?>
                                <div>
                                    <h3 class="text-lg font-black text-slate-800 dark:text-white uppercase tracking-tight leading-tight"><?php echo htmlspecialchars($name); ?></h3>
                                    <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-wide">Requested by <?php echo htmlspecialchars($p['name'] ?? $p['owner_name'] ?? 'System'); ?></p>
                                    
                                    <?php if ($tab === 'alumni'): ?>
                                    <div class="mt-2 flex gap-3 items-center">
                                        <span class="text-[8px] font-black uppercase text-blue-600 tracking-widest bg-blue-50 dark:bg-blue-600/10 px-2.5 py-0.5 rounded-full">ID: #<?php echo $p['user_id']; ?></span>
                                        <span class="text-[9px] font-black uppercase text-slate-400 tracking-[1.5px]"><?php echo $p['program']; ?> (Batch <?php echo $p['batch_year']; ?>)</span>
                                    </div>
                                    <?php elseif ($tab === 'spotlight'): ?>
                                    <div class="mt-3 p-3 border-l-2 border-blue-500 bg-slate-50 dark:bg-slate-900/50 rounded-r-2xl">
                                        <p class="text-xs text-slate-600 dark:text-gray-300 leading-relaxed italic line-clamp-2"><?php echo nl2br(htmlspecialchars($p['body'])); ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="flex gap-3">
                                <form method="POST" class="flex gap-3">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
                                    <input type="hidden" name="action" value="1">
                                    <input type="hidden" name="type" value="<?php echo substr($tab, 0, -1) == 'busines' ? 'business' : ($tab == 'alumni' ? 'alumni' : ($tab == 'mentor' ? 'mentor' : ($tab == 'jobs' ? 'job' : 'spotlight'))); ?>">
                                    
                                    <?php 
                                        $approve_val = ($tab === 'mentor' || $tab === 'jobs') ? 'approved' : 'verified';
                                        $reject_val = ($tab === 'mentor' || $tab === 'jobs') ? 'declined' : 'rejected';
                                    ?>
                                    <button name="decision" value="<?php echo $approve_val; ?>" class="h-11 px-8 bg-blue-600 text-white rounded-2xl text-[10px] font-black tracking-[1.5px] hover:bg-blue-700 transition-all shadow-lg shadow-blue-100 dark:shadow-none active:scale-95">APPROVE</button>
                                    <button name="decision" value="<?php echo $reject_val; ?>" class="h-11 px-8 bg-rose-50 text-rose-500 dark:bg-rose-900/10 rounded-2xl text-[10px] font-black tracking-[1.5px] hover:bg-rose-500 hover:text-white transition-all active:scale-95">REJECT</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </main>
</body>
</html>
