<?php
// pages/dashboard.php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

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

    // Recent Activity    // Fetch news/announcements (verified only)
    $news = $pdo->query("SELECT a.*, u.name as poster_name FROM announcements a 
                        JOIN users u ON a.created_by = u.id 
                        WHERE a.status = 'verified' 
                        ORDER BY a.created_at DESC LIMIT 3")->fetchAll();
    $stmt = $pdo->query("SELECT u.name, a.program, a.created_at FROM users u JOIN alumni a ON u.id = a.user_id ORDER BY a.created_at DESC LIMIT 5");
    $recent_activity = $stmt->fetchAll();
}

// --- ALUMNI PROFILE ---
$profile = null;
if ($user_role === 'alumni') {
    $stmt = $pdo->prepare("SELECT * FROM alumni WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $profile = $stmt->fetch();

    if (!$profile) {
        header('Location: setup-profile.php');
        exit;
    }

    // Calculate experience
    $exp_years = 0;
    if (!empty($profile['date_started'])) {
        $start = new DateTime($profile['date_started']);
        $now   = new DateTime();
        $exp_years = $start->diff($now)->y;
    }

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
    <main class="flex-1 flex flex-col ml-0 lg:ml-64">
        
        <?php 
            $topbar_title = 'Portal Home';
            $topbar_subtitle = ($user_role === 'admin') ? 'Strategic Overview' : 'My Network';
            require_once '../includes/topbar.php'; 
        ?>

        <div class="p-8">
            
            <?php if ($user_role === 'admin'): ?>
                <!-- ADMIN DASHBOARD -->
                <div class="mb-10">
                    <h1 class="text-3xl font-black text-slate-800 italic">SYSTEM COMMAND</h1>
                    <p class="text-slate-400 text-sm font-medium">Live metrics from the Lyceum of Alabang Alumni Database.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-16">
                    <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 flex items-center gap-6 hover:shadow-xl transition-all">
                        <div class="w-16 h-16 bg-blue-50 text-blue-600 rounded-3xl flex items-center justify-center shadow-inner">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[11px] font-black uppercase tracking-[2px]">Total Alumni</p>
                            <p class="text-4xl font-black text-slate-800 tracking-tight"><?php echo number_format($stats['total']); ?></p>
                        </div>
                    </div>
                    <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 flex items-center gap-6 hover:shadow-xl transition-all">
                        <div class="w-16 h-16 bg-green-50 text-green-600 rounded-3xl flex items-center justify-center shadow-inner">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" /></svg>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[11px] font-black uppercase tracking-[2px]">Employed %</p>
                            <p class="text-4xl font-black text-slate-800 tracking-tight"><?php echo $stats['employment_rate']; ?>%</p>
                        </div>
                    </div>
                    <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 flex items-center gap-6 hover:shadow-xl transition-all">
                        <div class="w-16 h-16 bg-purple-50 text-purple-600 rounded-3xl flex items-center justify-center shadow-inner">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[11px] font-black uppercase tracking-[2px]">In-Discipline</p>
                            <p class="text-4xl font-black text-slate-800 tracking-tight"><?php echo $stats['discipline_rate']; ?>%</p>
                        </div>
                    </div>
                    <div class="bg-white p-8 rounded-[32px] shadow-sm border border-slate-100 flex items-center gap-6 hover:shadow-xl transition-all">
                        <div class="w-16 h-16 bg-amber-50 text-amber-600 rounded-3xl flex items-center justify-center shadow-inner">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                        </div>
                        <div>
                            <p class="text-slate-400 text-[11px] font-black uppercase tracking-[2px]">Active Polls</p>
                            <p class="text-4xl font-black text-slate-800 tracking-tight"><?php echo $polls_count; ?></p>
                        </div>
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
                                    <?php echo renderAvatar($ra['name'], 'w-10 h-10 shadow-sm border-2 border-white'); ?>
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
                            <?php echo renderAvatar($user_name, 'w-full h-full text-4xl border-8 border-white shadow-2xl ring-1 ring-slate-100'); ?>
                            <a href="edit-profile.php" class="absolute bottom-1 right-1 w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center border-4 border-white hover:scale-110 transition-all shadow-lg">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg>
                            </a>
                        </div>
                        
                        <div class="relative z-10 text-center md:text-left flex-1">
                            <div class="flex flex-wrap justify-center md:justify-start gap-2 mb-3">
                                <span class="px-3 py-1 bg-blue-50 text-blue-600 text-[10px] font-black rounded-full uppercase tracking-widest"><?php echo htmlspecialchars($profile['program']); ?></span>
                                <span class="px-3 py-1 bg-slate-100 text-slate-500 text-[10px] font-black rounded-full uppercase tracking-widest">BATCH <?php echo $profile['batch_year']; ?></span>
                            </div>
                            <h1 class="text-4xl font-black text-slate-800 tracking-tight mb-2"><?php echo htmlspecialchars($user_name); ?></h1>
                            <p class="text-slate-500 font-medium flex items-center justify-center md:justify-start gap-2">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                                <?php echo htmlspecialchars($profile['job_title'] ?? 'Full-time Dreamer'); ?>
                                <span class="text-slate-200">|</span>
                                <?php echo htmlspecialchars($profile['company'] ?? 'Add your employer'); ?>
                            </p>

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
                        <div class="bg-slate-900 rounded-[40px] p-8 text-white flex flex-col shadow-2xl">
                            <?php if ($widget_poll ?? null): ?>
                            <div class="mb-6">
                                <span class="bg-blue-600 text-[10px] font-black px-2 py-1 rounded-full uppercase tracking-widest mb-3 inline-block">Active Poll</span>
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
