<?php
// pages/announcements.php  — Admin: Manage Communications & Events
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'alumni';
$success = $error = null;

// Handle RSVP (New Consolidated Logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rsvp'])) {
    if (!verifyCsrf()) { $error = "Security mismatch."; }
    else {
        $ev_id  = (int)$_POST['event_id'];
        $status = $_POST['status'] ?? 'Going';
        $stmt = $pdo->prepare("INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?,?,?) 
                               ON DUPLICATE KEY UPDATE status = VALUES(status)");
        if ($stmt->execute([$ev_id, $user_id])) {
            $success = "RSVP Updated for event!";
        }
    }
}

// Ensure schema is updated for Category
try {
    $pdo->exec("ALTER TABLE announcements ADD COLUMN IF NOT EXISTS category ENUM('Announcement', 'Event') DEFAULT 'Announcement'");
} catch(Exception $e) {}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user_role !== 'admin') { $error = "Unauthorized action."; }
    elseif (!verifyCsrf()) { $error = "Security mismatch."; }
    else {
        $title          = trim($_POST['title'] ?? '');
        $body           = trim($_POST['body']  ?? '');
        $category       = $_POST['category'] ?? 'Announcement';
        $scope          = $_POST['scope'] ?? 'all';
        $target_batch   = !empty($_POST['target_batch'])   ? (int)$_POST['target_batch']   : null;
        $target_program = !empty($_POST['target_program']) ? trim($_POST['target_program']) : null;

        if ($title && $body) {
            // Insert announcement
            $stmt = $pdo->prepare("INSERT INTO announcements (title, body, category, scope, target_batch, target_program, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$title, $body, $category, $scope, $target_batch, $target_program, $user_id]);

            // Fan-out notifications (simplified)
            $notif_text = ($category === 'Event' ? "📅 Event: " : "📢 ") . $title;
            
            $query = "SELECT id FROM users WHERE role = 'alumni' AND is_active = 1";
            if ($scope === 'batch') $query .= " AND id IN (SELECT user_id FROM alumni WHERE batch_year = $target_batch)";
            if ($scope === 'program') $query .= " AND id IN (SELECT user_id FROM alumni WHERE program = '$target_program')";
            
            $users = $pdo->query($query)->fetchAll(PDO::FETCH_COLUMN);
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'system', ?)");
            foreach ($users as $uid) { $notif->execute([$uid, $notif_text]); }

            $success = "Successfully posted as " . strtoupper($category) . " to " . count($users) . " alumni!";
        } else {
            $error = "Title and body are required.";
        }
    }
}

// Fetch history (with RSVP status for events)
$announcements = $pdo->query("
    SELECT a.*, u.name AS author,
    (SELECT status FROM event_rsvps WHERE event_id = a.id AND user_id = $user_id) as my_rsvp
    FROM announcements a 
    JOIN users u ON a.created_by = u.id 
    ORDER BY a.created_at DESC LIMIT 50
")->fetchAll();
$batches  = $pdo->query("SELECT DISTINCT batch_year FROM alumni ORDER BY batch_year DESC")->fetchAll(PDO::FETCH_COLUMN);
$programs = $pdo->query("SELECT DISTINCT program FROM alumni ORDER BY program")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-slate-50 min-h-screen flex">
<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-8 flex items-center justify-between sticky top-0 z-30 transition-all">
        <div class="flex items-center gap-3 text-slate-400">
            <span class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tighter"><?php echo $user_role === 'admin' ? 'Communications' : 'Alumni News'; ?></span>
            <span class="w-1 h-1 bg-slate-200 dark:bg-slate-700 rounded-full"></span>
            <span class="text-xs italic"><?php echo $user_role === 'admin' ? 'Institutional Hub' : 'Latest Updates'; ?></span>
        </div>
    </header>

    <div class="p-8 max-w-6xl mx-auto w-full">
        <div class="mb-10 <?php echo $user_role !== 'admin' ? 'text-center' : ''; ?>">
            <h1 class="text-3xl font-black text-slate-800 dark:text-white italic uppercase tracking-tighter">
                <?php echo $user_role === 'admin' ? 'ANNOUNCEMENT CENTER' : 'LATEST NEWS & UPDATES'; ?>
            </h1>
            <p class="text-slate-400 dark:text-slate-500 text-sm font-medium">
                <?php echo $user_role === 'admin' ? 'Issue official announcements and scheduled alumni events.' : 'Stay connected with formal institutional messages and upcoming events.'; ?>
            </p>
        </div>

        <?php if ($success): ?><div class="mb-6 bg-blue-50 text-blue-700 p-4 rounded-2xl text-sm font-bold border border-blue-100 italic">✅ <?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-6 bg-red-100 text-red-600 p-4 rounded-2xl text-sm font-bold border border-red-100">⚠️ <?php echo $error; ?></div><?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
            <?php if ($user_role === 'admin'): ?>
            <!-- Composer -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-[40px] shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-slate-900 p-8 text-white">
                        <h2 class="text-2xl font-black italic uppercase tracking-tighter">Compose</h2>
                        <p class="text-slate-400 text-xs mt-1 font-medium uppercase tracking-widest">Formal Network Message</p>
                    </div>
                    <form method="POST" class="p-8 space-y-4">
                        <?php echo csrfField(); ?>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[3px] mb-2">Category</label>
                            <div class="flex gap-2">
                                <label class="flex-1">
                                    <input type="radio" name="category" value="Announcement" checked class="hidden peer">
                                    <div class="h-11 flex items-center justify-center rounded-2xl bg-slate-50 border border-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-400 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 cursor-pointer transition-all">Announcement</div>
                                </label>
                                <label class="flex-1">
                                    <input type="radio" name="category" value="Event" class="hidden peer">
                                    <div class="h-11 flex items-center justify-center rounded-2xl bg-slate-50 border border-slate-100 text-[10px] font-black uppercase tracking-widest text-slate-400 peer-checked:bg-rose-500 peer-checked:text-white peer-checked:border-rose-500 cursor-pointer transition-all">Event</div>
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[3px] mb-2">Subject Title</label>
                            <input type="text" name="title" required class="w-full h-12 bg-slate-50 rounded-2xl px-5 outline-none focus:ring-4 focus:ring-blue-100 transition-all font-bold text-slate-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[3px] mb-2">Message Body</label>
                            <textarea name="body" rows="4" required class="w-full bg-slate-50 rounded-2xl px-5 py-4 outline-none focus:ring-4 focus:ring-blue-100 transition-all font-medium text-slate-800 resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[3px] mb-2">Reach Audience</label>
                            <select name="scope" id="scope_select" onchange="toggleTarget(this.value)" class="w-full h-12 bg-slate-50 rounded-2xl px-5 outline-none focus:ring-4 focus:ring-blue-100 transition-all font-bold text-slate-800 cursor-pointer">
                                <option value="all">Global (All Alumni)</option>
                                <option value="batch">Target Specific Batch</option>
                                <option value="program">Target Specific Program</option>
                            </select>
                        </div>
                        <div id="batch_field" class="hidden">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[3px] mb-2">Batch Year</label>
                            <select name="target_batch" class="w-full h-12 bg-slate-50 rounded-2xl px-5 border-2 border-amber-100 outline-none font-bold">
                                <?php foreach ($batches as $b): ?><option><?php echo $b; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div id="program_field" class="hidden">
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-[3px] mb-2">Degree Program</label>
                            <select name="target_program" class="w-full h-12 bg-slate-50 rounded-2xl px-5 border-2 border-purple-100 outline-none font-bold text-[11px]">
                                <?php foreach ($programs as $p): ?><option><?php echo $p; ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="w-full h-14 bg-blue-600 text-white rounded-2xl font-black shadow-lg shadow-blue-100 hover:bg-blue-700 transition-all uppercase tracking-widest text-xs">Post Announcement</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- News Feed / Logs -->
            <div class="<?php echo ($user_role === 'admin') ? 'lg:col-span-3' : 'lg:col-span-5 max-w-4xl mx-auto'; ?> space-y-6">
                <h2 class="text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-[3px] mb-4 <?php echo $user_role !== 'admin' ? 'text-center' : ''; ?>">
                    <?php echo $user_role === 'admin' ? 'Announcement History' : 'TODAY\'S FEED'; ?>
                </h2>
                <div class="bg-white dark:bg-slate-900 rounded-[40px] shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden divide-y divide-slate-50 dark:divide-slate-800/50 transition-all">
                    <?php if (empty($announcements)): ?>
                        <p class="p-12 text-center text-xs text-slate-300 italic">No announcements recorded.</p>
                    <?php endif; ?>
                    <?php foreach ($announcements as $ann): ?>
                    <div class="p-8 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                        <div class="flex items-start justify-between gap-6">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="px-3 py-1 text-[8px] font-black rounded-full uppercase tracking-widest <?php echo $ann['category']==='Event' ? 'bg-rose-50 dark:bg-rose-900/30 text-rose-500' : 'bg-blue-50 dark:bg-blue-900/30 text-blue-600'; ?>">
                                        <?php echo $ann['category']; ?>
                                    </span>
                                    <span class="text-[9px] font-black text-slate-300 dark:text-slate-600 uppercase tracking-[2px]"><?php echo strtoupper($ann['scope']); ?></span>
                                </div>
                                <h3 class="text-lg font-black text-slate-800 dark:text-white uppercase tracking-tighter italic group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors"><?php echo htmlspecialchars($ann['title']); ?></h3>
                                <p class="text-slate-400 dark:text-slate-500 text-xs mt-2 leading-relaxed italic"><?php echo nl2br(htmlspecialchars($ann['body'])); ?></p>
                                
                                <?php if ($ann['category'] === 'Event'): ?>
                                <div class="mt-6 flex flex-wrap items-center gap-2">
                                    <form method="POST" class="flex gap-1.5 bg-slate-100 dark:bg-slate-800 p-1.5 rounded-2xl border border-slate-200 dark:border-slate-700">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="event_id" value="<?php echo $ann['id']; ?>">
                                        <input type="hidden" name="rsvp" value="1">
                                        
                                        <button type="submit" name="status" value="Going" class="px-4 h-8 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all <?php echo $ann['my_rsvp'] === 'Going' ? 'bg-blue-600 text-white shadow-lg' : 'text-slate-400 dark:text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700'; ?>">Going</button>
                                        <button type="submit" name="status" value="Maybe" class="px-4 h-8 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all <?php echo $ann['my_rsvp'] === 'Maybe' ? 'bg-amber-600/20 text-amber-600 dark:text-amber-400 border border-amber-500/20' : 'text-slate-400 dark:text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700'; ?>">Maybe</button>
                                        <button type="submit" name="status" value="Declined" class="px-4 h-8 rounded-xl text-[9px] font-black uppercase tracking-widest transition-all <?php echo $ann['my_rsvp'] === 'Declined' ? 'bg-red-600/20 text-red-600 dark:text-red-400 border border-red-500/20' : 'text-slate-400 dark:text-slate-500 hover:bg-slate-200 dark:hover:bg-slate-700'; ?>">Skip</button>
                                    </form>
                                    <?php if ($ann['my_rsvp']): ?>
                                        <span class="text-[9px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest ml-2 animate-pulse">Confirmed!</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <p class="text-[10px] font-black text-slate-300 dark:text-slate-600 uppercase tracking-widest mb-1"><?php echo date('M d', strtotime($ann['created_at'])); ?></p>
                                <p class="text-[8px] font-bold text-slate-400 dark:text-slate-500 uppercase italic">via <?php echo htmlspecialchars($ann['author']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function toggleTarget(val) {
    document.getElementById('batch_field').classList.toggle('hidden', val !== 'batch');
    document.getElementById('program_field').classList.toggle('hidden', val !== 'program');
}
</script>
</body>
</html>
