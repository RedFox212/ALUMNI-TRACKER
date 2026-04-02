<?php
// pages/nominate-spotlight.php  — #6 MEDIUM-HARD: nomination form + pending status
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$success   = $error = null;

// Handle nomination submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nominee_user_id = (int)($_POST['nominee_user_id'] ?? 0);
    $quote           = trim($_POST['quote']       ?? '');
    $achievement     = trim($_POST['achievement'] ?? '');

    if (!$nominee_user_id || !$quote || !$achievement) {
        $error = "All fields are required.";
    } else {
        // Get alumni.id from user_id
        $astmt = $pdo->prepare("SELECT id FROM alumni WHERE user_id = ?");
        $astmt->execute([$nominee_user_id]);
        $alumni_rec = $astmt->fetch();
        if (!$alumni_rec) {
            $error = "Selected alumni profile not found.";
        } else {
            // Check no pending/active spotlight for this alumni already
            $chk = $pdo->prepare("SELECT COUNT(*) FROM spotlights WHERE alumni_id = ? AND status IN ('pending','active')");
            $chk->execute([$alumni_rec['id']]);
            if ($chk->fetchColumn() > 0) {
                $error = "This alumni already has an active or pending spotlight nomination.";
            } else {
                $pdo->prepare("INSERT INTO spotlights (alumni_id, quote, achievement, status, rotation_start, created_by) VALUES (?, ?, ?, 'pending', CURDATE(), ?)")
                    ->execute([$alumni_rec['id'], $quote, $achievement, $user_id]);
                $success = "🎉 Nomination submitted! An admin will review and approve it shortly.";
            }
        }
    }
}

// Fetch alumni list (excluding self) for the dropdown search
$alumni_list = $pdo->query(
    "SELECT u.id, u.name, a.program, a.batch_year, a.job_title, a.company
     FROM users u JOIN alumni a ON u.id = a.user_id
     WHERE u.is_active = 1 ORDER BY u.name ASC"
)->fetchAll();

// Fetch own nominations
$my_noms = $pdo->query(
    "SELECT s.*, u.name AS nominee_name, a.program, a.batch_year
     FROM spotlights s
     JOIN alumni a ON s.alumni_id = a.id
     JOIN users u ON a.user_id = u.id
     WHERE s.created_by = $user_id
     ORDER BY s.created_at DESC"
)->fetchAll();

$status_styles = [
    'pending'  => 'bg-amber-100 text-amber-600',
    'active'   => 'bg-green-100 text-green-700',
    'archived' => 'bg-slate-100 text-slate-500',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nominate for Spotlight – LATS</title>
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
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Nominate for Spotlight</span>
        </div>
    </header>

    <div class="p-8 max-w-5xl mx-auto w-full">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 italic">ALUMNI SPOTLIGHT NOMINATION</h1>
            <p class="text-slate-400 text-sm font-medium">Celebrate a fellow alumnus's achievement by nominating them for the Alumni Spotlight.</p>
        </div>

        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border border-green-100 text-green-700 px-5 py-4 rounded-2xl text-sm font-medium flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-5 py-4 rounded-2xl text-sm font-medium"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <!-- Nomination Form -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-amber-500 to-orange-400 p-6">
                        <h2 class="text-white font-black text-xl italic flex items-center gap-2">⭐ Submit Nomination</h2>
                        <p class="text-amber-100 text-xs mt-1">All nominations are reviewed by an admin before going live.</p>
                    </div>
                    <div class="p-6">
                        <form method="POST" id="nomForm" class="space-y-5">
                            <!-- Search + Select alumni -->
                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Search & Select Nominee *</label>
                                <input type="text" id="search_alumni" placeholder="Type name to search..."
                                    class="w-full h-11 bg-slate-50 rounded-xl px-4 mb-2 outline-none focus:ring-4 focus:ring-amber-100 text-sm"
                                    oninput="filterAlumni(this.value)">
                                <div id="alumni_dropdown" class="max-h-48 overflow-y-auto bg-slate-50 rounded-xl divide-y divide-white border border-slate-100 hidden">
                                    <?php foreach ($alumni_list as $al): ?>
                                    <button type="button"
                                        onclick="selectAlumni(<?php echo $al['id']; ?>, '<?php echo htmlspecialchars(addslashes($al['name'])); ?>', '<?php echo htmlspecialchars($al['program']??''); ?>', '<?php echo $al['batch_year']??''; ?>')"
                                        class="w-full text-left px-4 py-3 hover:bg-amber-50 transition-all flex items-center gap-3 alumni-item">
                                        <?php echo renderAvatar($al['name'], 'w-9 h-9 text-xs flex-shrink-0'); ?>
                                        <div>
                                            <p class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars($al['name']); ?></p>
                                            <p class="text-[10px] text-slate-400"><?php echo htmlspecialchars($al['program']??''); ?> · Batch <?php echo $al['batch_year']??''; ?></p>
                                        </div>
                                    </button>
                                    <?php endforeach; ?>
                                </div>
                                <input type="hidden" name="nominee_user_id" id="nominee_user_id" required>
                                <div id="selected_nominee" class="hidden bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-center gap-3 mt-2">
                                    <div id="sel_avatar" class="flex-shrink-0"></div>
                                    <div>
                                        <p id="sel_name" class="font-bold text-amber-800 text-sm"></p>
                                        <p id="sel_info" class="text-xs text-amber-600"></p>
                                    </div>
                                    <button type="button" onclick="clearSelection()" class="ml-auto text-amber-400 hover:text-amber-600 font-bold text-lg">×</button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Their Quote *</label>
                                <textarea name="quote" required rows="3"
                                    placeholder='e.g. "LATS helped me reconnect with my batch and grow professionally..."'
                                    class="w-full bg-slate-50 rounded-xl px-4 py-3 outline-none focus:ring-4 focus:ring-amber-100 text-sm resize-none"></textarea>
                                <p class="text-[10px] text-slate-400 mt-1">Write a quote from or about this alumnus.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Their Achievement *</label>
                                <textarea name="achievement" required rows="3"
                                    placeholder="e.g. Lead Software Engineer at GCash, pioneering mobile payments in PH."
                                    class="w-full bg-slate-50 rounded-xl px-4 py-3 outline-none focus:ring-4 focus:ring-amber-100 text-sm resize-none"></textarea>
                                <p class="text-[10px] text-slate-400 mt-1">Describe their professional achievement or career milestone.</p>
                            </div>

                            <button type="submit" class="w-full h-12 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 active:scale-95 transition-all shadow-lg shadow-amber-200 text-sm">
                                ⭐ Submit Nomination
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- My Nominations -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-5 border-b border-slate-50 bg-slate-50/50">
                        <h2 class="font-black text-slate-800 uppercase tracking-tighter text-sm">My Nominations</h2>
                    </div>
                    <?php if (empty($my_noms)): ?>
                        <div class="py-14 text-center text-slate-300 px-6">
                            <div class="text-4xl mb-3">✨</div>
                            <p class="font-medium italic text-sm">You haven't nominated anyone yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-50">
                            <?php foreach ($my_noms as $nom):
                                $ss = $status_styles[$nom['status']] ?? 'bg-slate-100 text-slate-500';
                            ?>
                            <div class="p-5">
                                <div class="flex items-start justify-between gap-3 mb-2">
                                    <div class="flex items-center gap-2">
                                        <?php echo renderAvatar($nom['nominee_name'], 'w-9 h-9 flex-shrink-0 text-xs'); ?>
                                        <div>
                                            <p class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($nom['nominee_name']); ?></p>
                                            <p class="text-[10px] text-slate-400"><?php echo htmlspecialchars($nom['program']??''); ?> · <?php echo $nom['batch_year']??''; ?></p>
                                        </div>
                                    </div>
                                    <span class="text-[10px] font-black px-2 py-0.5 rounded uppercase <?php echo $ss; ?>"><?php echo $nom['status']; ?></span>
                                </div>
                                <p class="text-xs text-slate-500 italic line-clamp-2">"<?php echo htmlspecialchars($nom['quote']); ?>"</p>
                                <p class="text-[10px] text-slate-400 mt-2"><?php echo formatDate($nom['created_at']); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- How it works -->
                <div class="bg-amber-50 rounded-3xl shadow-sm border border-amber-100 p-6 mt-4">
                    <h3 class="font-black text-amber-800 text-sm uppercase tracking-tighter mb-3">How it Works</h3>
                    <div class="space-y-3">
                        <?php foreach ([['1','Submit','Fill out the nomination form','amber-600'],['2','Review','Admin approves the nomination','blue-600'],['3','Published','Appears on the alumni dashboard','green-600']] as [$num,$title,$desc,$color]): ?>
                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 bg-white rounded-full flex items-center justify-center text-xs font-black text-<?php echo $color; ?> flex-shrink-0 shadow-sm"><?php echo $num; ?></div>
                            <div><p class="text-xs font-bold text-amber-800"><?php echo $title; ?></p><p class="text-[10px] text-amber-600"><?php echo $desc; ?></p></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function filterAlumni(q) {
    const dd = document.getElementById('alumni_dropdown');
    const items = dd.querySelectorAll('.alumni-item');
    const norm = q.toLowerCase().trim();
    if (!norm) { dd.classList.add('hidden'); return; }
    dd.classList.remove('hidden');
    items.forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(norm) ? '' : 'none';
    });
}

function selectAlumni(id, name, program, batch) {
    document.getElementById('nominee_user_id').value = id;
    document.getElementById('search_alumni').value = name;
    document.getElementById('alumni_dropdown').classList.add('hidden');
    document.getElementById('sel_name').textContent = name;
    document.getElementById('sel_info').textContent = program + (batch ? ' · Batch ' + batch : '');
    document.getElementById('selected_nominee').classList.remove('hidden');
}

function clearSelection() {
    document.getElementById('nominee_user_id').value = '';
    document.getElementById('search_alumni').value = '';
    document.getElementById('selected_nominee').classList.add('hidden');
}

document.addEventListener('click', e => {
    if (!e.target.closest('#alumni_dropdown') && !e.target.closest('#search_alumni')) {
        document.getElementById('alumni_dropdown').classList.add('hidden');
    }
});
</script>
</body>
</html>
