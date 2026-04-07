<?php
// pages/dashboard.php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// --- HANDLE CSV EXPORT ---
if (isset($_GET['export']) && $user_role === 'admin') {
    $type = $_GET['export'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lats_' . $type . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');

    if ($type === 'alumni') {
        fputcsv($out, ['Name', 'Email', 'Program', 'Batch Year', 'Company', 'Job Title', 'Employment Status', 'Years Experience', 'Address', 'Contact']);
        $rows = $pdo->query("SELECT u.name, u.email, a.program, a.batch_year, a.company, a.job_title, a.employment_status, a.years_experience, a.address, a.contact_no FROM users u JOIN alumni a ON u.id = a.user_id ORDER BY a.batch_year DESC, u.name ASC")->fetchAll();
        foreach ($rows as $r) { fputcsv($out, $r); }
    } elseif ($type === 'employment') {
        fputcsv($out, ['Employment Status', 'Count']);
        $rows = $pdo->query("SELECT employment_status, COUNT(*) as count FROM alumni GROUP BY employment_status ORDER BY count DESC")->fetchAll();
        foreach ($rows as $r) { fputcsv($out, [$r['employment_status'], $r['count']]); }
    } elseif ($type === 'program') {
        fputcsv($out, ['Program', 'Count', 'Employed', 'In-Discipline']);
        $rows = $pdo->query("SELECT program, COUNT(*) as total, SUM(CASE WHEN employment_status='Employed' THEN 1 ELSE 0 END) as employed, SUM(CASE WHEN discipline_match=1 THEN 1 ELSE 0 END) as indiscipline FROM alumni GROUP BY program ORDER BY total DESC")->fetchAll();
        foreach ($rows as $r) { fputcsv($out, [$r['program'], $r['total'], $r['employed'], $r['indiscipline']]); }
    } elseif ($type === 'master') {
        fputcsv($out, [
            'System ID', 'Alumni ID Num', 'Full Name', 'Gender', 'Email', 
            'Program', 'Batch Year', 'Degree', 'College', 
            'Employment Status', 'Company', 'Job Title', 'Job Alignment', 'Years Experience', 
            'Skills', 'Advanced Degrees', 'Contact No', 'Verification Status'
        ]);
        $rows = $pdo->query("
            SELECT 
                a.id, a.alumni_id_num, CONCAT(a.first_name, ' ', a.middle_name, ' ', a.last_name) as full_name, 
                a.gender, u.email, a.program, a.batch_year, a.degree, a.college, 
                a.employment_status, a.company, a.job_title, 
                (CASE WHEN a.discipline_match=1 THEN 'Aligned' ELSE 'Not Aligned' END) as alignment,
                a.years_experience, a.skills, 
                CONCAT_WS(', ', NULLIF(a.masteral, ''), NULLIF(a.doctorate, '')) as advanced,
                a.contact_no, a.verification_status
            FROM users u 
            JOIN alumni a ON u.id = a.user_id 
            ORDER BY a.batch_year DESC, full_name ASC
        ")->fetchAll();
        foreach ($rows as $r) { fputcsv($out, $r); }
    }
    fclose($out); exit;
}

// --- ADMIN STATS ---
$stats = [];
if ($user_role === 'admin') {
    // Total Alumni
    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM alumni")->fetchColumn();
    
    // Employment %
    $emp = $pdo->query("SELECT COUNT(*) FROM alumni WHERE employment_status = 'Employed'")->fetchColumn();
    $stats['employment_rate'] = $stats['total'] > 0 ? round(($emp / $stats['total']) * 100) : 0;
    
    // In-Discipline %
    $disc = $pdo->query("SELECT COUNT(*) FROM alumni WHERE discipline_match = 1")->fetchColumn();
    $stats['discipline_rate'] = $stats['total'] > 0 ? round(($disc / $stats['total']) * 100) : 0;

    // Recent Activity: Fetch newly joined alumni
    $stmt = $pdo->query("SELECT u.name, a.program, a.created_at, a.profile_pic FROM users u JOIN alumni a ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 5");
    $recent_activity = $stmt->fetchAll();

    // UNREAD CHATS (Relevance to Alumni conversation)
    $unread_chats = $pdo->query("SELECT COUNT(DISTINCT sender_id) FROM chat_messages WHERE receiver_id = $user_id AND is_read = 0")->fetchColumn();
    
    // PENDING IDENTITY VERIFICATION
    $pending_ids = $pdo->query("SELECT COUNT(*) FROM alumni WHERE alumni_id_num IS NULL OR alumni_id_num = ''")->fetchColumn();

    // LATEST ANNOUNCEMENT
    $latest_announcement = $pdo->query("SELECT title FROM announcements ORDER BY created_at DESC LIMIT 1")->fetchColumn();
}

// --- ALUMNI PROFILE ---
$profile = null;
if ($user_role === 'alumni') {
    $stmt = $pdo->prepare("SELECT a.*, u.email FROM alumni a JOIN users u ON a.user_id = u.id WHERE a.user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();
    $user_email = $profile['email'] ?? '';

    if (!$profile) {
        header('Location: setup-profile.php');
        exit;
    }

    // Calculate experience
    $exp_years_calc = 0;
    if (!empty($profile['date_started'])) {
        $start = new DateTime($profile['date_started']);
        $now   = new DateTime();
        $exp_years_calc = $start->diff($now)->y;
    }
    $exp_years = max($exp_years_calc, (int)($profile['years_experience'] ?? 0));

    // Unread notification count
    $notif_stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $notif_stmt->execute([$user_id]);
    $unread_count = (int)$notif_stmt->fetchColumn();

    // Handle spotlight reaction
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['react_spotlight'])) {
        $sl_id   = (int)($_POST['spotlight_id'] ?? 0);
        $react   = $_POST['reaction'] ?? '';
        if ($sl_id && in_array($react, ['like','inspire','congratulate'])) {
            // Remove old reaction first (toggle support)
            $del = $pdo->prepare("DELETE FROM spotlight_reactions WHERE spotlight_id=? AND user_id=?");
            $del->execute([$sl_id, $user_id]);
            if (($_POST['prev_reaction'] ?? '') !== $react) {
                $pdo->prepare("INSERT INTO spotlight_reactions (spotlight_id, user_id, reaction_type) VALUES (?,?,?)")
                    ->execute([$sl_id, $user_id, $react]);
            }
            header('Location: dashboard.php');
            exit;
        }
    }

    // Get active poll for widget (first accessible poll user hasn't voted on)
    $batch   = $profile['batch_year'] ?? null;
    $program = $profile['program']    ?? null;
    $wpoll_stmt = $pdo->prepare(
        "SELECT p.*, (SELECT COUNT(*) FROM poll_votes pv WHERE pv.poll_id=p.id AND pv.user_id=?) as already_voted
         FROM polls p
         WHERE (p.close_date IS NULL OR p.close_date > NOW())
           AND (p.visibility='all'
                OR (p.visibility='batch'   AND p.target_batch=?)
                OR (p.visibility='program' AND p.target_program=?))
         ORDER BY p.created_at DESC LIMIT 1"
    );
    $wpoll_stmt->execute([$user_id, $batch, $program]);
    $widget_poll = $wpoll_stmt->fetch();

    if ($widget_poll) {
        $wopts = $pdo->prepare("SELECT po.id, po.option_text, COUNT(pv.id) as votes FROM poll_options po LEFT JOIN poll_votes pv ON po.id=pv.option_id WHERE po.poll_id=? GROUP BY po.id ORDER BY po.id");
        $wopts->execute([$widget_poll['id']]);
        $widget_options = $wopts->fetchAll();
        $widget_total   = array_sum(array_column($widget_options, 'votes'));
        // Which option did user vote on?
        $myvote = $pdo->prepare("SELECT option_id FROM poll_votes WHERE poll_id=? AND user_id=?");
        $myvote->execute([$widget_poll['id'], $user_id]);
        $my_option_ids = $myvote->fetchAll(PDO::FETCH_COLUMN);
    }
}

// --- GLOBAL: ACTIVE POLLS COUNT ---
$polls_count = $pdo->query("SELECT COUNT(*) FROM polls WHERE close_date > NOW() OR close_date IS NULL")->fetchColumn();

// --- GLOBAL: SPOTLIGHT ---
$stmt = $pdo->query("SELECT s.*, u.name, a.program, a.batch_year FROM spotlights s JOIN alumni a ON s.alumni_id=a.id JOIN users u ON a.user_id=u.id WHERE s.status='active' LIMIT 1");
$spotlight = $stmt->fetch();

// Spotlight reactions
$spot_reactions = [];
$my_reaction = null;
if ($spotlight) {
    $rq = $pdo->prepare("SELECT reaction_type, COUNT(*) as cnt FROM spotlight_reactions WHERE spotlight_id=? GROUP BY reaction_type");
    $rq->execute([$spotlight['id']]);
    foreach ($rq->fetchAll() as $r) $spot_reactions[$r['reaction_type']] = $r['cnt'];
    if (isset($user_id)) {
        $mr = $pdo->prepare("SELECT reaction_type FROM spotlight_reactions WHERE spotlight_id=? AND user_id=?");
        $mr->execute([$spotlight['id'], $user_id]);
        $my_reaction = $mr->fetchColumn();
    }
}
// Admin pending spotlights count
$pending_spots = ($user_role === 'admin') ? (int)$pdo->query("SELECT COUNT(*) FROM spotlights WHERE status='pending'")->fetchColumn() : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex">

    <!-- Sidebar -->
    <?php require_once '../includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="flex-1 flex flex-col ml-0 lg:ml-72">
        
        <?php 
            $topbar_title = 'Portal Home';
            $topbar_subtitle = ($user_role === 'admin') ? 'Strategic Overview' : 'My Network';
            require_once '../includes/topbar.php'; 
        ?>

        <div class="p-8">
            
            <?php if ($user_role === 'admin'): ?>
                <!-- ADMIN DASHBOARD -->
                <div class="mb-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
                    <div>
                        <h1 class="text-3xl font-black text-slate-800 italic uppercase tracking-tighter">SYSTEM COMMAND</h1>
                        <p class="text-slate-400 text-sm font-medium">Live metrics from the Lyceum of Alabang Alumni Database.</p>
                    </div>
                    
                    <!-- EXECUTIVE EXPORTS (Polished) -->
                    <div class="flex flex-wrap gap-3">
                        <a href="?export=master" class="h-12 px-6 bg-indigo-600 text-white font-black text-[11px] rounded-[20px] hover:bg-indigo-700 flex items-center gap-3 transition-all shadow-xl shadow-indigo-500/20 uppercase tracking-[2px] active:scale-95">
                            <span class="text-lg">📊</span> Master CSV
                        </a>
                        <a href="?export=alumni" class="h-12 px-6 bg-white dark:bg-slate-800/50 backdrop-blur-md text-blue-600 dark:text-blue-400 font-black text-[11px] rounded-[20px] border border-slate-200 dark:border-slate-700 hover:bg-blue-50 dark:hover:bg-blue-900/20 flex items-center gap-3 transition-all uppercase tracking-[2px] active:scale-95">
                            <span class="text-lg">👥</span> Alumni
                        </a>
                        <a href="?export=program" class="h-12 px-6 bg-white dark:bg-slate-800/50 backdrop-blur-md text-purple-600 dark:text-purple-400 font-black text-[11px] rounded-[20px] border border-slate-200 dark:border-slate-700 hover:bg-purple-50 dark:hover:bg-purple-900/20 flex items-center gap-3 transition-all uppercase tracking-[2px] active:scale-95">
                            <span class="text-lg">🎓</span> Program
                        </a>
                        <a href="?export=employment" class="h-12 px-6 bg-white dark:bg-slate-800/50 backdrop-blur-md text-emerald-600 dark:text-emerald-400 font-black text-[11px] rounded-[20px] border border-slate-200 dark:border-slate-700 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 flex items-center gap-3 transition-all uppercase tracking-[2px] active:scale-95">
                            <span class="text-lg">💼</span> Status
                        </a>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
                    <div class="bg-white dark:bg-[#161b26] p-8 rounded-[40px] shadow-sm border border-slate-100 dark:border-slate-800/50 flex items-center gap-6 hover:shadow-xl transition-all group">
                        <div class="w-16 h-16 bg-blue-50 dark:bg-blue-600/10 text-blue-600 dark:text-blue-400 rounded-3xl flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[2px] mb-1">Total Number of Registered Alumni/s</p>
                            <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tight"><?php echo number_format($stats['total']); ?></p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-[#161b26] p-8 rounded-[40px] shadow-sm border border-slate-100 dark:border-slate-800/50 flex items-center gap-6 hover:shadow-xl transition-all group">
                        <div class="w-16 h-16 bg-emerald-50 dark:bg-emerald-600/10 text-emerald-600 dark:text-emerald-400 rounded-3xl flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[2px] mb-1">Career Success</p>
                            <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tight"><?php echo $stats['employment_rate'] ?? 0; ?>%</p>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-[#161b26] p-8 rounded-[40px] shadow-sm border border-slate-100 dark:border-slate-800/50 flex items-center gap-6 hover:shadow-xl transition-all group">
                        <div class="w-16 h-16 bg-purple-50 dark:bg-purple-600/10 text-purple-600 dark:text-purple-400 rounded-3xl flex items-center justify-center shadow-inner group-hover:scale-110 transition-transform">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[10px] font-black uppercase tracking-[2px] mb-1">Aligned Careers</p>
                            <p class="text-4xl font-black text-slate-800 dark:text-white tracking-tight"><?php echo $stats['discipline_rate'] ?? 0; ?>%</p>
                        </div>
                    </div>
                </div>

                <!-- ADMINISTRATIVE OPERATIONS -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-16">
                    <a href="admin-id-manager.php" class="bg-indigo-50/50 dark:bg-indigo-950/20 p-6 rounded-3xl border border-indigo-100/50 dark:border-indigo-900/30 flex items-center justify-between hover:scale-[1.02] transition-all group">
                        <div class="flex items-center gap-5">
                            <div class="w-12 h-12 bg-white dark:bg-slate-900 text-indigo-600 rounded-2xl flex items-center justify-center shadow-sm group-hover:rotate-12 transition-transform">
                                <span class="text-xl font-black"><?php echo $pending_ids; ?></span>
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-400">Action Required</p>
                                <p class="text-sm font-black text-indigo-900 dark:text-indigo-100 uppercase tracking-tighter">Identity Verifications</p>
                            </div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                    </a>
                    <div onclick="toggleChatWindow()" class="bg-blue-50/50 dark:bg-blue-950/20 p-6 rounded-3xl border border-blue-100/50 dark:border-blue-900/30 flex items-center justify-between hover:scale-[1.02] transition-all cursor-pointer group">
                        <div class="flex items-center gap-5">
                            <div id="unread-badge-container" class="w-12 h-12 <?php echo $unread_chats > 0 ? 'bg-blue-600 text-white shadow-lg shadow-blue-200 animate-pulse' : 'bg-white dark:bg-slate-900 text-blue-600'; ?> rounded-2xl flex items-center justify-center shadow-sm group-hover:-rotate-12 transition-transform">
                                <span id="dashboard-unread-count" class="text-xl font-black"><?php echo $unread_chats; ?></span>
                            </div>
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-blue-400">Support Active</p>
                                <p class="text-sm font-black text-blue-900 dark:text-blue-100 uppercase tracking-tighter">Live Support Queue</p>
                            </div>
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" /></svg>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 bg-white rounded-[32px] shadow-sm border border-slate-100 overflow-hidden">
                        <div class="p-8 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                            <h3 class="font-black text-slate-800 uppercase tracking-tighter">Recent Activities</h3>
                            <span class="text-xs font-bold text-blue-600 px-3 py-1 bg-blue-50 rounded-full">LIVE</span>
                        </div>
                        <div class="p-2">
                            <?php foreach ($recent_activity as $ra): ?>
                            <div class="flex items-center justify-between p-4 hover:bg-slate-50 rounded-2xl transition-all group">
                                <div class="flex items-center gap-4">
                                    <?php echo renderAvatar($ra['name'], 'w-10 h-10 shadow-sm border-2 border-white', $ra['profile_pic']); ?>
                                    <div>
                                        <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($ra['name']); ?></p>
                                        <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest"><?php echo $ra['program']; ?></p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs font-bold text-slate-600">Alumni Joined</p>
                                    <p class="text-[10px] text-slate-300 font-medium"><?php echo formatDate($ra['created_at']); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="bg-blue-600 rounded-[32px] p-8 text-white flex flex-col justify-between shadow-xl shadow-blue-200">
                        <div>
                            <h3 class="text-xl font-black italic tracking-tighter mb-6">QUICK ACTIONS</h3>
                            <div class="space-y-3">
                                <a href="reports.php" class="w-full py-4 bg-white/10 hover:bg-white/20 rounded-2xl text-left px-5 text-sm font-bold flex items-center justify-between transition-all">
                                    Generate Report
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4 4H3" /></svg>
                                </a>
                                <a href="announcements.php" class="w-full py-4 bg-white/10 hover:bg-white/20 rounded-2xl text-left px-5 text-sm font-bold flex items-center justify-between transition-all">
                                    Mass Announcement
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4 4H3" /></svg>
                                </a>
                                <a href="polls.php" class="w-full py-4 bg-white/10 hover:bg-white/20 rounded-2xl text-left px-5 text-sm font-bold flex items-center justify-between transition-all">
                                    Manage Polls
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4 4H3" /></svg>
                                </a>
                                <a href="import.php" class="w-full py-4 bg-white/10 hover:bg-white/20 rounded-2xl text-left px-5 text-sm font-bold flex items-center justify-between transition-all">
                                    Import Alumni Data
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4 4H3" /></svg>
                                </a>
                            </div>
                        </div>
                        <div class="mt-12 pt-8 border-t border-white/10">
                            <p class="text-[10px] font-black uppercase tracking-widest opacity-50 mb-2">System Status</p>
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                                <span class="text-sm font-bold">Stable</span>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- ALUMNI DASHBOARD -->
                <div class="max-w-5xl mx-auto">
                    <!-- Profile Hero -->
                    <div class="bg-white rounded-[40px] p-10 shadow-sm border border-slate-100 flex flex-col md:flex-row items-center gap-10 mb-8 relative overflow-hidden group">
                        <!-- Abstract BG Decor -->
                        <div class="absolute -right-20 -top-20 w-64 h-64 bg-slate-50 rounded-full group-hover:scale-110 transition-all duration-1000"></div>
                        
                        <div class="relative z-10 w-32 h-32 md:w-40 md:h-40">
                            <?php echo renderAvatar($user_name, 'w-full h-full text-4xl border-8 border-white shadow-2xl ring-1 ring-slate-100', $profile['profile_pic']); ?>
                            <a href="edit-profile.php" class="absolute bottom-1 right-1 w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center border-4 border-white hover:scale-110 transition-all shadow-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            </a>
                        </div>
                        
                        <div class="relative z-10 text-center md:text-left flex-1">
                            <div class="flex flex-wrap justify-center md:justify-start gap-2 mb-3">
                                <span class="px-3 py-1 bg-blue-50 text-blue-600 text-[10px] font-black rounded-full uppercase tracking-widest"><?php echo htmlspecialchars($profile['program']); ?></span>
                                <span class="px-3 py-1 bg-slate-100 text-slate-500 text-[10px] font-black rounded-full uppercase tracking-widest">BATCH <?php echo $profile['batch_year']; ?></span>
                            </div>
                            <h1 class="text-4xl font-black text-slate-800 tracking-tight mb-2 uppercase"><?php echo htmlspecialchars($user_name); ?></h1>
                            <p class="text-slate-500 font-medium flex items-center justify-center md:justify-start gap-2 mb-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                <?php echo htmlspecialchars($profile['job_title'] ?? 'Full-time Dreamer'); ?>
                                <span class="text-slate-200">|</span>
                                <?php echo htmlspecialchars($profile['company'] ?? 'Add your employer'); ?>
                            </p>
                            
                            <p class="text-[11px] text-slate-400 font-black uppercase tracking-[1px] flex items-center justify-center md:justify-start gap-2 mb-6">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                <?php echo htmlspecialchars($user_email); ?>
                            </p>

                            <!-- Digital Links -->
                            <div class="flex flex-wrap gap-2 justify-center md:justify-start">
                                <?php if (!empty($profile['linkedin_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($profile['linkedin_link']); ?>" target="_blank" class="w-8 h-8 flex items-center justify-center bg-blue-50 text-blue-600 rounded-full hover:bg-blue-600 hover:text-white transition-all">
                                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($profile['portfolio_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($profile['portfolio_link']); ?>" target="_blank" class="w-8 h-8 flex items-center justify-center bg-purple-50 text-purple-600 rounded-full hover:bg-purple-600 hover:text-white transition-all">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($profile['resume_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($profile['resume_link']); ?>" target="_blank" class="w-8 h-8 flex items-center justify-center bg-rose-50 text-rose-600 rounded-full hover:bg-rose-600 hover:text-white transition-all font-black text-[10px]">CV</a>
                                <?php endif; ?>
                            </div>

                            <!-- Badges -->
                            <div class="flex flex-wrap justify-center md:justify-start gap-4 mt-8">
                                <?php if ($profile['discipline_match']): ?>
                                <div class="flex items-center gap-2 bg-green-50 text-green-700 px-4 py-2 rounded-2xl text-xs font-bold border border-green-100/50">
                                    <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div>
                                    In-Discipline
                                </div>
                                <?php endif; ?>
                                
                                <div class="flex items-center gap-2 bg-purple-50 text-purple-700 px-4 py-2 rounded-2xl text-xs font-bold border border-purple-100/50">
                                    🏆 <?php echo $exp_years; ?>+ Years Experience
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                        <!-- Spotlights -->
                        <div class="lg:col-span-2 bg-white rounded-[40px] p-8 shadow-sm border border-slate-100">
                            <div class="flex items-center justify-between mb-6">
                                <h3 class="font-black text-slate-800 uppercase tracking-tighter">ALUMNI SPOTLIGHT</h3>
                                <a href="nominate-spotlight.php" class="text-xs text-blue-600 font-bold hover:underline">+ Nominate</a>
                            </div>

                            <?php if ($spotlight): ?>
                            <div class="flex flex-col md:flex-row gap-8 items-center mb-6">
                                <div class="w-24 h-24 flex-shrink-0 relative">
                                    <?php if (!empty($spotlight['image_path'])): ?>
                                        <img src="../<?php echo htmlspecialchars($spotlight['image_path']); ?>" 
                                             class="w-full h-full object-cover rounded-3xl shadow-lg ring-4 ring-blue-50" 
                                             alt="<?php echo htmlspecialchars($spotlight['name']); ?>">
                                    <?php else: ?>
                                        <?php echo renderAvatar($spotlight['name'], 'w-full h-full text-xl shadow-lg ring-4 ring-blue-50'); ?>
                                    <?php endif; ?>
                                </div>
                                <div class="text-center md:text-left">
                                    <p class="text-blue-600 text-xs font-black uppercase tracking-[4px] mb-2"><?php echo htmlspecialchars($spotlight['name']); ?></p>
                                    <p class="text-xl font-black text-slate-800 leading-tight italic">"<?php echo htmlspecialchars($spotlight['quote']); ?>"</p>
                                    <p class="text-slate-400 text-sm mt-4 font-medium"><?php echo htmlspecialchars($spotlight['achievement']); ?></p>
                                </div>
                            </div>
                            <!-- Reaction Buttons -->
                            <div class="flex items-center gap-2 flex-wrap pt-4 border-t border-slate-50">
                                <?php
                                $rxns = [['like','👍','Like'],['inspire','💡','Inspired'],['congratulate','🎉','Congrats']];
                                foreach ($rxns as [$rt, $em, $lb]):
                                    $cnt   = $spot_reactions[$rt] ?? 0;
                                    $is_me = ($my_reaction === $rt);
                                ?>
                                <form method="POST">
                                    <input type="hidden" name="react_spotlight" value="1">
                                    <input type="hidden" name="spotlight_id" value="<?php echo $spotlight['id']; ?>">
                                    <input type="hidden" name="reaction" value="<?php echo $rt; ?>">
                                    <input type="hidden" name="prev_reaction" value="<?php echo $my_reaction ?? ''; ?>">
                                    <button type="submit"
                                        class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold transition-all <?php echo $is_me ? 'bg-blue-600 text-white shadow-lg shadow-blue-200' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'; ?>">
                                        <?php echo $em; ?> <?php echo $lb; ?> <?php if($cnt>0): ?><span class="<?php echo $is_me?'text-blue-100':'text-slate-400'; ?>"><?php echo $cnt; ?></span><?php endif; ?>
                                    </button>
                                </form>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <div class="py-10 text-center text-slate-300 italic font-medium">
                                No active spotlights this week.
                                <a href="nominate-spotlight.php" class="text-blue-400 hover:text-blue-600 font-bold ml-1">Nominate someone!</a>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Quick Poll Widget (LIVE) -->
                        <div class="bg-white dark:bg-slate-900 rounded-[40px] p-8 text-slate-900 dark:text-white flex flex-col shadow-xl dark:shadow-2xl border border-slate-100 dark:border-slate-800">
                            <?php if ($widget_poll ?? null): ?>
                            <div class="mb-6">
                                <span class="bg-blue-600 text-white text-[10px] font-black px-2 py-1 rounded-full uppercase tracking-widest mb-3 inline-block">Active Poll</span>
                                <h3 class="text-lg font-bold leading-tight"><?php echo htmlspecialchars($widget_poll['title']); ?></h3>
                                <?php if ($widget_poll['description']): ?><p class="text-slate-400 text-xs mt-1"><?php echo htmlspecialchars($widget_poll['description']); ?></p><?php endif; ?>
                            </div>
                            <?php if ($widget_poll['already_voted'] || !empty($my_option_ids)): ?>
                                <!-- Show results -->
                                <div class="space-y-2 flex-1">
                                    <?php foreach ($widget_options as $wo):
                                        $pct = $widget_total > 0 ? round(($wo['votes']/$widget_total)*100) : 0;
                                        $isMine = in_array($wo['id'], $my_option_ids ?? []);
                                    ?>
                                    <div class="<?php echo $isMine?'bg-blue-600/30 rounded-xl p-2':'p-2'; ?>">
                                        <div class="flex justify-between text-xs mb-1">
                                            <span class="font-<?php echo $isMine?'black':'medium'; ?>"><?php if($isMine) echo '✓ '; echo htmlspecialchars($wo['option_text']); ?></span>
                                            <span class="text-slate-400"><?php echo $pct; ?>%</span>
                                        </div>
                                        <div class="h-1.5 bg-white/10 rounded-full overflow-hidden">
                                            <div class="h-full <?php echo $isMine?'bg-blue-400':'bg-slate-500'; ?> rounded-full" style="width:<?php echo $pct; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="text-center text-[10px] text-blue-400 font-bold mt-4">You voted ✅</p>
                            <?php else: ?>
                                <!-- Vote form -->
                                <form method="POST" action="my-polls.php" class="space-y-2 flex-1">
                                    <input type="hidden" name="poll_id" value="<?php echo $widget_poll['id']; ?>">
                                    <?php foreach ($widget_options as $wo): ?>
                                    <label class="flex items-center gap-3 p-3 bg-white/5 border border-white/10 rounded-2xl cursor-pointer hover:bg-blue-600/40 transition-all">
                                        <input type="<?php echo $widget_poll['poll_type']==='multiple'?'checkbox':'radio'; ?>" name="option_ids[]" value="<?php echo $wo['id']; ?>" class="accent-blue-400">
                                        <span class="text-sm font-medium"><?php echo htmlspecialchars($wo['option_text']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                    <button type="submit" class="w-full py-3 rounded-2xl bg-blue-600 hover:bg-blue-500 transition-all font-bold text-sm mt-2">Submit Vote</button>
                                </form>
                            <?php endif; ?>
                            <a href="my-polls.php" class="text-center text-blue-400 font-bold text-xs uppercase tracking-widest mt-4 hover:text-white transition-all">View All Polls →</a>
                            <?php else: ?>
                            <div class="flex-1 flex flex-col items-center justify-center text-slate-500">
                                <div class="text-4xl mb-3">📊</div>
                                <p class="text-sm font-medium italic text-center">No active polls at the moment.</p>
                                <a href="my-polls.php" class="text-blue-400 text-xs font-bold mt-3 hover:text-white transition-all">View Poll History →</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

</body>
</html>
