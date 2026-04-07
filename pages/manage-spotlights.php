<?php
// pages/manage-spotlights.php — Admin: approve/reject spotlight nominations
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: dashboard.php'); exit; }

$user_name = $_SESSION['user_name'];
$success = $error = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action      = $_POST['action']      ?? '';
    $spotlight_id = (int)($_POST['spotlight_id'] ?? 0);

    if ($action === 'approve' && $spotlight_id) {
        // Archive any currently active spotlight first
        $pdo->query("UPDATE spotlights SET status='archived' WHERE status='active'");
        $pdo->prepare("UPDATE spotlights SET status='active', rotation_start=CURDATE() WHERE id=?")
            ->execute([$spotlight_id]);
        // Notify the nominated alumni
        $nom = $pdo->prepare("SELECT u.id as uid, u.name FROM spotlights s JOIN alumni a ON s.alumni_id=a.id JOIN users u ON a.user_id=u.id WHERE s.id=?");
        $nom->execute([$spotlight_id]);
        $nominee = $nom->fetch();
        if ($nominee) {
            $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'spotlight', ?)")
                ->execute([$nominee['uid'], "⭐ Congratulations! You've been featured in the Alumni Spotlight!"]);
        }
        $success = "Spotlight approved and is now live!";

    } elseif ($action === 'reject' && $spotlight_id) {
        $pdo->prepare("UPDATE spotlights SET status='archived' WHERE id=?")->execute([$spotlight_id]);
        $success = "Nomination rejected.";

    } elseif ($action === 'archive' && $spotlight_id) {
        $pdo->prepare("UPDATE spotlights SET status='archived' WHERE id=?")->execute([$spotlight_id]);
        $success = "Spotlight archived.";

    } elseif ($action === 'create_manual') {
        // Admin creates a spotlight manually
        $nominee_uid  = (int)($_POST['nominee_uid'] ?? 0);
        $quote        = trim($_POST['quote']       ?? '');
        $achievement  = trim($_POST['achievement'] ?? '');
        $image_path   = null;

        // Handle Image Upload
        if (isset($_FILES['spotlight_image']) && $_FILES['spotlight_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/spotlights/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            
            $file_ext = strtolower(pathinfo($_FILES['spotlight_image']['name'], PATHINFO_EXTENSION));
            $new_name = 'spotlight_' . time() . '_' . uniqid() . '.' . $file_ext;
            $target   = $upload_dir . $new_name;

            if (move_uploaded_file($_FILES['spotlight_image']['tmp_name'], $target)) {
                $image_path = 'uploads/spotlights/' . $new_name;
            }
        }

        if ($nominee_uid && $quote && $achievement) {
            $aStmt = $pdo->prepare("SELECT id FROM alumni WHERE user_id=?");
            $aStmt->execute([$nominee_uid]);
            $al = $aStmt->fetch();
            if ($al) {
                $pdo->query("UPDATE spotlights SET status='archived' WHERE status='active'");
                $pdo->prepare("INSERT INTO spotlights (alumni_id, quote, achievement, image_path, status, rotation_start, created_by) VALUES (?,?,?,?,'active',CURDATE(),?)")
                    ->execute([$al['id'], $quote, $achievement, $image_path, $_SESSION['user_id']]);
                $success = "Spotlight created with featured image and is now live!";
            } else { $error = "Alumni profile not found."; }
        } else { $error = "All fields are required."; }
    }
}

// Fetch spotlight data grouped by status
$pending  = $pdo->query("SELECT s.*, u.name, a.program, a.batch_year, a.job_title, a.company, nom.name as nominator FROM spotlights s JOIN alumni a ON s.alumni_id=a.id JOIN users u ON a.user_id=u.id LEFT JOIN users nom ON s.created_by=nom.id WHERE s.status='pending' ORDER BY s.created_at DESC")->fetchAll();
$active   = $pdo->query("SELECT s.*, u.name, a.program, a.batch_year FROM spotlights s JOIN alumni a ON s.alumni_id=a.id JOIN users u ON a.user_id=u.id WHERE s.status='active' LIMIT 1")->fetch();
$archived = $pdo->query("SELECT s.*, u.name, a.program FROM spotlights s JOIN alumni a ON s.alumni_id=a.id JOIN users u ON a.user_id=u.id WHERE s.status='archived' ORDER BY s.created_at DESC LIMIT 10")->fetchAll();

// Spotlight reactions on active
$reactions = [];
if ($active) {
    $rStmt = $pdo->prepare("SELECT reaction_type, COUNT(*) as cnt FROM spotlight_reactions WHERE spotlight_id=? GROUP BY reaction_type");
    $rStmt->execute([$active['id']]);
    foreach ($rStmt->fetchAll() as $r) $reactions[$r['reaction_type']] = $r['cnt'];
}

// Alumni list for manual creation
$alumni_list = $pdo->query("SELECT u.id, u.name, a.program, a.batch_year FROM users u JOIN alumni a ON u.id=a.user_id WHERE u.is_active=1 ORDER BY u.name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Spotlights – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;} .modal{display:none;} .modal.open{display:flex;}</style>
</head>
<body class="bg-slate-50 min-h-screen flex">
<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3 text-slate-400">
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Manage Spotlights</span>
            <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
            <span class="text-xs">Alumni Recognition</span>
        </div>
        <div class="flex items-center gap-2">
            <?php if (count($pending) > 0): ?>
                <span class="bg-amber-500 text-white text-xs font-black px-2.5 py-1 rounded-full animate-pulse"><?php echo count($pending); ?> pending</span>
            <?php endif; ?>
            <button onclick="document.getElementById('createModal').classList.add('open')"
                class="h-9 px-4 bg-amber-500 text-white font-bold text-xs rounded-xl hover:bg-amber-600 transition-all">
                ⭐ Create Spotlight
            </button>
        </div>
    </header>

    <div class="p-8 max-w-6xl mx-auto w-full">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 italic">SPOTLIGHT MANAGEMENT</h1>
            <p class="text-slate-400 text-sm font-medium">Approve alumni nominations and manage the live spotlight feature.</p>
        </div>

        <?php if ($success): ?><div class="mb-6 bg-green-50 border border-green-100 text-green-700 px-5 py-3 rounded-2xl text-sm font-medium flex items-center gap-2">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-5 py-3 rounded-2xl text-sm font-medium">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Currently Active Spotlight -->
        <div class="mb-8">
            <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Currently Live</h2>
            <?php if ($active): ?>
            <div class="bg-gradient-to-r from-amber-500 to-orange-400 rounded-[32px] p-8 text-white flex items-center gap-8 shadow-xl shadow-amber-100">
                <div class="w-24 h-24 flex-shrink-0 relative">
                    <?php if (!empty($active['image_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($active['image_path']); ?>" 
                             class="w-full h-full object-cover rounded-3xl shadow-lg ring-4 ring-white/30" 
                             alt="<?php echo htmlspecialchars($active['name']); ?>">
                    <?php else: ?>
                        <?php echo renderAvatar($active['name'], 'w-full h-full text-3xl ring-4 ring-white/30 shadow-lg'); ?>
                    <?php endif; ?>
                </div>
                <div class="flex-1">
                    <span class="text-[10px] font-black bg-white/20 px-3 py-1 rounded-full uppercase tracking-[2px]">⭐ LIVE SPOTLIGHT</span>
                    <h3 class="font-black text-2xl mt-2"><?php echo htmlspecialchars($active['name']); ?></h3>
                    <p class="text-amber-100 text-sm font-medium"><?php echo htmlspecialchars($active['program']??''); ?> · Batch <?php echo $active['batch_year']??''; ?></p>
                    <p class="italic text-white/90 text-sm mt-3 line-clamp-1 font-medium">"<?php echo htmlspecialchars($active['quote']); ?>"</p>
                    <div class="flex items-center gap-4 mt-4 text-[10px] font-black uppercase tracking-tight">
                        <span class="bg-white/20 px-3 py-1 rounded-full">👍 <?php echo $reactions['like'] ?? 0; ?> Likes</span>
                        <span class="bg-white/20 px-3 py-1 rounded-full">💡 <?php echo $reactions['inspire'] ?? 0; ?> Inspired</span>
                        <span class="bg-white/20 px-3 py-1 rounded-full">🎉 <?php echo $reactions['congratulate'] ?? 0; ?> Congrats</span>
                    </div>
                </div>
                <form method="POST" class="ml-4">
                    <?php echo csrfField(); ?>
                    <input type="hidden" name="action" value="archive">
                    <input type="hidden" name="spotlight_id" value="<?php echo $active['id']; ?>">
                    <button type="submit" onclick="return confirm('Archive this spotlight?')"
                        class="bg-white/20 hover:bg-white text-amber-600 font-black text-[10px] uppercase tracking-widest px-6 py-3 rounded-2xl transition-all shadow-sm">Archive</button>
                </form>
            </div>
            <?php else: ?>
            <div class="bg-white rounded-3xl border-2 border-dashed border-slate-200 py-10 text-center text-slate-300">
                <div class="text-4xl mb-2">⭐</div>
                <p class="font-medium italic">No active spotlight. Approve a nomination below or create one manually.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pending Nominations -->
        <div class="mb-8">
            <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Pending Nominations (<?php echo count($pending); ?>)</h2>
            <?php if (empty($pending)): ?>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 py-10 text-center text-slate-300">
                    <p class="font-medium italic">No pending nominations.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                    <?php foreach ($pending as $p): ?>
                    <div class="bg-white rounded-[32px] shadow-sm border border-slate-100 p-8 hover:shadow-xl transition-all">
                        <div class="flex items-start gap-6 mb-6">
                            <?php echo renderAvatar($p['name'], 'w-16 h-16 flex-shrink-0 shadow-sm'); ?>
                            <div class="flex-1 min-w-0">
                                <h3 class="font-black text-slate-800 text-lg tracking-tight"><?php echo htmlspecialchars($p['name']); ?></h3>
                                <p class="text-xs text-slate-400 font-bold uppercase tracking-wider"><?php echo htmlspecialchars($p['program']??''); ?> · Batch <?php echo $p['batch_year']??''; ?></p>
                                <p class="text-[10px] text-slate-300 font-medium mt-2">Nominated by <?php echo htmlspecialchars($p['nominator']??'Unknown'); ?> · <?php echo timeAgo($p['created_at']); ?></p>
                            </div>
                        </div>
                        <div class="bg-amber-50/50 rounded-2xl p-5 mb-5 border border-amber-100/30">
                            <p class="text-[10px] text-amber-600 font-black uppercase tracking-[2px] mb-2">Statement of Quote</p>
                            <p class="text-sm italic text-amber-900 leading-relaxed font-medium">"<?php echo htmlspecialchars($p['quote']); ?>"</p>
                        </div>
                        <div class="bg-slate-50/50 rounded-2xl p-5 mb-8 border border-slate-100/30">
                            <p class="text-[10px] text-slate-500 font-black uppercase tracking-[2px] mb-2">Key Achievement</p>
                            <p class="text-sm text-slate-700 leading-relaxed font-medium"><?php echo htmlspecialchars($p['achievement']); ?></p>
                        </div>
                        <div class="flex gap-4">
                            <form method="POST" class="flex-1">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="spotlight_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" onclick="return confirm('Approve this spotlight? It will go live immediately.')"
                                    class="w-full h-12 bg-green-600 text-white font-black uppercase tracking-widest text-[10px] rounded-2xl hover:bg-green-700 transition-all shadow-md active:scale-95">✅ Approve</button>
                            </form>
                            <form method="POST" class="flex-1">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="spotlight_id" value="<?php echo $p['id']; ?>">
                                <button type="submit" onclick="return confirm('Reject this nomination?')"
                                    class="w-full h-12 bg-slate-100 text-slate-500 font-black uppercase tracking-widest text-[10px] rounded-2xl hover:bg-rose-50 hover:text-rose-600 transition-all active:scale-95">✕ Reject</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Archived Spotlights -->
        <?php if (!empty($archived)): ?>
        <div>
            <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Recent Archive</h2>
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="divide-y divide-slate-50">
                    <?php foreach ($archived as $a): ?>
                    <div class="flex items-center gap-4 p-5 hover:bg-slate-50 transition-all">
                        <?php echo renderAvatar($a['name'], 'w-10 h-10 flex-shrink-0 opacity-60'); ?>
                        <div class="flex-1 min-w-0">
                            <p class="font-bold text-slate-600 text-sm"><?php echo htmlspecialchars($a['name']); ?></p>
                            <p class="text-[10px] text-slate-400"><?php echo htmlspecialchars($a['program']??''); ?></p>
                        </div>
                        <p class="text-xs text-slate-400"><?php echo formatDate($a['created_at']); ?></p>
                        <span class="text-[10px] font-black bg-slate-100 text-slate-400 px-2 py-0.5 rounded uppercase">Archived</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Create Spotlight Modal -->
<div id="createModal" class="modal fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl">
        <div class="bg-gradient-to-r from-amber-500 to-orange-400 p-6 rounded-t-3xl">
            <h2 class="text-white font-black text-xl italic">CREATE SPOTLIGHT</h2>
            <p class="text-amber-100 text-xs mt-1">This will go live immediately, archiving any current spotlight.</p>
        </div>
        <form method="POST" class="p-6 space-y-4" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create_manual">
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Select Alumni *</label>
                <input type="text" id="modal_search" placeholder="Type to search..." class="w-full h-10 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-amber-100 mb-1" oninput="filterModalAlumni(this.value)">
                <select name="nominee_uid" id="modal_alumni_select" size="4" class="w-full bg-slate-50 rounded-xl px-3 py-2 text-sm outline-none focus:ring-4 focus:ring-amber-100 border border-slate-200">
                    <?php foreach ($alumni_list as $al): ?>
                        <option value="<?php echo $al['id']; ?>" class="modal-alumni-opt py-1"><?php echo htmlspecialchars($al['name']); ?> · <?php echo htmlspecialchars($al['program']??''); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Spotlight Photo (Optional)</label>
                <input type="file" name="spotlight_image" accept="image/*" class="w-full bg-slate-50 rounded-xl px-4 py-2 text-sm outline-none focus:ring-4 focus:ring-amber-100 file:mr-4 file:py-1 file:px-3 file:rounded-full file:border-0 file:text-[10px] file:font-black file:bg-amber-100 file:text-amber-700 hover:file:bg-amber-200">
                <p class="text-[9px] text-slate-400 mt-1 italic">High-res portraits recommended for premium display.</p>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Quote *</label>
                <textarea name="quote" rows="2" required placeholder='e.g. "Lyceum shaped who I am today..."' class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-amber-100 resize-none"></textarea>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Achievement *</label>
                <textarea name="achievement" rows="2" required placeholder="Career highlight or achievement." class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-amber-100 resize-none"></textarea>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="submit" class="flex-1 h-11 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 transition-all">⭐ Publish</button>
                <button type="button" onclick="document.getElementById('createModal').classList.remove('open')" class="flex-1 h-11 bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-100 rounded-xl font-bold hover:bg-slate-200 dark:hover:bg-slate-600 transition-all">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function filterModalAlumni(q) {
    document.querySelectorAll('.modal-alumni-opt').forEach(o => {
        o.style.display = o.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
    });
}
document.getElementById('createModal').addEventListener('click', function(e) { if(e.target===this) this.classList.remove('open'); });
</script>
</body>
</html>
