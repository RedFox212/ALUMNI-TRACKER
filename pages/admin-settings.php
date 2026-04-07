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
        // In a real app, you'd store these in a 'settings' table or config file.
        // For this demo, we'll simulate a successful update.
        $success = "System settings updated successfully!";
    }
}
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

    <div class="p-8 max-w-4xl mx-auto w-full">
        <div class="mb-10">
            <h1 class="text-3xl font-black text-slate-800 dark:text-white italic uppercase tracking-tighter">CONTROL PANEL</h1>
            <p class="text-slate-400 text-sm font-medium">Manage system-wide parameters, maintenance mode, and security policies.</p>
        </div>

        <?php if ($success): ?><div class="mb-8 bg-green-50 dark:bg-green-500/10 text-green-700 dark:text-green-400 p-6 rounded-3xl text-sm font-bold border border-green-100 dark:border-green-500/20">✅ <?php echo $success; ?></div><?php endif; ?>

        <div class="space-y-8">
            <!-- General Settings -->
            <form method="POST" class="bg-white dark:bg-slate-800 rounded-[48px] p-12 shadow-sm border border-slate-100 dark:border-slate-700 group hover:shadow-xl transition-all">
                <?php echo csrfField(); ?>
                <input type="hidden" name="update_sys" value="1">
                
                <div class="flex items-center gap-6 mb-12">
                    <div class="w-16 h-16 bg-blue-600 rounded-[28px] flex items-center justify-center text-white text-2xl shadow-xl shadow-blue-200 dark:shadow-none">🏠</div>
                    <div>
                        <h3 class="text-2xl font-black text-slate-800 dark:text-white uppercase tracking-tighter leading-none italic">General Identity</h3>
                        <p class="text-slate-400 text-sm font-medium mt-1">Core system branding information.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                    <div>
                        <label class="block text-[11px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-[3px] mb-4 pl-1">System Title</label>
                        <input type="text" value="Lyceum of Alabang Alumni System" class="w-full h-16 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl px-8 outline-none focus:ring-4 focus:ring-blue-100 dark:focus:ring-blue-900/30 transition-all font-black text-slate-800 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-[3px] mb-4 pl-1">Admin Email Notice</label>
                        <input type="email" value="admin@lyceum.edu.ph" class="w-full h-16 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl px-8 outline-none focus:ring-4 focus:ring-blue-100 dark:focus:ring-blue-900/30 transition-all font-black text-slate-800 dark:text-white">
                    </div>
                </div>

                <div class="mt-10">
                    <label class="block text-[11px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-[3px] mb-4 pl-1">System Logo (URL)</label>
                    <input type="text" value="loalogo.png" class="w-full h-16 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-3xl px-8 outline-none focus:ring-4 focus:ring-blue-100 dark:focus:ring-blue-900/30 transition-all font-black text-slate-800 dark:text-white">
                </div>
            </form>

            <!-- Module Controls -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Mentorship Controls -->
                <div class="bg-white dark:bg-slate-800 rounded-[48px] p-8 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-10 h-10 bg-amber-500 rounded-2xl flex items-center justify-center text-white text-lg shadow-lg shadow-amber-200 dark:shadow-none">🤝</div>
                        <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tighter">Mentorship Policy</h3>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 dark:text-slate-500 uppercase tracking-[2px] mb-2">Exp. Threshold (Years)</label>
                            <input type="number" value="3" class="w-full h-12 bg-slate-50 dark:bg-slate-900 border border-slate-100 dark:border-slate-700 rounded-xl px-4 font-bold text-slate-800 dark:text-white">
                        </div>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-xs font-bold text-slate-500 group-hover:text-blue-600 transition-colors">Manual Verification Required</span>
                            <input type="checkbox" checked class="w-5 h-5 rounded border-slate-300 text-blue-600">
                        </label>
                    </div>
                </div>

                <!-- Job Board Controls -->
                <div class="bg-white dark:bg-slate-800 rounded-[48px] p-8 shadow-sm border border-slate-100 dark:border-slate-700">
                    <div class="flex items-center gap-4 mb-8">
                        <div class="w-10 h-10 bg-emerald-500 rounded-2xl flex items-center justify-center text-white text-lg shadow-lg shadow-emerald-200 dark:shadow-none">💼</div>
                        <h3 class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tighter">Career Portal</h3>
                    </div>
                    <div class="space-y-6">
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-xs font-bold text-slate-500 group-hover:text-blue-600 transition-colors">Alumni Job Posting</span>
                            <input type="checkbox" checked class="w-5 h-5 rounded border-slate-300 text-blue-600">
                        </label>
                        <label class="flex items-center justify-between cursor-pointer group">
                            <span class="text-xs font-bold text-slate-500 group-hover:text-blue-600 transition-colors">Admin Approval for Jobs</span>
                            <input type="checkbox" class="w-5 h-5 rounded border-slate-300 text-blue-600">
                        </label>
                    </div>
                </div>
            </div>

            <!-- Maintenance Mode -->
            <div class="bg-amber-50 dark:bg-amber-900/20 rounded-[48px] p-12 border border-amber-200 dark:border-amber-900/30 transition-all hover:shadow-xl hover:shadow-amber-200/20">
                <div class="flex items-center justify-between gap-12">
                    <div class="flex items-center gap-8">
                        <div class="w-20 h-20 bg-amber-500 rounded-[32px] flex items-center justify-center text-4xl shadow-xl shadow-amber-200 dark:shadow-none transition-transform hover:rotate-12">⏳</div>
                        <div>
                            <h3 class="text-2xl font-black text-slate-800 dark:text-white uppercase tracking-tighter leading-none italic">Maintenance Mode</h3>
                            <p class="text-amber-700/60 dark:text-amber-500/60 text-sm mt-2 font-black italic tracking-tight">Only administrators can access the system when active.</p>
                        </div>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer scale-150">
                        <input type="checkbox" class="sr-only peer">
                        <div class="w-14 h-8 bg-slate-300 dark:bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[4px] after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-amber-600"></div>
                    </label>
                </div>
            </div>

            <!-- Advanced Configuration (SMTP & Analytics) -->
            <div class="bg-slate-900 rounded-[48px] p-10 text-white group">
                <div class="flex items-center gap-4 mb-10">
                    <div class="w-12 h-12 bg-indigo-500 rounded-2xl flex items-center justify-center text-white text-xl">🛠️</div>
                    <div>
                        <h3 class="text-xl font-black text-blue-400 uppercase tracking-tighter leading-none italic font-black">Technical Stack</h3>
                        <p class="text-slate-400 text-xs font-medium mt-1">SMTP, API keys, and external integrations.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[3px] mb-3">SMTP Host</label>
                        <input type="text" placeholder="smtp.gmail.com" class="w-full h-12 bg-white/5 border border-white/10 rounded-xl px-4 outline-none focus:border-blue-500 transition-all font-bold text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-500 uppercase tracking-[3px] mb-3">Tracking ID (GA4)</label>
                        <input type="text" placeholder="G-XXXXXXXXXX" class="w-full h-12 bg-white/5 border border-white/10 rounded-xl px-4 outline-none focus:border-blue-500 transition-all font-bold text-sm">
                    </div>
                </div>
                <div class="mt-10 flex justify-end">
                    <button class="px-8 py-3 bg-white text-slate-900 rounded-2xl text-[10px] font-black tracking-widest hover:bg-blue-500 hover:text-white transition-all shadow-xl shadow-white/5 uppercase">Refresh Tokens</button>
                </div>
            </div>

            <div class="flex justify-center pb-10">
                <button type="submit" form="update_sys" class="h-16 px-16 bg-blue-600 text-white rounded-3xl font-black shadow-2xl shadow-blue-300 dark:shadow-none hover:bg-blue-700 active:scale-[0.98] transition-all tracking-widest uppercase">Apply Global Changes</button>
            </div>
        </div>
    </div>
</main>

<script>
    // System settings logic is mostly UI-only for this demo
</script>
</body>
</html>
