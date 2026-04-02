<?php
// pages/view-profile.php  — #5 MEDIUM: click-through alumni profile viewer
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$view_id   = (int)($_GET['id'] ?? 0);
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

if (!$view_id) { header('Location: directory.php'); exit; }

// Fetch the user + alumni profile
$stmt = $pdo->prepare(
    "SELECT u.id as user_id, u.name, u.email, u.role, u.created_at,
            a.student_id, a.program, a.batch_year, a.address, a.contact_no,
            a.advanced_degree, a.employment_status, a.company, a.job_title,
            a.position_level, a.discipline_match, a.years_experience, a.date_started
     FROM users u
     LEFT JOIN alumni a ON u.id = a.user_id
     WHERE u.id = ? AND u.role = 'alumni' AND u.is_active = 1"
);
$stmt->execute([$view_id]);
$person = $stmt->fetch();

if (!$person) { header('Location: directory.php?error=notfound'); exit; }

// Years of experience calculation
$exp_years = 0;
if (!empty($person['date_started'])) {
    $start     = new DateTime($person['date_started']);
    $exp_years = $start->diff(new DateTime())->y;
}

// Is this the viewer's own profile?
$is_own = ($view_id == $_SESSION['user_id']);

$emp_status_colors = [
    'Employed'      => 'bg-green-100 text-green-700',
    'Self-employed' => 'bg-blue-100 text-blue-700',
    'Freelancing'   => 'bg-teal-100 text-teal-700',
    'Unemployed'    => 'bg-red-100 text-red-600',
    'Studying'      => 'bg-amber-100 text-amber-700',
];
$emp_color = $emp_status_colors[$person['employment_status'] ?? ''] ?? 'bg-slate-100 text-slate-600';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($person['name']); ?> – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex">

<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-64">
    <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3 text-slate-400">
            <a href="javascript:history.back()" class="text-slate-400 hover:text-slate-600 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Alumni Profile</span>
        </div>
        <?php if ($is_own): ?>
            <a href="edit-profile.php" class="h-9 px-4 bg-blue-600 text-white font-bold text-xs rounded-xl hover:bg-blue-700 flex items-center gap-1.5 transition-all">✏️ Edit Profile</a>
        <?php endif; ?>
    </header>

    <div class="p-8 max-w-4xl mx-auto w-full">
        <!-- Hero Card -->
        <div class="bg-white rounded-[40px] shadow-sm border border-slate-100 overflow-hidden mb-6 relative">
            <!-- Background Strip -->
            <div class="h-28 bg-gradient-to-r from-blue-600 to-indigo-500 relative">
                <div class="absolute inset-0 opacity-10" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"60\" height=\"60\"><circle cx=\"30\" cy=\"30\" r=\"20\" fill=\"white\" opacity=\"0.15\"/></svg>');"></div>
            </div>
            <div class="px-10 pb-10">
                <div class="flex flex-col md:flex-row items-start md:items-end gap-6 -mt-14 mb-6">
                    <div class="w-28 h-28 flex-shrink-0 ring-4 ring-white rounded-full shadow-2xl">
                        <?php echo renderAvatar($person['name'], 'w-full h-full text-4xl'); ?>
                    </div>
                    <div class="flex-1 pb-2">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <?php if ($person['program']): ?><span class="px-3 py-1 bg-blue-50 text-blue-600 text-[10px] font-black rounded-full uppercase tracking-widest"><?php echo htmlspecialchars($person['program']); ?></span><?php endif; ?>
                            <?php if ($person['batch_year']): ?><span class="px-3 py-1 bg-slate-100 text-slate-500 text-[10px] font-black rounded-full uppercase tracking-widest">Batch <?php echo $person['batch_year']; ?></span><?php endif; ?>
                            <?php if ($person['employment_status']): ?><span class="px-3 py-1 text-[10px] font-black rounded-full uppercase <?php echo $emp_color; ?>"><?php echo htmlspecialchars($person['employment_status']); ?></span><?php endif; ?>
                        </div>
                        <h1 class="text-3xl font-black text-slate-800 tracking-tight"><?php echo htmlspecialchars($person['name']); ?></h1>
                        <?php if ($person['job_title'] || $person['company']): ?>
                            <p class="text-slate-500 mt-1 text-sm font-medium">
                                <?php echo htmlspecialchars($person['job_title'] ?? ''); ?>
                                <?php if ($person['job_title'] && $person['company']): ?><span class="text-slate-300 mx-2">at</span><?php endif; ?>
                                <strong class="text-slate-700"><?php echo htmlspecialchars($person['company'] ?? ''); ?></strong>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Badges -->
                <div class="flex flex-wrap gap-3 mb-6">
                    <?php if ($person['discipline_match']): ?>
                        <div class="flex items-center gap-2 bg-green-50 text-green-700 px-4 py-2 rounded-2xl text-xs font-bold border border-green-100">
                            <div class="w-1.5 h-1.5 bg-green-500 rounded-full animate-pulse"></div> In-Discipline
                        </div>
                    <?php endif; ?>
                    <?php if ($exp_years > 0): ?>
                        <div class="flex items-center gap-2 bg-purple-50 text-purple-700 px-4 py-2 rounded-2xl text-xs font-bold border border-purple-100">
                            🏆 <?php echo $exp_years; ?>+ Years Experience
                        </div>
                    <?php endif; ?>
                    <?php if ($person['advanced_degree']): ?>
                        <div class="flex items-center gap-2 bg-amber-50 text-amber-700 px-4 py-2 rounded-2xl text-xs font-bold border border-amber-100">
                            🎓 <?php echo htmlspecialchars($person['advanced_degree']); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($person['student_id'])): ?>
                        <div class="flex items-center gap-2 bg-slate-100 text-slate-600 px-4 py-2 rounded-2xl text-xs font-bold">
                            🪪 <?php echo htmlspecialchars($person['student_id']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Info Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php
                    $info = [
                        ['label'=>'Years in Industry', 'value'=> $person['years_experience'].' year'.((int)$person['years_experience']!==1?'s':''), 'icon'=>'⏳'],
                        ['label'=>'Started', 'value'=> !empty($person['date_started']) ? formatDate($person['date_started']) : '—', 'icon'=>'📅'],
                        ['label'=>'Member Since', 'value'=> formatDate($person['created_at']), 'icon'=>'🎓'],
                    ];
                    ?>
                    <?php foreach ($info as $i): ?>
                    <div class="bg-slate-50 rounded-2xl p-4">
                        <p class="text-xs text-slate-400 font-bold uppercase tracking-widest mb-1"><?php echo $i['label']; ?></p>
                        <p class="font-black text-slate-800 flex items-center gap-2"><?php echo $i['icon']; ?> <?php echo htmlspecialchars($i['value']); ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Contact Info (only if own profile or admin) -->
        <?php if ($is_own || $_SESSION['user_role'] === 'admin'): ?>
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50">
                <h2 class="font-black text-slate-700 uppercase tracking-tighter text-sm">Contact Information <span class="text-slate-300 font-normal normal-case text-xs">(visible to you only)</span></h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php if ($person['email']): ?>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-blue-50 text-blue-500 rounded-xl flex items-center justify-center flex-shrink-0">✉️</div>
                    <div><p class="text-xs text-slate-400 font-bold">Email</p><p class="text-sm text-slate-700 font-medium"><?php echo htmlspecialchars($person['email']); ?></p></div>
                </div>
                <?php endif; ?>
                <?php if ($person['contact_no']): ?>
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-green-50 text-green-500 rounded-xl flex items-center justify-center flex-shrink-0">📱</div>
                    <div><p class="text-xs text-slate-400 font-bold">Contact</p><p class="text-sm text-slate-700 font-medium"><?php echo htmlspecialchars($person['contact_no']); ?></p></div>
                </div>
                <?php endif; ?>
                <?php if ($person['address']): ?>
                <div class="flex items-center gap-3 md:col-span-2">
                    <div class="w-10 h-10 bg-amber-50 text-amber-500 rounded-xl flex items-center justify-center flex-shrink-0">📍</div>
                    <div><p class="text-xs text-slate-400 font-bold">Address</p><p class="text-sm text-slate-700 font-medium"><?php echo htmlspecialchars($person['address']); ?></p></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</main>
</body>
</html>
