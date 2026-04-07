<?php
// pages/admin-id-manager.php — Admin: Verify Alumni Identity & Issue IDs
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: dashboard.php'); exit; }

$success = $error = null;

// Handle ID Generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_id'])) {
    if (!verifyCsrf()) { $error = "Security mismatch."; }
    else {
        $target_user_id = (int)$_POST['user_id'];
        
        try {
            // Check if already has ID
            $check = $pdo->prepare("SELECT alumni_id_num FROM alumni WHERE user_id = ?");
            $check->execute([$target_user_id]);
            $existing = $check->fetchColumn();
            
            if ($existing) {
                $error = "This alumnus already has an ID: " . $existing;
            } else {
                $year = date('Y');
                // Get next sequential number for this year
                $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(alumni_id_num, '-', -1) AS UNSIGNED)) FROM alumni WHERE alumni_id_num LIKE ?");
                $stmt->execute(["LOA-$year-%"]);
                $max = (int)$stmt->fetchColumn();
                $next = $max + 1;
                $new_id = "LOA-$year-" . str_pad($next, 5, '0', STR_PAD_LEFT);
                
                // Update ONLY if it's currently NULL and use LIMIT 1 for safety
                $pdo->prepare("UPDATE alumni SET alumni_id_num = ?, verification_status = 'verified' WHERE user_id = ? AND (alumni_id_num IS NULL OR alumni_id_num = '') LIMIT 1")
                    ->execute([$new_id, $target_user_id]);
                
                // Notify user
                $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'system', ?)")
                    ->execute([$target_user_id, "Your Digital Alumni ID ($new_id) has been issued!"]);
                
                $success = "ID Generated: $new_id";
            }
        } catch(Exception $e) { $error = "System error: " . $e->getMessage(); }
    }
}

// Fetch Pending Alumni (those without IDs)
$pending = $pdo->query("SELECT u.id, u.name, u.email, a.batch_year, a.program, a.verification_status 
                        FROM users u JOIN alumni a ON u.id = a.user_id 
                        WHERE a.alumni_id_num IS NULL OR a.alumni_id_num = '' 
                        ORDER BY u.created_at DESC")->fetchAll();

// Fetch Recently Verified
$verified = $pdo->query("SELECT u.id, u.name, a.alumni_id_num, a.batch_year, a.program 
                         FROM users u JOIN alumni a ON u.id = a.user_id 
                         WHERE a.alumni_id_num IS NOT NULL AND a.alumni_id_num != '' 
                         ORDER BY a.alumni_id_num DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity Manager – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
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
    <?php 
        $topbar_title = 'Identity Manager';
        $topbar_subtitle = 'Institutional Credential Governance';
        require_once '../includes/topbar.php'; 
    ?>

    <div class="flex-1 flex flex-col h-full min-h-0">
        <div class="flex-1 p-8 max-w-7xl mx-auto w-full flex flex-col min-h-0">
        <div class="mb-10">
            <h1 class="text-3xl font-black text-slate-800 italic uppercase tracking-tighter">ALUMNI VERIFICATION</h1>
            <p class="text-slate-400 text-sm font-medium">Approve alumni identity and issue unique digital ID numbers.</p>
        </div>

        <?php if ($success): ?><div class="mb-6 bg-blue-50 text-blue-700 p-4 rounded-2xl text-sm font-bold border border-blue-100 italic">✅ <?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-6 bg-red-50 text-red-600 p-4 rounded-2xl text-sm font-bold border border-red-100">⚠️ <?php echo $error; ?></div><?php endif; ?>

        <div class="flex-1 grid grid-cols-1 lg:grid-cols-3 gap-8 min-h-0">
            
            <!-- Pending Verification -->
            <div class="lg:col-span-2 flex flex-col min-h-0">
                <h2 class="flex-shrink-0 text-[10px] font-black text-slate-400 uppercase tracking-[3px] mb-4">Pending Issuance (<?php echo count($pending); ?>)</h2>
                
                <?php if (empty($pending)): ?>
                    <div class="bg-white rounded-[40px] p-12 text-center border-2 border-dashed border-slate-200">
                        <div class="text-5xl mb-4">🛡️</div>
                        <h3 class="text-xl font-bold text-slate-800 tracking-tight">Queue and Identity Clear</h3>
                        <p class="text-slate-400 text-sm">All registered alumni have been issued their digital credentials.</p>
                    </div>
                <?php else: ?>
                    <div class="flex-1 bg-white rounded-[32px] overflow-y-auto custom-scrollbar shadow-sm border border-slate-100">
                        <table class="w-full text-left">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="py-4 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Alumnus</th>
                                    <th class="py-4 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Details</th>
                                    <th class="py-4 px-6 text-right text-[10px] font-black text-slate-400 uppercase tracking-widest">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-50">
                                <?php foreach ($pending as $p): ?>
                                <tr class="hover:bg-slate-50 transition-colors">
                                    <td class="py-5 px-6">
                                        <div class="flex items-center gap-3">
                                            <?php echo renderAvatar($p['name'], 'w-10 h-10'); ?>
                                            <div>
                                                <p class="text-sm font-black text-slate-800"><?php echo htmlspecialchars($p['name']); ?></p>
                                                <p class="text-[10px] text-slate-400 font-bold"><?php echo htmlspecialchars($p['email']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-5 px-6 italic">
                                        <p class="text-xs font-black text-blue-600"><?php echo $p['program']; ?></p>
                                        <p class="text-[10px] text-slate-400 font-bold tracking-widest uppercase">Class of <?php echo $p['batch_year']; ?></p>
                                    </td>
                                    <td class="py-5 px-6 text-right">
                                        <form method="POST" onsubmit="return confirm('Verify this alumna/us and issue an ID number?');">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                                            <button type="submit" name="generate_id" class="h-9 px-6 bg-slate-900 text-white text-[10px] font-black rounded-xl hover:bg-blue-600 transition-all uppercase tracking-widest">Issue ID</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Recent Activity / Verification Stats -->
            <div class="flex flex-col min-h-0">
                <h2 class="flex-shrink-0 text-[10px] font-black text-slate-400 uppercase tracking-[3px] mb-4">Recent Verified</h2>
                <div class="flex-1 bg-white rounded-[32px] p-8 shadow-sm border border-slate-100 space-y-6 overflow-y-auto custom-scrollbar">
                    <?php foreach ($verified as $v): ?>
                    <div class="flex items-center justify-between group">
                        <div class="flex items-center gap-3">
                            <div class="w-2 h-2 bg-green-500 rounded-full group-hover:scale-150 transition-transform"></div>
                            <div>
                                <p class="text-xs font-black text-slate-800"><?php echo htmlspecialchars($v['name']); ?></p>
                                <p class="text-[10px] font-bold text-blue-600 tracking-widest"><?php echo $v['alumni_id_num']; ?></p>
                            </div>
                        </div>
                        <span class="text-[9px] font-black text-slate-300 uppercase tracking-widest"><?php echo $v['batch_year']; ?></span>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($verified)): ?>
                        <p class="text-center text-xs text-slate-300 italic">No IDs issued yet.</p>
                    <?php endif; ?>
                </div>
                
            </div>

            </div>
        </div>
    </div>
</main>
</body>
</html>
