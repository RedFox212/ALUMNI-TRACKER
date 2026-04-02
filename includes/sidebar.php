<?php
// includes/sidebar.php - Reusable premium sidebar
$current_page = basename($_SERVER['PHP_SELF']);

function active($page) {
    global $current_page;
    return $current_page === $page ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900';
}

$user_role = $_SESSION['user_role'] ?? 'alumni';
$user_name = $_SESSION['user_name'] ?? 'Guest';
?>
<!-- SYSTEM STYLE REINFORCEMENT -->
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
<style>
    /* THEME ENGINE - CSS VARIABLES */
    :root {
        --bg-main: #f1f5f9;
        --bg-card: #ffffff;
        --text-primary: #0f172a;
        --text-secondary: #334155;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --glass-bg: rgba(255, 255, 255, 0.85);
    }

    .dark {
        --bg-main: #0f172a;
        --bg-card: #1e293b;
        --text-primary: #f8fafc;
        --text-secondary: #cbd5e1;
        --text-muted: #64748b;
        --border-color: #334155;
        --glass-bg: rgba(15, 23, 42, 0.9);
    }

    /* GLOBAL SYSTEM OVERRIDES */
    body { background-color: var(--bg-main) !important; color: var(--text-secondary) !important; transition: all 0.3s ease; }
    h1, h2, h3, h4, h5, h6 { color: var(--text-primary) !important; }
    
    .bg-white, .bg-slate-50, .dashboard-card { background-color: var(--bg-card) !important; color: var(--text-primary) !important; border-color: var(--border-color) !important; }
    .text-slate-800, .text-slate-900, .text-gray-800 { color: var(--text-primary) !important; }
    .text-slate-600, .text-slate-500, .text-gray-600 { color: var(--text-secondary) !important; }
    .text-slate-400, .text-gray-400 { color: var(--text-muted) !important; }
    
    .border-slate-50, .border-slate-100, .border-slate-200 { border-color: var(--border-color) !important; }
    
    header { background-color: var(--glass-bg) !important; border-bottom: 1px solid var(--border-color) !important; backdrop-filter: blur(16px); }
    aside { background-color: var(--bg-main) !important; border-right: 1px solid var(--border-color) !important; transition: all 0.5s ease; }
    
    input, select, textarea { background-color: var(--bg-card) !important; color: var(--text-primary) !important; border: 1px solid var(--border-color) !important; }
</style>

<aside class="w-72 bg-slate-900 border-r border-slate-100 flex flex-col hidden lg:flex fixed h-full z-40 transition-all duration-500 shadow-2xl">
    <div class="p-8 pb-4 flex flex-col items-center text-center border-b border-slate-50 dark:border-slate-800">
        <div class="w-20 h-20 bg-white rounded-3xl flex items-center justify-center p-3 mb-4 shadow-xl shadow-blue-500/10 ring-1 ring-slate-100 overflow-hidden">
            <img src="../loalogo.png" alt="LOA Logo" class="w-full h-full object-contain">
        </div>
        <div>
            <h1 class="font-black text-slate-800 dark:text-white text-lg leading-none uppercase tracking-tighter">Lyceum of Alabang</h1>
            <p class="text-blue-600 font-bold text-[10px] mt-1 tracking-[1px] uppercase">Alumni System</p>
        </div>
    </div>

    <nav class="flex-1 px-6 space-y-8 mt-6 overflow-y-auto pb-10">
        
        <!-- Management Section -->
        <div>
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[2px] pl-4 mb-4">Management</p>
            <div class="space-y-2">
                <a href="dashboard.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('dashboard.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">📊</span> Dashboard
                </a>
                
                <?php if ($user_role === 'admin'): ?>
                <a href="admin-id-manager.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('admin-id-manager.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">🆔</span> Identity Manager
                </a>
                <a href="announcements.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('announcements.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">📢</span> Broadcast Hub
                </a>
                <a href="manage-users.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('manage-users.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">👥</span> System Users
                </a>
                <a href="admin-review.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('admin-review.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">🛡️</span> Review Center
                </a>
                <a href="manage-spotlights.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('manage-spotlights.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">✨</span> Spotlights
                </a>
                <a href="reports.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('reports.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">📈</span> Reports
                </a>
                <a href="admin-settings.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('admin-settings.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">⚙️</span> Settings
                </a>
                <?php endif; ?>

                <?php if ($user_role === 'alumni'): ?>
                <a href="edit-profile.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('edit-profile.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">⚙️</span> Edit Profile
                </a>
                <a href="my-id.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('my-id.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">🆔</span> My Alumni ID
                </a>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($user_role === 'alumni'): ?>
        <!-- Professional Section -->
        <div>
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[2px] pl-4 mb-4">Professional</p>
            <div class="space-y-1">
                <a href="job-board.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold <?php echo active('job-board.php'); ?> transition-all group">
                    <span class="text-lg">💼</span> Job Board
                </a>
                <a href="business-directory.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold <?php echo active('business-directory.php'); ?> transition-all group">
                    <span class="text-lg">🏬</span> Businesses
                </a>
                <a href="resume-builder.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold <?php echo active('resume-builder.php'); ?> transition-all group">
                    <span class="text-lg">📄</span> Resume Builder
                </a>
            </div>
        </div>

        <!-- Community Section -->
        <div>
            <p class="text-[10px] font-black text-slate-300 uppercase tracking-[2px] pl-4 mb-4">Community</p>
            <div class="space-y-1">
                <a href="events.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold <?php echo active('events.php'); ?> transition-all group">
                    <span class="text-lg">🎫</span> Events
                </a>
                <a href="directory.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold <?php echo active('directory.php'); ?> transition-all group">
                    <span class="text-lg">🔍</span> Directory
                </a>
                <a href="mentorship.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold <?php echo active('mentorship.php'); ?> transition-all group">
                    <span class="text-lg">🤝</span> Mentorship
                </a>
            </div>
        </div>
        <?php endif; ?>

    </nav>

    <div class="p-6 mt-auto border-t border-slate-50 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
        <div class="flex items-center gap-3 mb-6 px-2">
            <?php echo renderAvatar($user_name, 'w-10 h-10 flex-shrink-0 shadow-sm border-2 border-white dark:border-slate-800'); ?>
            <div class="min-w-0">
                <p class="text-sm font-black text-slate-800 dark:text-white truncate"><?php echo htmlspecialchars($user_name); ?></p>
                <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest"><?php echo htmlspecialchars($user_role); ?></p>
            </div>
        </div>
        <a href="../logout.php" class="flex items-center justify-center gap-2 w-full h-12 bg-white dark:bg-slate-800 text-rose-500 border border-rose-100 dark:border-rose-900 rounded-2xl text-xs font-black hover:bg-rose-500 hover:text-white hover:border-rose-500 transition-all shadow-sm">
            SIGN OUT
        </a>
    </div>
</aside>

<script>
    function updateDarkModeUI(isDark) {
        const headerIcon = document.getElementById('dm-icon-header');
        if (isDark) {
            document.documentElement.classList.add('dark');
            if(headerIcon) headerIcon.innerText = '☀️';
            localStorage.setItem('theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            if(headerIcon) headerIcon.innerText = '🌙';
            localStorage.setItem('theme', 'light');
        }
    }

    function toggleDarkMode() {
        const isDark = document.documentElement.classList.contains('dark');
        updateDarkModeUI(!isDark);
    }

    if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        updateDarkModeUI(true);
    }
</script>
