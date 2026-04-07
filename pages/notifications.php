<?php
// pages/notifications.php  — #2 EASY: read-only list + mark-as-read
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Mark all as read on visit
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$user_id]);

// Fetch all
$notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC");
$notifs->execute([$user_id]);
$notifications = $notifs->fetchAll();

$type_icons = [
    'announcement' => ['icon'=>'📢','bg'=>'bg-blue-50','text'=>'text-blue-600'],
    'welcome'      => ['icon'=>'🎉','bg'=>'bg-green-50','text'=>'text-green-600'],
    'spotlight'    => ['icon'=>'⭐','bg'=>'bg-amber-50','text'=>'text-amber-600'],
    'poll'         => ['icon'=>'📊','bg'=>'bg-purple-50','text'=>'text-purple-600'],
    'default'      => ['icon'=>'🔔','bg'=>'bg-slate-50','text'=>'text-slate-600'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex">

<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3 text-slate-400">
            <a href="dashboard.php" class="text-slate-400 hover:text-slate-600 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Notifications</span>
        </div>
        <span class="text-xs bg-slate-100 text-slate-500 font-bold px-3 py-1 rounded-full"><?php echo count($notifications); ?> total</span>
    </header>

    <div class="p-8 max-w-3xl mx-auto w-full">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 italic">NOTIFICATIONS</h1>
            <p class="text-slate-400 text-sm font-medium">Your recent activity and announcements.</p>
        </div>

        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <?php if (empty($notifications)): ?>
                <div class="py-20 text-center text-slate-300">
                    <div class="text-6xl mb-4">🔕</div>
                    <p class="font-medium italic">You're all caught up! No notifications yet.</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-slate-50">
                    <?php foreach ($notifications as $n):
                        $t = $n['type'] ?? 'default';
                        $style = $type_icons[$t] ?? $type_icons['default'];
                    ?>
                    <div class="flex items-start gap-4 p-5 hover:bg-slate-50 transition-all">
                        <div class="w-11 h-11 rounded-2xl flex items-center justify-center flex-shrink-0 text-lg <?php echo $style['bg']; ?>">
                            <?php echo $style['icon']; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-slate-700 font-medium leading-relaxed"><?php echo htmlspecialchars($n['message']); ?></p>
                            <div class="flex items-center gap-3 mt-2">
                                <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded <?php echo $style['bg'].' '.$style['text']; ?>"><?php echo strtoupper($t); ?></span>
                                <span class="text-[10px] text-slate-400"><?php echo formatDate($n['created_at']); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>
</body>
</html>
