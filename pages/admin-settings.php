<?php
// pages/admin-settings.php — System Administration
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireRole('admin');

$success = $error = null;

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_sys'])) {
    if (!verifyCsrf()) { $error = "Security mismatch."; }
    else {
        // Capture settings
        $settings = [
            'broadcast_active' => isset($_POST['broadcast_active']),
            'broadcast_msg' => $_POST['broadcast_msg'] ?? '',
            'maintenance_mode' => isset($_POST['maintenance_mode']),
            'auto_verify' => $_POST['auto_verify'] ?? 'Manual Review Only',
            'audit_retention' => (int)($_POST['audit_retention'] ?? 90)
        ];
        
        // Save to file (simplified persistence)
        file_put_contents('../includes/settings_store.json', json_encode($settings));
        $success = "System intelligence updated. Broadcast changes live.";
    }
}

// Load existing settings
$sys = json_decode(@file_get_contents('../includes/settings_store.json'), true) ?: [
    'broadcast_active' => false, 
    'broadcast_msg' => '',
    'maintenance_mode' => false,
    'auto_verify' => 'Manual Review Only',
    'audit_retention' => 90
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body{font-family:'Inter',sans-serif;}
        .glass-card { background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.2); }
        .dark body { background-color: #0f172a; color: #f8fafc; }
        .dark .bg-white { background-color: #1e293b !important; color: #f8fafc !important; }
        .dark .text-slate-800 { color: #f8fafc !important; }
        .dark .border-slate-100 { border-color: #334155 !important; }
    </style>
</head>
<body class="bg-slate-50 transition-colors duration-500">
<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <header class="h-16 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-b border-slate-100 dark:border-slate-800 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3">
            <span class="text-sm font-black uppercase tracking-widest text-slate-400">System Admin</span>
            <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
            <span class="text-xs font-bold text-blue-600">Configuration</span>
        </div>
    </header>

    <div class="p-8 max-w-5xl mx-auto w-full">
        <div class="mb-10 flex justify-between items-end">
            <div>
                <h1 class="text-3xl font-black text-slate-800 dark:text-white italic uppercase tracking-tighter leading-none">SYSTEM CONTROL</h1>
                <p class="text-slate-400 text-sm font-medium mt-2">Executive oversight and operational parameters.</p>
            </div>
            <div class="flex items-center gap-2 px-4 py-2 bg-blue-50 dark:bg-blue-900/20 rounded-2xl border border-blue-100 dark:border-blue-800/50">
                <span class="w-2 h-2 bg-blue-500 rounded-full animate-pulse"></span>
                <span class="text-[10px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest">Master Logic Active</span>
            </div>
        </div>

        <?php if ($success): ?><div class="mb-8 bg-emerald-50 dark:bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 p-6 rounded-[32px] text-sm font-bold border border-emerald-100 dark:border-emerald-500/20 flex items-center gap-3">
            <span class="text-xl">🛡️</span> <?php echo $success; ?>
        </div><?php endif; ?>

        <form method="POST" id="update_sys_form" class="space-y-8">
            <?php echo csrfField(); ?>
            <input type="hidden" name="update_sys" value="1">

            <!-- 1. IDENTITY GOVERNANCE -->
            <div class="bg-white dark:bg-slate-800 rounded-[48px] p-12 shadow-sm border border-slate-100 dark:border-slate-700 group hover:shadow-xl transition-all">
                <div class="flex items-center gap-6 mb-12">
                    <div class="w-16 h-16 bg-blue-600 rounded-[28px] flex items-center justify-center text-white text-2xl shadow-xl shadow-blue-200 dark:shadow-none">🆔</div>
                    <div>
                        <h3 class="text-2xl font-black text-slate-800 dark:text-white uppercase tracking-tighter leading-none italic">Identity Governance</h3>
                        <p class="text-slate-400 text-sm font-medium mt-1">Verification tiers and enrollment constraints.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                    <div>
                        <label class="block text-[10px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-[3px] mb-4 pl-1">Approval Protocol</label>
                        <select class="w-full h-16 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl px-8 outline-none focus:ring-4 focus:ring-blue-100 dark:focus:ring-blue-900/30 transition-all font-black text-slate-800 dark:text-white appearance-none cursor-pointer">
                            <option>Manual Review Only</option>
                            <option>Auto-Verify Academic Email</option>
                            <option>Full Automated Onboarding</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-[3px] mb-4 pl-1">Enrollment Window (Batch)</label>
                        <div class="flex items-center gap-3">
                            <input type="number" value="2000" class="w-full h-16 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl px-8 outline-none focus:ring-4 focus:ring-blue-100 dark:focus:ring-blue-900/30 transition-all font-black text-slate-800 dark:text-white" placeholder="Start">
                            <span class="font-black text-slate-300">—</span>
                            <input type="number" value="2026" class="w-full h-16 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl px-8 outline-none focus:ring-4 focus:ring-blue-100 dark:focus:ring-blue-900/30 transition-all font-black text-slate-800 dark:text-white" placeholder="End">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. GLOBAL BROADCAST ENGINE -->
            <div class="bg-indigo-900 rounded-[48px] p-12 text-white relative overflow-hidden group">
                <div class="absolute -right-20 -top-20 w-64 h-64 bg-indigo-500 rounded-full opacity-10 group-hover:scale-110 transition-transform duration-1000"></div>
                
                <div class="relative z-10 flex items-center justify-between mb-10">
                    <div class="flex items-center gap-6">
                        <div class="w-16 h-16 bg-white/10 backdrop-blur-md rounded-[28px] flex items-center justify-center text-2xl border border-white/10">📢</div>
                        <div>
                            <h3 class="text-2xl font-black text-white uppercase tracking-tighter leading-none italic">Global Broadcast</h3>
                            <p class="text-indigo-300 text-sm font-medium mt-1">Deploy site-wide administrative notices.</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer scale-125">
                        <input type="checkbox" name="broadcast_active" <?php echo $sys['broadcast_active'] ? 'checked' : ''; ?> class="sr-only peer">
                        <div class="w-11 h-6 bg-white/20 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-500"></div>
                    </label>
                </div>

                <div class="relative z-10">
                    <label class="block text-[10px] font-black text-indigo-400 uppercase tracking-[3px] mb-4 pl-1">Broadcast Message</label>
                    <textarea name="broadcast_msg" rows="3" class="w-full bg-white/5 border border-white/10 rounded-[32px] p-8 outline-none focus:border-white/30 focus:bg-white/10 transition-all font-bold text-white placeholder-indigo-700"><?php echo htmlspecialchars($sys['broadcast_msg']); ?></textarea>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- 3. DATA OPERATIONS -->
                <div class="bg-white dark:bg-slate-800 rounded-[48px] p-10 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center gap-4 mb-10">
                        <div class="w-12 h-12 bg-purple-600 rounded-2xl flex items-center justify-center text-white text-xl">💾</div>
                        <h3 class="text-lg font-black text-slate-800 dark:text-white uppercase tracking-tighter italic">Data Operations</h3>
                    </div>
                    <div class="space-y-8">
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-[2px] mb-3">Backup Frequency</label>
                            <select class="w-full h-14 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-2xl px-6 font-bold text-slate-800 dark:text-white appearance-none cursor-pointer">
                                <option>Every 6 Hours</option>
                                <option>Daily (Midnight)</option>
                                <option>Weekly Snapshot</option>
                            </select>
                        </div>
                        <div class="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-900 rounded-2xl border border-slate-100 dark:border-slate-700">
                            <div>
                                <p class="text-[10px] font-black text-slate-800 dark:text-white uppercase">Audit Logs</p>
                                <p class="text-[8px] text-slate-400 font-bold uppercase tracking-widest mt-0.5">Retention Period</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <input type="number" value="90" class="w-16 bg-transparent text-right font-black text-blue-600 outline-none">
                                <span class="text-[10px] font-bold text-slate-400 uppercase">Days</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. NETWORK EXTENSIONS -->
                <div class="bg-slate-900 rounded-[48px] p-10 text-white relative overflow-hidden group">
                    <div class="absolute -bottom-8 -right-8 w-32 h-32 bg-blue-600 rounded-full opacity-[0.05] group-hover:scale-150 transition-transform duration-1000"></div>
                    
                    <div class="flex items-center gap-4 mb-10">
                        <div class="w-12 h-12 bg-blue-500 rounded-2xl flex items-center justify-center text-white text-xl shadow-lg shadow-blue-500/20">🔗</div>
                        <h3 class="text-lg font-black text-blue-400 uppercase tracking-tighter italic">Network Hooks</h3>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[2px] mb-3">Webhook Sync URL</label>
                            <input type="text" placeholder="https://api.external.com/sync" class="w-full h-14 bg-white/5 border border-white/10 rounded-2xl px-6 outline-none focus:border-blue-500 transition-all font-bold text-sm">
                        </div>
                        <div class="pt-2">
                            <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[2px] mb-3">System Secret Key</label>
                            <div class="flex gap-2">
                                <input type="password" value="************************" class="flex-1 h-14 bg-white/5 border border-white/10 rounded-2xl px-6 outline-none font-mono text-xs">
                                <button type="button" class="px-5 bg-white text-slate-900 rounded-2xl text-[10px] font-black hover:bg-blue-500 hover:text-white transition-all uppercase tracking-widest">Gen</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Global Action -->
            <div class="flex flex-col items-center gap-4 pt-8 pb-20">
                <button type="submit" class="h-20 px-24 bg-blue-600 text-white rounded-[32px] font-black shadow-2xl shadow-blue-500/30 hover:bg-blue-700 active:scale-[0.98] transition-all tracking-[4px] uppercase text-sm group">
                    <span class="group-hover:mr-2 transition-all">Apply Global Changes</span>
                    <span class="inline-block group-hover:translate-x-1 transition-transform">⚡</span>
                </button>
                <p class="text-[9px] font-black text-slate-400 uppercase tracking-[3px]">Logic will propagate instantly across all nodes</p>
            </div>
        </form>
    </div>
    </div>
</main>

<script>
    // System settings logic is mostly UI-only for this demo
</script>
</body>
</html>
