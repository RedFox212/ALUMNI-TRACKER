<?php
// pages/polls.php  — MEDIUM
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: dashboard.php'); exit; }

$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$success = $error = null;

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_poll') {
        $title          = trim($_POST['title'] ?? '');
        $description    = trim($_POST['description'] ?? '');
        $poll_type      = $_POST['poll_type'] ?? 'single';
        $visibility     = $_POST['visibility'] ?? 'all';
        $target_batch   = !empty($_POST['target_batch'])   ? (int)$_POST['target_batch']   : null;
        $target_program = !empty($_POST['target_program']) ? trim($_POST['target_program']) : null;
        $is_anonymous   = isset($_POST['is_anonymous']) ? 1 : 0;
        $open_date      = !empty($_POST['open_date'])  ? $_POST['open_date']  : null;
        $close_date     = !empty($_POST['close_date']) ? $_POST['close_date'] : null;
        $options        = array_filter(array_map('trim', $_POST['options'] ?? []), fn($o) => $o !== '');

        if ($title && count($options) >= 2) {
            $stmt = $pdo->prepare("INSERT INTO polls (title, description, poll_type, visibility, target_batch, target_program, is_anonymous, open_date, close_date, created_by)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $description, $poll_type, $visibility, $target_batch, $target_program, $is_anonymous, $open_date, $close_date, $_SESSION['user_id']]);
            $poll_id = $pdo->lastInsertId();

            $opt_stmt = $pdo->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
            foreach ($options as $opt) { $opt_stmt->execute([$poll_id, $opt]); }

            $success = "Poll \"$title\" created successfully!";
        } else {
            $error = "Poll title and at least 2 options are required.";
        }

    } elseif ($action === 'delete_poll') {
        $poll_id = (int)($_POST['poll_id'] ?? 0);
        if ($poll_id) {
            $pdo->prepare("DELETE FROM polls WHERE id = ?")->execute([$poll_id]);
            $success = "Poll deleted.";
        }

    } elseif ($action === 'close_poll') {
        $poll_id = (int)($_POST['poll_id'] ?? 0);
        if ($poll_id) {
            $pdo->prepare("UPDATE polls SET close_date = NOW() WHERE id = ?")->execute([$poll_id]);
            $success = "Poll closed.";
        }
    }
}

// Fetch polls with vote counts
$polls = $pdo->query(
    "SELECT p.*, u.name AS author,
        (SELECT COUNT(DISTINCT pv.user_id) FROM poll_votes pv WHERE pv.poll_id = p.id) AS respondents
     FROM polls p JOIN users u ON p.created_by = u.id
     ORDER BY p.created_at DESC"
)->fetchAll();

$batches  = $pdo->query("SELECT DISTINCT batch_year FROM alumni ORDER BY batch_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$programs = $pdo->query("SELECT DISTINCT program FROM alumni ORDER BY program")->fetchAll(PDO::FETCH_COLUMN);

// Fetch results for each poll
$poll_results = [];
foreach ($polls as $p) {
    $opts = $pdo->prepare(
        "SELECT po.option_text, COUNT(pv.id) as votes
         FROM poll_options po
         LEFT JOIN poll_votes pv ON po.id = pv.option_id
         WHERE po.poll_id = ?
         GROUP BY po.id, po.option_text ORDER BY votes DESC"
    );
    $opts->execute([$p['id']]);
    $poll_results[$p['id']] = $opts->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Polls & Events – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; } .modal { display:none; } .modal.open { display:flex; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex">

<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3 text-slate-400">
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Polls & Events</span>
            <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
            <span class="text-xs">Alumni Engagement</span>
        </div>
        <button onclick="openCreateModal()" class="h-9 px-5 bg-blue-600 text-white rounded-xl font-bold text-sm hover:bg-blue-700 active:scale-95 transition-all shadow-lg shadow-blue-200">
            + New Poll
        </button>
    </header>

    <div class="p-8">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 italic">POLLS & EVENTS</h1>
            <p class="text-slate-400 text-sm font-medium">Create, manage, and analyze alumni polls.</p>
        </div>

        <?php if ($success): ?><div class="mb-6 bg-green-50 border border-green-100 text-green-700 px-5 py-3 rounded-2xl text-sm font-medium flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-5 py-3 rounded-2xl text-sm font-medium"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Poll Cards -->
        <?php if (empty($polls)): ?>
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 py-20 text-center text-slate-300">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 mx-auto mb-4 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="font-medium italic">No polls yet. Create your first poll!</p>
                <button onclick="openCreateModal()" class="mt-4 px-6 py-3 bg-blue-600 text-white rounded-xl font-bold text-sm hover:bg-blue-700 transition-all">+ Create Poll</button>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($polls as $poll): ?>
                    <?php
                    $now = new DateTime();
                    $close = $poll['close_date'] ? new DateTime($poll['close_date']) : null;
                    $is_open = !$close || $close > $now;
                    $total_votes = array_sum(array_column($poll_results[$poll['id']] ?? [], 'votes'));
                    $vis_colors = ['all'=>'bg-blue-100 text-blue-600','batch'=>'bg-amber-100 text-amber-600','program'=>'bg-purple-100 text-purple-600'];
                    ?>
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-lg transition-all">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4 mb-4">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-2 flex-wrap">
                                        <span class="text-[10px] font-black px-2 py-0.5 rounded uppercase <?php echo $is_open ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-500'; ?>">
                                            <?php echo $is_open ? 'OPEN' : 'CLOSED'; ?>
                                        </span>
                                        <span class="text-[10px] font-black px-2 py-0.5 rounded uppercase <?php echo $vis_colors[$poll['visibility']] ?? 'bg-slate-100 text-slate-500'; ?>">
                                            <?php echo strtoupper($poll['visibility'] ?? 'ALL');
                                            if ($poll['visibility']==='batch' && $poll['target_batch']) echo ' '.$poll['target_batch'];
                                            if ($poll['visibility']==='program' && $poll['target_program']) echo ' · '.$poll['target_program'];
                                            ?>
                                        </span>
                                        <span class="text-[10px] font-black px-2 py-0.5 rounded uppercase bg-indigo-100 text-indigo-600"><?php echo strtoupper($poll['poll_type']); ?></span>
                                    </div>
                                    <h3 class="font-bold text-slate-800 leading-tight"><?php echo htmlspecialchars($poll['title']); ?></h3>
                                    <?php if ($poll['description']): ?>
                                        <p class="text-slate-400 text-xs mt-1"><?php echo htmlspecialchars($poll['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-2xl font-black text-slate-800"><?php echo $poll['respondents']; ?></p>
                                    <p class="text-[10px] text-slate-400 uppercase tracking-widest font-bold">Voters</p>
                                </div>
                            </div>

                            <!-- Results Bars -->
                            <?php if (!empty($poll_results[$poll['id']])): ?>
                                <div class="space-y-2 mt-4">
                                    <?php foreach ($poll_results[$poll['id']] as $opt):
                                        $pct = $total_votes > 0 ? round(($opt['votes'] / $total_votes) * 100) : 0;
                                    ?>
                                        <div>
                                            <div class="flex justify-between text-xs mb-1">
                                                <span class="text-slate-600 font-medium truncate max-w-[70%]"><?php echo htmlspecialchars($opt['option_text']); ?></span>
                                                <span class="text-slate-400 font-bold"><?php echo $opt['votes']; ?> (<?php echo $pct; ?>%)</span>
                                            </div>
                                            <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-blue-500 to-blue-400 rounded-full transition-all duration-500" style="width:<?php echo $pct; ?>%"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Meta + Actions -->
                            <div class="flex items-center justify-between mt-5 pt-4 border-t border-slate-50">
                                <div>
                                    <p class="text-[10px] text-slate-400">Created <?php echo formatDate($poll['created_at']); ?> by <?php echo htmlspecialchars($poll['author']); ?></p>
                                    <?php if ($poll['close_date']): ?>
                                        <p class="text-[10px] text-slate-400">Closes: <?php echo formatDate($poll['close_date']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-2">
                                    <?php if ($is_open): ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Close this poll now?')">
                                        <input type="hidden" name="action" value="close_poll">
                                        <input type="hidden" name="poll_id" value="<?php echo $poll['id']; ?>">
                                        <button type="submit" class="text-xs px-3 py-1.5 bg-amber-50 text-amber-600 rounded-lg font-bold hover:bg-amber-100 transition-all">Close Poll</button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" class="inline" onsubmit="return confirm('Delete this poll and all its data?')">
                                        <input type="hidden" name="action" value="delete_poll">
                                        <input type="hidden" name="poll_id" value="<?php echo $poll['id']; ?>">
                                        <button type="submit" class="text-xs px-3 py-1.5 bg-red-50 text-red-500 rounded-lg font-bold hover:bg-red-100 transition-all">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Create Poll Modal -->
<div id="createModal" class="modal fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-xl shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="bg-blue-600 p-6 rounded-t-3xl sticky top-0 z-10">
            <h2 class="text-white font-black text-xl italic">CREATE NEW POLL</h2>
            <p class="text-blue-200 text-xs mt-1">Fill in all details to publish a poll.</p>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="create_poll">
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Poll Title *</label>
                <input type="text" name="title" required placeholder="e.g. Will you attend Homecoming 2026?" class="w-full h-11 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm">
            </div>
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Description</label>
                <textarea name="description" rows="2" placeholder="Optional description..." class="w-full bg-slate-50 rounded-xl px-4 py-3 outline-none focus:ring-4 focus:ring-blue-100 text-sm resize-none"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Poll Type</label>
                    <select name="poll_type" class="w-full h-11 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm cursor-pointer">
                        <option value="single">Single Choice</option>
                        <option value="multiple">Multiple Choice</option>
                        <option value="yesno">Yes / No</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Visibility</label>
                    <select name="visibility" id="poll_vis" onchange="togglePollTarget(this.value)" class="w-full h-11 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm cursor-pointer">
                        <option value="all">All Alumni</option>
                        <option value="batch">By Batch</option>
                        <option value="program">By Program</option>
                    </select>
                </div>
            </div>
            <div id="poll_batch_field" class="hidden">
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Target Batch</label>
                <select name="target_batch" class="w-full h-11 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm">
                    <option value="">Select Batch</option>
                    <?php foreach ($batches as $b): ?><option value="<?php echo $b; ?>"><?php echo $b; ?></option><?php endforeach; ?>
                </select>
            </div>
            <div id="poll_program_field" class="hidden">
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Target Program</label>
                <select name="target_program" class="w-full h-11 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm">
                    <option value="">Select Program</option>
                    <?php foreach ($programs as $p): ?><option value="<?php echo htmlspecialchars($p); ?>"><?php echo htmlspecialchars($p); ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Open Date</label>
                    <input type="datetime-local" name="open_date" class="w-full h-11 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Close Date</label>
                    <input type="datetime-local" name="close_date" class="w-full h-11 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm">
                </div>
            </div>
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="is_anonymous" class="rounded">
                <span class="text-sm font-medium text-slate-600">Anonymous voting</span>
            </label>

            <!-- Poll Options -->
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Poll Options * (min 2)</label>
                <div id="options_list" class="space-y-2">
                    <div class="flex gap-2"><input type="text" name="options[]" placeholder="Option 1" class="flex-1 h-10 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm"><button type="button" onclick="removeOption(this)" class="w-10 h-10 bg-red-50 text-red-400 rounded-xl hover:bg-red-100 font-bold transition-all text-lg">×</button></div>
                    <div class="flex gap-2"><input type="text" name="options[]" placeholder="Option 2" class="flex-1 h-10 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm"><button type="button" onclick="removeOption(this)" class="w-10 h-10 bg-red-50 text-red-400 rounded-xl hover:bg-red-100 font-bold transition-all text-lg">×</button></div>
                </div>
                <button type="button" onclick="addOption()" class="mt-2 text-sm font-bold text-blue-600 hover:text-blue-700 flex items-center gap-1">+ Add Option</button>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 h-11 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-all shadow-lg shadow-blue-200">Publish Poll</button>
                <button type="button" onclick="closeModal('createModal')" class="flex-1 h-11 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition-all">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
let optCount = 2;
function addOption() {
    optCount++;
    const div = document.createElement('div');
    div.className = 'flex gap-2';
    div.innerHTML = `<input type="text" name="options[]" placeholder="Option ${optCount}" class="flex-1 h-10 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm"><button type="button" onclick="removeOption(this)" class="w-10 h-10 bg-red-50 text-red-400 rounded-xl hover:bg-red-100 font-bold transition-all text-lg">×</button>`;
    document.getElementById('options_list').appendChild(div);
}
function removeOption(btn) {
    const list = document.getElementById('options_list');
    if (list.children.length > 2) btn.parentElement.remove();
}
function openCreateModal() { document.getElementById('createModal').classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function togglePollTarget(val) {
    document.getElementById('poll_batch_field').classList.toggle('hidden', val !== 'batch');
    document.getElementById('poll_program_field').classList.toggle('hidden', val !== 'program');
}
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));
</script>
</body>
</html>
