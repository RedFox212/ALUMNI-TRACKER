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
        --bg-main: #0c111d;
        --bg-card: #161b26;
        --text-primary: #f9fafb;
        --text-secondary: #d1d5db;
        --text-muted: #9ca3af;
        --border-color: #2d3340;
        --glass-bg: rgba(12, 17, 29, 0.95);
    }

    /* GLOBAL SYSTEM OVERRIDES */
    body { 
        background-color: var(--bg-main) !important; 
        color: var(--text-secondary) !important; 
        transition: background-color 0.4s cubic-bezier(0.4, 0, 0.2, 1), 
                    color 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
    }
    
    /* Headings & Labels Visibility */
    h1, h2, h3, h4, h5, h6, label, th, b, strong { 
        color: var(--text-primary) !important; 
        transition: color 0.4s ease;
    }

    /* Automated Dark-Mode Adapters for Tailwind Utilities */
    .dark .bg-white, .dark .bg-slate-50, .dark .bg-gray-50 { background-color: var(--bg-card) !important; }
    .dark .bg-slate-100, .dark .bg-gray-100 { background-color: #1f2937 !important; }
    .dark .bg-slate-50\/50, .dark .bg-white\/50, .dark .bg-white\/80 { background-color: var(--glass-bg) !important; }
    
    .dark .border-slate-100, .dark .border-slate-200, .dark .border-slate-50, .dark .border-gray-100, .dark .border-gray-200 { 
        border-color: var(--border-color) !important; 
    }

    .dark .text-slate-800, .dark .text-slate-700, .dark .text-slate-900, .dark .text-gray-900, .dark .text-gray-800 { 
        color: var(--text-primary) !important; 
    }
    .dark .text-slate-600, .dark .text-slate-500, .dark .text-gray-600, .dark .text-gray-500 { 
        color: var(--text-secondary) !important; 
    }
    .dark .text-slate-400, .dark .text-gray-400 { 
        color: var(--text-muted) !important; 
    }
    
    /* Input & Select Adapters */
    .dark input, .dark select, .dark textarea {
        background-color: #0f172a !important;
        color: #f8fafc !important;
        border-color: var(--border-color) !important;
    }
    .dark input::placeholder { color: #4b5563 !important; }

    /* Glassmorphism Adapters */
    .dark .glass-dark, .dark .glass-card, .dark .bg-white\/10 { 
        background: rgba(30, 41, 59, 0.5) !important; 
        border-color: rgba(255, 255, 255, 0.08) !important; 
    }

    /* CUSTOM UTILITY: Theme Switcher Animation */
    .theme-transition { transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1); }

    /* Additional UI Harmony Overrides */
    header { 
        background-color: var(--glass-bg) !important; 
        border-bottom: 1px solid var(--border-color) !important; 
        backdrop-filter: blur(16px); 
    }
    
    .border-slate-50, .border-slate-100, .border-slate-200, .border-gray-100 { 
        border-color: var(--border-color) !important; 
    }
</style>

<aside id="main-sidebar" class="w-72 bg-white dark:bg-[#0c111d] border-r border-slate-100 dark:border-slate-800 flex flex-col fixed h-full z-50 transition-all duration-300 shadow-2xl -translate-x-full lg:translate-x-0">
    <!-- Close button for mobile -->
    <div class="flex justify-end p-4 lg:hidden">
        <button onclick="toggleSidebar()" class="p-2 text-slate-400 hover:text-white bg-white/5 rounded-xl">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
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
            <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[2px] pl-4 mb-4">Management</p>
            <div class="space-y-2">
                <a href="dashboard.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('dashboard.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">📊</span> Dashboard
                </a>
                
                <?php if ($user_role === 'admin'): ?>
                <a href="admin-id-manager.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-black <?php echo active('admin-id-manager.php'); ?> transition-all group active:scale-95 shadow-sm">
                    <span class="text-xl">🆔</span> Identity Manager
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
            <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[2px] pl-4 mb-4">Professional</p>
            <div class="space-y-1">
                <a href="job-board.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold <?php echo active('job-board.php'); ?> transition-all group">
                    <span class="text-lg">💼</span> Job Board
                </a>
            </div>
        </div>

        <?php endif; ?>

        <!-- Community Section (Shared) -->
        <div>
            <p class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[2px] pl-4 mb-4">Community</p>
            <div class="space-y-1">
                <a href="announcements.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold <?php echo active('announcements.php') || active('events.php'); ?> transition-all group">
                    <span class="text-lg">📢</span> Events & News
                </a>
                <a href="directory.php" class="flex items-center gap-3 px-4 py-3 rounded-2xl text-sm font-bold <?php echo active('directory.php'); ?> transition-all group">
                    <span class="text-lg">🔍</span> Directory
                </a>
            </div>
        </div>

    </nav>

    <div class="p-6 mt-auto border-t border-slate-50 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50">
        <!-- GLOBAL THEME TOGGLE -->
        <button onclick="toggleDarkMode()" 
            class="flex items-center justify-between w-full h-12 bg-white dark:bg-slate-800 border-2 border-slate-100 dark:border-slate-700 px-4 rounded-2xl mb-4 hover:border-blue-500 hover:ring-4 hover:ring-blue-500/10 transition-all group overflow-hidden relative">
            <div class="flex flex-col items-start">
                <span class="text-[8px] font-black uppercase tracking-widest text-slate-400">Current Mode</span>
                <span id="theme-status-text" class="text-[10px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-400">LIGHT</span>
            </div>
            <span id="theme-status-icon" class="text-xl transition-transform group-hover:rotate-12 group-active:scale-90 pb-1">🌙</span>
        </button>

        <div class="flex items-center gap-3 mb-6 px-2">
            <?php echo renderAvatar($user_name, 'w-10 h-10 flex-shrink-0 shadow-sm border-2 border-white dark:border-slate-800', $_SESSION['profile_pic'] ?? null); ?>
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
<div id="sidebar-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden lg:hidden transition-all duration-300"></div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('main-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const isHidden = sidebar.classList.contains('-translate-x-full');
        
        if (isHidden) {
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        } else {
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
</script>

<?php require_once '../includes/chat_widget.php'; ?>

<script>
    function updateDarkModeUI(isDark) {
        const themeIcon = document.getElementById('theme-status-icon');
        const themeText = document.getElementById('theme-status-text');
        
        if (isDark) {
            document.documentElement.classList.add('dark');
            if(themeIcon) themeIcon.innerText = '🌙'; 
            if(themeText) themeText.innerText = 'DARK';
            localStorage.setItem('lats-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            if(themeIcon) themeIcon.innerText = '☀️';
            if(themeText) themeText.innerText = 'LIGHT';
            localStorage.setItem('lats-theme', 'light');
        }
        
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: resolvesActive(isDark) }));
    }

    function resolvesActive(v) { return v ? 'dark' : 'light'; }

    function toggleDarkMode() {
        const isDark = document.documentElement.classList.contains('dark');
        updateDarkModeUI(!isDark);
    }

    // Immediate execution for theme persistence
    (function() {
        const storedTheme = localStorage.getItem('lats-theme');
        if (storedTheme === 'dark' || (!storedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            updateDarkModeUI(true);
        } else {
            updateDarkModeUI(false);
        }
    })();
</script>
