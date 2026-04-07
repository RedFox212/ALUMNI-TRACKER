<?php
// pages/setup-profile.php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'alumni';
$error = null;

// Admins never need to set up an alumni profile
if ($user_role === 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = "Security mismatch. Please try again.";
    } else {
        try {
            $pdo->beginTransaction();

            // Ensure alumni record exists (prevents update fail on new users)
            $stmt = $pdo->prepare("INSERT INTO alumni (user_id, program, batch_year) 
                                   VALUES (?, 'None', 0000) 
                                   ON DUPLICATE KEY UPDATE user_id = user_id");
            $stmt->execute([$user_id]);

            // Update alumni data
            $stmt = $pdo->prepare("UPDATE alumni SET 
                address = ?, 
                contact_no = ?, 
                advanced_degree = ?, 
                employment_status = ?, 
                company = ?, 
                job_title = ?, 
                discipline_match = ?, 
                years_experience = ?, 
                date_started = ? 
                WHERE user_id = ?");
            
            $stmt->execute([
                $_POST['address'] ?? '',
                $_POST['contact_no'] ?? '',
                $_POST['advanced_degree'] ?? '',
                $_POST['employment_status'] ?? 'Unemployed',
                $_POST['company'] ?? null,
                $_POST['job_title'] ?? null,
                isset($_POST['discipline_match']) ? 1 : 0,
                $_POST['years_experience'] ?? 0,
                !empty($_POST['date_started']) ? $_POST['date_started'] : null,
                $user_id
            ]);
            
            // Update first_login status and optionally password
            if (!empty($_POST['new_password'])) {
                if ($_POST['new_password'] === ($_POST['confirm_password'] ?? '')) {
                    $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE users SET first_login = 0, password_hash = ? WHERE id = ?")
                        ->execute([$hashed, $user_id]);
                } else {
                    throw new Exception("Passwords do not match.");
                }
            } else {
                $pdo->prepare("UPDATE users SET first_login = 0 WHERE id = ?")
                    ->execute([$user_id]);
            }
            
            $pdo->commit();
            header("Location: dashboard.php?setup=success");
            exit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Error updating profile: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Your Profile - LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen py-12 px-4">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-3xl shadow-xl border border-slate-100 overflow-hidden">
            <div class="bg-blue-600 p-8 text-white">
                <h1 class="text-2xl font-bold">Complete Your Profile</h1>
                <p class="text-blue-100 mt-2">Welcome to the LATS network. Please provide your latest professional information to continue.</p>
            </div>
            
            <form method="POST" class="p-8 space-y-6">
                <?php echo csrfField(); ?>
                <?php if ($error): ?>
                    <div class="bg-red-50 text-red-600 p-4 rounded-xl text-sm border border-red-100"><?php echo $error; ?></div>
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-bold text-slate-700 mb-2">Home Address</label>
                        <textarea name="address" required class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:bg-white outline-none border-none transition-all h-24" placeholder="Full Address"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Contact Number</label>
                        <input type="text" name="contact_no" required class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:bg-white outline-none border-none transition-all" placeholder="0917-XXX-XXXX">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Advanced Degree (Optional)</label>
                        <input type="text" name="advanced_degree" class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:bg-white outline-none border-none transition-all" placeholder="Master's / Doctorate">
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-6">
                    <label class="block text-sm font-bold text-slate-700 mb-2">Employment Status</label>
                    <select name="employment_status" id="empStatus" required class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:bg-white outline-none border-none transition-all cursor-pointer">
                        <option value="Employed">Employed</option>
                        <option value="Unemployed">Unemployed</option>
                        <option value="Self-employed">Self-employed</option>
                        <option value="Freelancing">Freelancing</option>
                        <option value="Studying">Further Studies</option>
                    </select>
                </div>

                <!-- Conditional Employment Fields -->
                <div id="employedFields" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Current Company</label>
                            <input type="text" name="company" class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:bg-white outline-none border-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Job Title</label>
                            <input type="text" name="job_title" class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:bg-white outline-none border-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Experience (Years)</label>
                            <input type="number" name="years_experience" value="0" class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:bg-white outline-none border-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2">Date Started</label>
                            <input type="date" name="date_started" class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:bg-white outline-none border-none transition-all">
                        </div>
                    </div>
                    <div class="flex items-center gap-3 bg-blue-50 p-4 rounded-xl">
                        <input type="checkbox" name="discipline_match" id="match" class="w-5 h-5 text-blue-600 rounded focus:ring-blue-200">
                        <label for="match" class="text-sm text-blue-700 font-medium">My current job is related to my Lyceum program (In-discipline)</label>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-6">
                    <h2 class="text-sm font-bold text-slate-700 mb-4">Security Update</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">New Password (Optional)</label>
                            <input type="password" name="new_password" class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:bg-white outline-none border-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-400 uppercase tracking-widest mb-2">Confirm Password</label>
                            <input type="password" class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm focus:ring-4 focus:ring-blue-100 focus:bg-white outline-none border-none transition-all">
                        </div>
                    </div>
                </div>

                <button type="submit" class="w-full py-4 bg-blue-600 text-white rounded-2xl font-bold shadow-lg shadow-blue-100 hover:bg-blue-700 active:scale-[0.98] transition-all">Complete Registration</button>
            </form>
        </div>
    </div>

    <script>
        const empStatus = document.getElementById('empStatus');
        const employedFields = document.getElementById('employedFields');
        
        empStatus.onchange = () => {
            if (empStatus.value === 'Employed') {
                employedFields.style.opacity = '1';
                employedFields.style.display = 'block';
            } else {
                employedFields.style.opacity = '0';
                employedFields.style.display = 'none';
            }
        }
    </script>
</body>
</html>
