<?php
// pages/manage-batch-officers.php — Admin: assign/remove batch officers
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: dashboard.php'); exit; }

$user_name = $_SESSION['user_name'];
$success = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) { $error = "Invalid request."; }
    else {
        $action  = $_POST['action']  ?? '';
        $user_id_target = (int)($_POST['user_id'] ?? 0);

        if ($action === 'assign_officer' && $user_id_target) {
            $pdo->prepare("UPDATE users SET role='officer' WHERE id=? AND role='alumni'")->execute([$user_id_target]);
            // Notify
            $pdo->prepare("INSERT INTO notifications (user_id, type, message) VALUES (?, 'announcement', ?)")
                ->execute([$user_id_target, "🎖️ You have been assigned as a Batch Officer!"]);
            $success = "User promoted to Batch Officer.";

        } elseif ($action === 'remove_officer' && $user_id_target) {
            $pdo->prepare("UPDATE users SET role='alumni' WHERE id=? AND role='officer'")->execute([$user_id_target]);
            $success = "Officer role removed. User reverted to Alumni.";

        } elseif ($action === 'update_position' && $user_id_target) {
            $position = trim($_POST['position'] ?? '');
            if ($position) {
                // Ensure batch_officers row exists
                $chk = $pdo->prepare("SELECT COUNT(*) FROM batch_officers WHERE user_id=?");
                $chk->execute([$user_id_target]);
                if ($chk->fetchColumn() > 0) {
                    $pdo->prepare("UPDATE batch_officers SET position=?, updated_at=NOW() WHERE user_id=?")->execute([$position, $user_id_target]);
                } else {
                    // Get batch_year from alumni table
                    $bs = $pdo->prepare("SELECT batch_year FROM alumni WHERE user_id=?");
                    $bs->execute([$user_id_target]);
                    $batch = $bs->fetchColumn();
                    $pdo->prepare("INSERT INTO batch_officers (user_id, position, batch_year) VALUES (?,?,?)")->execute([$user_id_target, $position, $batch]);
                }
                $success = "Position updated.";
            }
        }
    }
}

// Current officers
$officers = $pdo->query(
    "SELECT u.id, u.name, u.email, u.is_active, a.batch_year, a.program,
            bo.position, bo.id as bo_id
     FROM users u
     JOIN alumni a ON u.id = a.user_id
     LEFT JOIN batch_officers bo ON u.id = bo.user_id
     WHERE u.role = 'officer'
     ORDER BY a.batch_year DESC, u.name ASC"
)->fetchAll();

// Eligible alumni to promote (not already officers)
$eligible = $pdo->query(
    "SELECT u.id, u.name, a.batch_year, a.program, a.job_title
     FROM users u JOIN alumni a ON u.id = a.user_id
     WHERE u.role = 'alumni' AND u.is_active = 1
     ORDER BY a.batch_year DESC, u.name ASC"
)->fetchAll();

// Batch summary
$batches = $pdo->query(
    "SELECT a.batch_year,
        COUNT(CASE WHEN u.role='officer' THEN 1 END) as officers,
        COUNT(*) as total
     FROM users u JOIN alumni a ON u.id=a.user_id
     GROUP BY a.batch_year ORDER BY a.batch_year DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Officers – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;} .modal{display:none;} .modal.open{display:flex;}</style>
</head>
<body class="bg-slate-50 min-h-screen flex">
<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3 text-slate-400">
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Batch Officers</span>
            <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
            <span class="text-xs">Role Management</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs bg-purple-50 text-purple-600 font-bold px-3 py-1 rounded-full"><?php echo count($officers); ?> officers</span>
            <button onclick="document.getElementById('assignModal').classList.add('open')"
                class="h-9 px-4 bg-purple-600 text-white font-bold text-xs rounded-xl hover:bg-purple-700 transition-all">+ Assign Officer</button>
        </div>
    </header>

    <div class="p-8 max-w-6xl mx-auto w-full">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 italic">BATCH OFFICERS</h1>
            <p class="text-slate-400 text-sm font-medium">Assign and manage batch officers for each graduation year.</p>
        </div>

        <?php if ($success): ?><div class="mb-6 bg-green-50 border border-green-100 text-green-700 px-5 py-3 rounded-2xl text-sm font-medium">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-5 py-3 rounded-2xl text-sm font-medium">⚠️ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Batch Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3 mb-8">
            <?php foreach (array_slice($batches, 0, 6) as $b): ?>
            <div class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 text-center">
                <p class="text-lg font-black text-slate-800"><?php echo $b['batch_year']; ?></p>
                <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mt-1">
                    <?php echo $b['officers']; ?> officer<?php echo $b['officers']!=1?'s':''; ?>
                </p>
                <div class="h-1 bg-slate-100 rounded-full mt-2 overflow-hidden">
                    <?php $pct = $b['total'] > 0 ? min(100, round(($b['officers']/$b['total'])*100*5)) : 0; ?>
                    <div class="h-full bg-purple-500 rounded-full" style="width:<?php echo $pct; ?>%"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Officers Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-6">
            <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex justify-between items-center">
                <h2 class="font-black text-slate-800 uppercase tracking-tighter text-sm">Current Officers</h2>
                <span class="text-xs text-slate-400"><?php echo count($officers); ?> assigned</span>
            </div>
            <?php if (empty($officers)): ?>
            <div class="py-16 text-center text-slate-300">
                <div class="text-5xl mb-3">🎖️</div>
                <p class="font-medium italic">No batch officers assigned yet.</p>
            </div>
            <?php else: ?>
            <table class="w-full">
                <thead><tr class="bg-slate-50/70 border-b border-slate-100">
                    <th class="text-left py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Officer</th>
                    <th class="text-left py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Position</th>
                    <th class="text-left py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Batch</th>
                    <th class="text-left py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Program</th>
                    <th class="text-right py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Actions</th>
                </tr></thead>
                <tbody class="divide-y divide-slate-50">
                    <?php foreach ($officers as $o): ?>
                    <tr class="hover:bg-slate-50 transition-all">
                        <td class="py-4 px-6">
                            <div class="flex items-center gap-3">
                                <?php echo renderAvatar($o['name'], 'w-10 h-10 flex-shrink-0'); ?>
                                <div>
                                    <p class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($o['name']); ?></p>
                                    <p class="text-xs text-slate-400"><?php echo htmlspecialchars($o['email']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="py-4 px-4">
                            <form method="POST" class="flex items-center gap-2">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="update_position">
                                <input type="hidden" name="user_id" value="<?php echo $o['id']; ?>">
                                <input type="text" name="position" value="<?php echo htmlspecialchars($o['position'] ?? ''); ?>"
                                    placeholder="e.g. President"
                                    class="h-8 bg-slate-50 rounded-lg px-3 text-xs outline-none focus:ring-2 focus:ring-purple-200 w-28">
                                <button type="submit" class="h-8 px-2 bg-purple-50 text-purple-600 rounded-lg text-xs font-bold hover:bg-purple-100 transition-all">Save</button>
                            </form>
                        </td>
                        <td class="py-4 px-4"><span class="text-xs font-black text-slate-600 bg-purple-50 px-2 py-1 rounded-lg"><?php echo $o['batch_year']??'—'; ?></span></td>
                        <td class="py-4 px-4 text-xs text-slate-500"><?php echo htmlspecialchars($o['program']??'—'); ?></td>
                        <td class="py-4 px-6 text-right">
                            <form method="POST" class="inline" onsubmit="return confirm('Remove officer role from this user?')">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="action" value="remove_officer">
                                <input type="hidden" name="user_id" value="<?php echo $o['id']; ?>">
                                <button type="submit" class="text-xs px-3 py-1.5 bg-red-50 text-red-500 rounded-lg font-bold hover:bg-red-100 transition-all">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Assign Officer Modal -->
<div id="assignModal" class="modal fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 items-center justify-center p-4">
    <div class="bg-white rounded-3xl w-full max-w-lg shadow-2xl max-h-[85vh] overflow-y-auto">
        <div class="bg-purple-600 p-6 rounded-t-3xl sticky top-0">
            <h2 class="text-white font-black text-xl italic">ASSIGN BATCH OFFICER</h2>
            <p class="text-purple-200 text-xs mt-1">Search and select an alumni to promote.</p>
        </div>
        <div class="p-6">
            <input type="text" id="assign_search" placeholder="Search by name, batch, or program..."
                class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-purple-100 mb-3"
                oninput="filterAssign(this.value)">
            <div id="assign_list" class="space-y-2 max-h-64 overflow-y-auto">
                <?php foreach ($eligible as $e): ?>
                <div class="assign-item flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-purple-50 transition-all"
                     data-name="<?php echo strtolower(htmlspecialchars($e['name'])); ?>"
                     data-batch="<?php echo $e['batch_year']; ?>"
                     data-program="<?php echo strtolower(htmlspecialchars($e['program']??'')); ?>">
                    <?php echo renderAvatar($e['name'], 'w-10 h-10 flex-shrink-0 text-xs'); ?>
                    <div class="flex-1 min-w-0">
                        <p class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($e['name']); ?></p>
                        <p class="text-[10px] text-slate-400"><?php echo htmlspecialchars($e['program']??''); ?> · Batch <?php echo $e['batch_year']; ?></p>
                    </div>
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="action" value="assign_officer">
                        <input type="hidden" name="user_id" value="<?php echo $e['id']; ?>">
                        <button type="submit" class="text-xs px-3 py-1.5 bg-purple-600 text-white rounded-lg font-bold hover:bg-purple-700 transition-all">Assign</button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <button onclick="document.getElementById('assignModal').classList.remove('open')"
                class="w-full mt-4 h-11 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition-all text-sm">Close</button>
        </div>
    </div>
</div>

<script>
function filterAssign(q) {
    const norm = q.toLowerCase();
    document.querySelectorAll('.assign-item').forEach(el => {
        const match = el.dataset.name.includes(norm) || el.dataset.batch.includes(norm) || el.dataset.program.includes(norm);
        el.style.display = match ? '' : 'none';
    });
}
document.getElementById('assignModal').addEventListener('click', e => { if(e.target===e.currentTarget) e.currentTarget.classList.remove('open'); });
</script>
</body>
</html>
