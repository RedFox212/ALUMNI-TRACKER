<?php
// pages/edit-profile.php — #1 EASIEST: pre-fill form, UPDATE alumni
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
if ($_SESSION['user_role'] !== 'alumni') { header('Location: dashboard.php'); exit; }

$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$success   = $error = null;

// Fetch current profile
$stmt = $pdo->prepare("SELECT * FROM alumni WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) { header('Location: setup-profile.php'); exit; }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = "Security token mismatch. Please refresh and try again.";
    } else {
        try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE alumni SET
            address = ?, contact_no = ?, advanced_degree = ?,
            employment_status = ?, company = ?, job_title = ?,
            discipline_match = ?, years_experience = ?, date_started = ?,
            updated_at = NOW()
            WHERE user_id = ?")
        ->execute([
            $_POST['address']           ?? '',
            $_POST['contact_no']        ?? '',
            $_POST['advanced_degree']   ?? '',
            $_POST['employment_status'] ?? 'Unemployed',
            $_POST['company']           ?? null,
            $_POST['job_title']         ?? null,
            isset($_POST['discipline_match']) ? 1 : 0,
            $_POST['years_experience']  ?? 0,
            !empty($_POST['date_started']) ? $_POST['date_started'] : null,
            $user_id
        ]);

        // Optional password change
        if (!empty($_POST['new_password'])) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user_id]);
            } else {
                throw new Exception("Passwords do not match.");
            }
        }

        $pdo->commit();
        $success = "Profile updated successfully!";
        // Reload profile
        $stmt = $pdo->prepare("SELECT * FROM alumni WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $profile = $stmt->fetch();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
    }
}

// Profile completion percentage
$fields = ['address','contact_no','employment_status','company','job_title','years_experience','batch_year','program'];
$filled = 0;
foreach ($fields as $f) { if (!empty($profile[$f])) $filled++; }
$completion_pct = round(($filled / count($fields)) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile – LATS</title>
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
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Edit Profile</span>
        </div>
        <div class="flex items-center gap-3">
            <div class="text-right hidden sm:block">
                <p class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($user_name); ?></p>
                <p class="text-[10px] text-slate-400 uppercase font-black tracking-widest">Alumni</p>
            </div>
            <?php echo renderAvatar($user_name, 'w-9 h-9'); ?>
        </div>
    </header>

    <div class="p-8 max-w-4xl mx-auto w-full">
        <div class="mb-8 flex items-center justify-between flex-wrap gap-4">
            <div>
                <h1 class="text-3xl font-black text-slate-800 italic">EDIT PROFILE</h1>
                <p class="text-slate-400 text-sm font-medium">Keep your professional information up to date.</p>
            </div>
            <!-- Completion Badge -->
            <div class="bg-white rounded-2xl px-5 py-3 shadow-sm border border-slate-100 flex items-center gap-4">
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Profile Complete</p>
                    <div class="flex items-center gap-2 mt-1">
                        <div class="w-28 h-2 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-blue-500 to-indigo-400 transition-all duration-700"
                                 style="width:<?php echo $completion_pct; ?>%"></div>
                        </div>
                        <span class="text-sm font-black <?php echo $completion_pct >= 80 ? 'text-green-600' : ($completion_pct >= 50 ? 'text-amber-500' : 'text-red-500'); ?>">
                            <?php echo $completion_pct; ?>%
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="mb-6 bg-green-50 border border-green-100 text-green-700 px-5 py-4 rounded-2xl text-sm font-medium flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-5 py-4 rounded-2xl text-sm font-medium"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <?php echo csrfField(); ?>
            <!-- Personal Info Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-5 border-b border-slate-50 bg-slate-50/50">
                    <h2 class="font-black text-slate-700 uppercase tracking-tighter text-sm">Personal Information</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Home Address</label>
                        <textarea name="address" rows="3" placeholder="Full residential address"
                            class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all resize-none"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Contact Number</label>
                        <input type="text" name="contact_no" value="<?php echo htmlspecialchars($profile['contact_no'] ?? ''); ?>" placeholder="09XX-XXX-XXXX"
                            class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Advanced Degree</label>
                        <input type="text" name="advanced_degree" value="<?php echo htmlspecialchars($profile['advanced_degree'] ?? ''); ?>" placeholder="e.g. Master's in CS (optional)"
                            class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                    </div>
                </div>
            </div>

            <!-- Employment Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-5 border-b border-slate-50 bg-slate-50/50">
                    <h2 class="font-black text-slate-700 uppercase tracking-tighter text-sm">Employment</h2>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Employment Status</label>
                        <select name="employment_status" id="empStatus" onchange="toggleEmpFields(this.value)"
                            class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 cursor-pointer transition-all">
                            <?php 
                            $status = $profile['employment_status'] ?? 'Unemployed';
                            foreach (['Employed','Unemployed','Self-employed','Freelancing','Studying'] as $es): ?>
                                <option value="<?php echo $es; ?>" <?php echo $status === $es ? 'selected' : ''; ?>><?php echo $es; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php $hideFields = in_array($status, ['Unemployed', 'Studying']); ?>
                    <div id="empFields" class="grid grid-cols-1 md:grid-cols-2 gap-5 <?php echo $hideFields ? 'hidden' : ''; ?>">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Company / Employer</label>
                            <input type="text" name="company" value="<?php echo htmlspecialchars($profile['company'] ?? ''); ?>"
                                class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Job Title / Position</label>
                            <input type="text" name="job_title" value="<?php echo htmlspecialchars($profile['job_title'] ?? ''); ?>"
                                class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Years of Experience</label>
                            <input type="number" name="years_experience" min="0" max="50" value="<?php echo (int)($profile['years_experience'] ?? 0); ?>"
                                class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Date Started</label>
                            <input type="date" name="date_started" value="<?php echo htmlspecialchars($profile['date_started'] ?? ''); ?>"
                                class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center gap-3 cursor-pointer bg-blue-50 rounded-xl p-4 hover:bg-blue-100 transition-all">
                                <input type="checkbox" name="discipline_match" class="w-5 h-5 rounded text-blue-600"
                                    <?php echo ($profile['discipline_match'] ?? 0) ? 'checked' : ''; ?>>
                                <span class="text-sm font-medium text-blue-700">My current job is related to my Lyceum program <span class="font-black">(In-Discipline)</span></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Password Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-5 border-b border-slate-50 bg-slate-50/50">
                    <h2 class="font-black text-slate-700 uppercase tracking-tighter text-sm">Change Password <span class="text-slate-300 font-medium normal-case">(optional)</span></h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">New Password</label>
                        <input type="password" name="new_password" placeholder="Leave blank to keep current"
                            class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Repeat new password"
                            class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                    </div>
                </div>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="flex-1 h-12 bg-blue-600 text-white rounded-2xl font-bold hover:bg-blue-700 active:scale-95 transition-all shadow-lg shadow-blue-200 text-sm">
                    💾 Save Changes
                </button>
                <a href="dashboard.php" class="h-12 px-6 bg-white border border-slate-200 text-slate-600 rounded-2xl font-bold hover:bg-slate-50 flex items-center transition-all text-sm">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</main>

<script>
function toggleEmpFields(val) {
    const hide = ['Unemployed','Studying'].includes(val);
    document.getElementById('empFields').classList.toggle('hidden', hide);
}
</script>
</body>
</html>
