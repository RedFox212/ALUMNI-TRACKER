<?php
// pages/manage-users.php  — EASY  (table CRUD for users)
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: dashboard.php'); exit; }

$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$success = $error = null;

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action']  ?? '';
    $user_id = (int)($_POST['user_id'] ?? 0);

    if ($action === 'toggle_active' && $user_id) {
        $cur = $pdo->prepare("SELECT is_active FROM users WHERE id = ?");
        $cur->execute([$user_id]);
        $cur_val = $cur->fetchColumn();
        $new_val = $cur_val ? 0 : 1;
        $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$new_val, $user_id]);
        $success = "User status updated.";

    } elseif ($action === 'change_role' && $user_id) {
        $new_role = $_POST['new_role'] ?? '';
        if (in_array($new_role, ['admin','alumni','officer'])) {
            $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $user_id]);
            $success = "User role changed to $new_role.";
        }

    } elseif ($action === 'reset_password' && $user_id) {
        $new_pass = trim($_POST['new_password'] ?? '');
        if (strlen($new_pass) >= 6) {
            $hash = password_hash($new_pass, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user_id]);
            $success = "Password reset successfully.";
        } else {
            $error = "Password must be at least 6 characters.";
        }

    } elseif ($action === 'delete_user' && $user_id) {
        if ($user_id == $_SESSION['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$user_id]);
            $success = "User deleted.";
        }
    }
}

// Filters
$search_q  = trim($_GET['search'] ?? '');
$role_f    = $_GET['role'] ?? '';
$batch_f   = $_GET['batch'] ?? '';
$program_f = $_GET['program'] ?? '';

$query     = "SELECT u.*, a.batch_year, a.program 
              FROM users u 
              LEFT JOIN alumni a ON u.id = a.user_id 
              WHERE 1=1";
$params    = [];

if ($search_q) { $query .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search_q%"; $params[] = "%$search_q%"; }
if ($role_f)   { $query .= " AND u.role = ?"; $params[] = $role_f; }
if ($batch_f)  { $query .= " AND a.batch_year = ?"; $params[] = $batch_f; }
if ($program_f){ $query .= " AND a.program = ?"; $params[] = $program_f; }

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

$role_colors = ['admin'=>'bg-red-100 text-red-600','alumni'=>'bg-green-100 text-green-600','officer'=>'bg-purple-100 text-purple-600'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; } .modal { display:none; } .modal.open { display:flex; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex">

<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3 text-slate-400">
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Manage Users</span>
            <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
            <span class="text-xs">Account Management</span>
        </div>
        <span class="text-xs bg-slate-100 text-slate-500 font-bold px-3 py-1 rounded-full"><?php echo count($users); ?> users</span>
    </header>

    <div class="p-8">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 italic">MANAGE USERS</h1>
            <p class="text-slate-400 text-sm font-medium">Activate, deactivate, change roles, and reset passwords.</p>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?><div class="mb-6 bg-green-50 border border-green-100 text-green-700 px-5 py-3 rounded-2xl text-sm font-medium flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-5 py-3 rounded-2xl text-sm font-medium"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Filter Bar -->
        <form class="bg-white rounded-3xl shadow-sm border border-slate-100 p-8 mb-10 flex flex-wrap gap-6 items-center">
            <div class="flex-1 relative min-w-[300px]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 absolute left-4 top-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search_q); ?>" placeholder="Search name or email..."
                    class="w-full h-12 bg-slate-50 rounded-2xl pl-12 pr-4 outline-none focus:ring-4 focus:ring-blue-100 text-sm transition-all border border-transparent focus:border-blue-200">
            </div>
            <select name="role" class="h-12 bg-slate-50 rounded-2xl px-6 outline-none focus:ring-4 focus:ring-blue-100 text-[11px] font-black uppercase tracking-widest cursor-pointer min-w-44 border border-transparent focus:border-blue-200 transition-all">
                <option value="">All Roles</option>
                <option value="admin"  <?php echo $role_f==='admin'  ?'selected':'';?>>Admin</option>
                <option value="alumni" <?php echo $role_f==='alumni' ?'selected':'';?>>Alumni</option>
                <option value="officer"<?php echo $role_f==='officer'?'selected':'';?>>Officer</option>
            </select>

            <select name="program" class="h-12 bg-slate-50 rounded-2xl px-6 outline-none focus:ring-4 focus:ring-blue-100 text-[11px] font-black uppercase tracking-widest cursor-pointer min-w-44 border border-transparent focus:border-blue-200 transition-all">
                <option value="">All Programs</option>
                <?php 
                $progs = $pdo->query("SELECT DISTINCT program FROM alumni WHERE program IS NOT NULL ORDER BY program")->fetchAll(PDO::FETCH_COLUMN);
                foreach($progs as $p): ?>
                    <option value="<?php echo $p; ?>" <?php echo $program_f===$p?'selected':''; ?>><?php echo $p; ?></option>
                <?php endforeach; ?>
            </select>

            <select name="batch" class="h-12 bg-slate-50 rounded-2xl px-6 outline-none focus:ring-4 focus:ring-blue-100 text-[11px] font-black uppercase tracking-widest cursor-pointer min-w-32 border border-transparent focus:border-blue-200 transition-all">
                <option value="">All Batches</option>
                <?php for($y=2026; $y>=2004; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $batch_f==$y?'selected':''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
            <div class="flex gap-3">
                <button type="submit" class="h-12 px-8 bg-blue-600 text-white rounded-2xl font-bold hover:bg-blue-700 text-sm transition-all shadow-lg shadow-blue-100 active:scale-95">Filter</button>
                <a href="manage-users.php" class="h-12 px-6 flex items-center text-slate-400 hover:text-slate-600 text-sm font-bold rounded-2xl hover:bg-slate-50 transition-all">Clear</a>
            </div>
        </form>

        <!-- Table -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="bg-slate-50/70 border-b border-slate-100">
                        <th class="text-left py-6 px-8 text-[11px] font-black text-slate-400 uppercase tracking-[2px]">User</th>
                        <th class="text-left py-6 px-6 text-[11px] font-black text-slate-400 uppercase tracking-[2px]">Role</th>
                        <th class="text-left py-6 px-6 text-[11px] font-black text-slate-400 uppercase tracking-[2px]">Status</th>
                        <th class="text-left py-6 px-6 text-[11px] font-black text-slate-400 uppercase tracking-[2px]">Joined</th>
                        <th class="text-right py-6 px-8 text-[11px] font-black text-slate-400 uppercase tracking-[2px]">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($users)): ?>
                    <tr><td colspan="5" class="py-16 text-center text-slate-300 italic">No users found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($users as $u): ?>
                    <tr class="hover:bg-slate-50/50 transition-all group">
                        <td class="py-6 px-8">
                            <div class="flex items-center gap-4">
                                <?php echo renderAvatar($u['name'], 'w-12 h-12 flex-shrink-0 shadow-sm border-2 border-white'); ?>
                                <div>
                                    <p class="font-black text-slate-800 text-sm"><?php echo htmlspecialchars($u['name']); ?></p>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-tight">
                                        <?php if ($u['role'] === 'alumni' && $u['program']): ?>
                                            Class of <?php echo $u['batch_year']; ?> • <?php echo htmlspecialchars($u['program']); ?>
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($u['email']); ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="py-6 px-6">
                            <span class="text-[10px] font-black px-3 py-1.5 rounded-2xl uppercase <?php echo $role_colors[$u['role']] ?? 'bg-slate-100 text-slate-500'; ?>">
                                <?php echo $u['role']; ?>
                            </span>
                        </td>
                        <td class="py-6 px-6">
                            <?php if ($u['is_active']): ?>
                                <span class="flex items-center gap-2 text-green-600 text-xs font-black tracking-tight"><span class="w-2 h-2 bg-green-500 rounded-full shadow-sm shadow-green-200"></span>Active</span>
                            <?php else: ?>
                                <span class="flex items-center gap-2 text-rose-500 text-xs font-black tracking-tight"><span class="w-2 h-2 bg-rose-400 rounded-full shadow-sm shadow-rose-200"></span>Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="py-6 px-6 text-xs text-slate-400 font-medium tracking-tight"><?php echo formatDate($u['created_at']); ?></td>
                        <td class="py-6 px-8">
                            <div class="flex items-center justify-end gap-3 opacity-0 group-hover:opacity-100 transition-all translate-x-2 group-hover:translate-x-0">
                                <!-- Toggle Active -->
                                <form method="POST" class="inline">
                                    <input type="hidden" name="action" value="toggle_active">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" title="<?php echo $u['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                        class="w-9 h-9 rounded-xl flex items-center justify-center text-xs transition-all <?php echo $u['is_active'] ? 'bg-rose-50 text-rose-500 hover:bg-rose-500 hover:text-white shadow-sm' : 'bg-green-50 text-green-500 hover:bg-green-600 hover:text-white shadow-sm'; ?>">
                                        <?php echo $u['is_active'] ? '🚫' : '✅'; ?>
                                    </button>
                                </form>
                                <!-- Change Role -->
                                <button onclick="openRoleModal(<?php echo $u['id']; ?>, '<?php echo $u['role']; ?>')"
                                    class="w-9 h-9 rounded-xl bg-blue-50 text-blue-500 hover:bg-blue-600 hover:text-white flex items-center justify-center transition-all shadow-sm" title="Change Role">
                                    🔑
                                </button>
                                <!-- Reset Password -->
                                <button onclick="openPassModal(<?php echo $u['id']; ?>)"
                                    class="w-9 h-9 rounded-xl bg-amber-50 text-amber-500 hover:bg-amber-500 hover:text-white flex items-center justify-center transition-all shadow-sm" title="Reset Password">
                                    🔒
                                </button>
                                <!-- Delete -->
                                <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="inline" onsubmit="return confirm('Delete this user permanently?')">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                    <button type="submit" class="w-9 h-9 rounded-xl bg-slate-50 text-slate-400 hover:bg-rose-500 hover:text-white flex items-center justify-center transition-all shadow-sm" title="Delete">🗑️</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Change Role Modal -->
<div id="roleModal" class="modal fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 items-center justify-center">
    <div class="bg-white rounded-3xl p-8 w-full max-w-sm shadow-2xl mx-4">
        <h3 class="font-black text-slate-800 text-lg mb-6">Change User Role</h3>
        <form method="POST">
            <input type="hidden" name="action" value="change_role">
            <input type="hidden" name="user_id" id="role_user_id">
            <select name="new_role" id="new_role_select" class="w-full h-11 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 mb-4 text-sm cursor-pointer">
                <option value="admin">Admin</option>
                <option value="alumni">Alumni</option>
                <option value="officer">Officer</option>
            </select>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 h-11 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 transition-all">Save</button>
                <button type="button" onclick="closeModal('roleModal')" class="flex-1 h-11 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition-all">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="passModal" class="modal fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 items-center justify-center">
    <div class="bg-white rounded-3xl p-8 w-full max-w-sm shadow-2xl mx-4">
        <h3 class="font-black text-slate-800 text-lg mb-6">Reset Password</h3>
        <form method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="pass_user_id">
            <input type="text" name="new_password" placeholder="New password (min 6 chars)" required minlength="6"
                class="w-full h-11 bg-slate-50 rounded-xl px-4 outline-none focus:ring-4 focus:ring-blue-100 mb-4 text-sm">
            <div class="flex gap-3">
                <button type="submit" class="flex-1 h-11 bg-amber-500 text-white rounded-xl font-bold hover:bg-amber-600 transition-all">Reset</button>
                <button type="button" onclick="closeModal('passModal')" class="flex-1 h-11 bg-slate-100 text-slate-600 rounded-xl font-bold hover:bg-slate-200 transition-all">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRoleModal(uid, currentRole) {
    document.getElementById('role_user_id').value = uid;
    document.getElementById('new_role_select').value = currentRole;
    document.getElementById('roleModal').classList.add('open');
}
function openPassModal(uid) {
    document.getElementById('pass_user_id').value = uid;
    document.getElementById('passModal').classList.add('open');
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));
</script>
</body>
</html>
