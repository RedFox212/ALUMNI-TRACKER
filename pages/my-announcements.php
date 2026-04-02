<?php
// pages/my-announcements.php  — #3 EASY: filtered announcements for this alumni
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Get alumni batch/program to filter announcements
$stmt = $pdo->prepare("SELECT batch_year, program FROM alumni WHERE user_id = ?");
$stmt->execute([$user_id]);
$alumni_profile = $stmt->fetch();
$batch   = $alumni_profile['batch_year']  ?? null;
$program = $alumni_profile['program']     ?? null;

// Fetch relevant announcements
$stmt = $pdo->prepare(
    "SELECT a.*, u.name AS author FROM announcements a
     JOIN users u ON a.created_by = u.id
     WHERE a.scope = 'all'
        OR (a.scope = 'batch'   AND a.target_batch   = ?)
        OR (a.scope = 'program' AND a.target_program = ?)
     ORDER BY a.created_at DESC"
);
$stmt->execute([$batch, $program]);
$announcements = $stmt->fetchAll();

$scope_styles = [
    'all'     => ['label'=>'All Alumni',   'bg'=>'bg-blue-100',   'text'=>'text-blue-600'],
    'batch'   => ['label'=>'Your Batch',   'bg'=>'bg-amber-100',  'text'=>'text-amber-600'],
    'program' => ['label'=>'Your Program', 'bg'=>'bg-purple-100', 'text'=>'text-purple-600'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex">

<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-64">
    <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3 text-slate-400">
            <a href="dashboard.php" class="text-slate-400 hover:text-slate-600 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Announcements</span>
        </div>
        <div class="flex items-center gap-2 text-xs text-slate-400">
            <?php if ($batch): ?><span class="bg-amber-50 text-amber-600 font-bold px-2 py-1 rounded-lg">Batch <?php echo $batch; ?></span><?php endif; ?>
            <?php if ($program): ?><span class="bg-purple-50 text-purple-600 font-bold px-2 py-1 rounded-lg"><?php echo htmlspecialchars($program); ?></span><?php endif; ?>
        </div>
    </header>

    <div class="p-8 max-w-3xl mx-auto w-full">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 italic">ANNOUNCEMENTS</h1>
            <p class="text-slate-400 text-sm font-medium">Messages from the LATS administration relevant to you.</p>
        </div>

        <?php if (empty($announcements)): ?>
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 py-20 text-center text-slate-300">
                <div class="text-6xl mb-4">📭</div>
                <p class="font-medium italic">No announcements for you yet. Check back soon!</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($announcements as $i => $ann):
                    $sc = $ann['scope'];
                    $style = $scope_styles[$sc] ?? $scope_styles['all'];
                ?>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md transition-all group <?php echo $i===0?'ring-2 ring-blue-100':''; ?>">
                    <?php if ($i === 0): ?>
                        <div class="bg-gradient-to-r from-blue-600 to-indigo-500 px-6 py-2">
                            <span class="text-white text-[10px] font-black uppercase tracking-widest">Latest</span>
                        </div>
                    <?php endif; ?>
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-3">
                                    <span class="text-[10px] font-black px-2 py-0.5 rounded uppercase <?php echo $style['bg'].' '.$style['text']; ?>">
                                        📢 <?php echo $style['label'];
                                        if ($sc==='batch'   && $ann['target_batch'])   echo ' · '.$ann['target_batch'];
                                        if ($sc==='program' && $ann['target_program']) echo ' · '.htmlspecialchars($ann['target_program']);
                                        ?>
                                    </span>
                                </div>
                                <h2 class="font-black text-slate-800 text-lg mb-3 group-hover:text-blue-700 transition-colors"><?php echo htmlspecialchars($ann['title']); ?></h2>
                                <p class="text-slate-500 text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($ann['body'])); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 mt-5 pt-4 border-t border-slate-50">
                            <?php echo renderAvatar($ann['author'], 'w-7 h-7 text-xs flex-shrink-0'); ?>
                            <span class="text-xs text-slate-400">Posted by <strong class="text-slate-600"><?php echo htmlspecialchars($ann['author']); ?></strong></span>
                            <span class="text-slate-200">•</span>
                            <span class="text-xs text-slate-400"><?php echo formatDate($ann['created_at']); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
