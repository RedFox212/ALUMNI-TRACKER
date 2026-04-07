<?php
// includes/topbar.php - Shared top navigation bar
$unread_count = $unread_count ?? 0;
$pending_spots = $pending_spots ?? 0;
?>
<header class="h-16 bg-white/80 dark:bg-slate-900/80 backdrop-blur-xl border-b border-slate-100 dark:border-slate-800 px-8 flex items-center justify-between sticky top-0 z-30 transition-all duration-500">
    <!-- Mobile Menu Toggle -->
    <button onclick="toggleSidebar()" class="lg:hidden p-2 text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-800 rounded-xl hover:bg-white dark:hover:bg-slate-700 transition-all shadow-sm border border-slate-100 dark:border-slate-700">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <!-- Left: Breadcrumb or context -->
    <div class="flex items-center gap-4 text-slate-400">
        <span class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tighter"><?php echo $topbar_title ?? 'Portal'; ?></span>
        <span class="w-1 h-1 bg-slate-200 dark:bg-slate-700 rounded-full hidden sm:block"></span>
        <span class="text-[10px] italic font-medium hidden sm:block"><?php echo $topbar_subtitle ?? 'Lyceum of Alabang'; ?></span>
    </div>
    
    <!-- Right: Actions -->
    <div class="flex items-center gap-6">
        <!-- Custom Actions (if any) -->
        <?php if (isset($topbar_actions)) echo $topbar_actions; ?>
        
        <div class="h-10 w-[1px] bg-transparent"></div>

        <?php if ($_SESSION['user_role'] === 'alumni'): ?>
        <a href="notifications.php" class="relative p-2.5 bg-slate-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400 rounded-2xl hover:bg-white dark:hover:bg-slate-700 transition-all shadow-sm border border-slate-100 dark:border-slate-700">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            <?php if ($unread_count > 0): ?>
            <span class="absolute -top-1 -right-1 w-5 h-5 bg-blue-600 text-white text-[9px] font-black rounded-full flex items-center justify-center ring-4 ring-white dark:ring-slate-900"><?php echo min($unread_count, 9); ?></span>
            <?php endif; ?>
        </a>
        <?php elseif ($_SESSION['user_role'] === 'admin' && $pending_spots > 0): ?>
        <a href="manage-spotlights.php" class="relative p-2.5 bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 rounded-2xl hover:scale-105 transition-all shadow-sm border border-amber-100 dark:border-amber-900/30">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            <span class="absolute -top-1 -right-1 w-5 h-5 bg-amber-500 text-white text-[9px] font-black rounded-full flex items-center justify-center ring-4 ring-white dark:ring-slate-900"><?php echo $pending_spots; ?></span>
        </a>
        <?php endif; ?>

        <div class="h-10 w-[1px] bg-slate-100 dark:bg-slate-800"></div>

        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <p class="text-sm font-black text-slate-800 dark:text-white uppercase truncate max-w-[120px]"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                <p class="text-[9px] text-slate-400 font-black uppercase tracking-[2px] leading-none mt-1"><?php echo htmlspecialchars($_SESSION['user_role']); ?></p>
            </div>
            <?php echo renderAvatar($_SESSION['user_name'], 'w-10 h-10 border-2 border-white dark:border-slate-800 shadow-lg ring-1 ring-slate-100 dark:ring-slate-700', $_SESSION['profile_pic'] ?? null); ?>
        </div>
    </div>
</header>
