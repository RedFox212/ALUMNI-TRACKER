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
$stmt = $pdo->prepare("SELECT a.*, u.email FROM alumni a JOIN users u ON a.user_id = u.id WHERE a.user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

if (!$profile) { header('Location: setup-profile.php'); exit; }

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        $error = "Security token mismatch. Please refresh and try again.";
    } else {
        try {
            // 1. Basic Validation
            $address = trim($_POST['address'] ?? '');
            $email   = trim($_POST['email'] ?? '');
            $code    = $_POST['country_code'] ?? '+63';
            $num     = preg_replace('/\D/', '', $_POST['phone_part'] ?? '');
            $contact = $code . ' ' . $num;
            
            $emp_status = $_POST['employment_status'] ?? 'Unemployed';
            
            if (empty($address)) throw new Exception("Home address is required.");
            if (empty($email)) throw new Exception("Institutional email is required.");
            if (empty($num)) throw new Exception("Contact number is required.");
            
            // 1.1 Phone Length Check with Region Specifics
            $valid_lengths = [
                '+63' => [10, 11], // PH: 9XX... or 09XX...
                '+1'  => [10],     // US/CA: 3-3-4
                '+65' => [8],      // SG
                '+60' => [9, 10],  // MY
                '+86' => [11]      // CN
            ];

            if (isset($valid_lengths[$code])) {
                if (!in_array(strlen($num), $valid_lengths[$code])) {
                    $allowed = implode(' or ', $valid_lengths[$code]);
                    throw new Exception("For $code, the number must be exactly $allowed digits long.");
                }
            } else {
                if (strlen($num) < 7 || strlen($num) > 15) {
                    throw new Exception("International numbers must be between 7 and 15 digits.");
                }
            }
            
            // 2. Conditional Validation for Employment
            $company = trim($_POST['company'] ?? '');
            $job_title = trim($_POST['job_title'] ?? '');
            
            if (in_array($emp_status, ['Employed', 'Self-employed', 'Freelancing'])) {
                if (empty($company)) throw new Exception("Company/Employer name is required for your status.");
                if (empty($job_title)) throw new Exception("Job title is required for your status.");
            }

            // 3. Digital Links
            $resume = trim($_POST['resume_link'] ?? '');
            $website = trim($_POST['website_link'] ?? '');
            $portfolio = trim($_POST['portfolio_link'] ?? '');
            $linkedin = trim($_POST['linkedin_link'] ?? '');

            // 4. Password Validation
            $new_pass = $_POST['new_password'] ?? '';
            $conf_pass = $_POST['confirm_password'] ?? '';
            $update_password = false;
            
            if (!empty($new_pass)) {
                if (strlen($new_pass) < 8) throw new Exception("New password must be at least 8 characters long.");
                if ($new_pass !== $conf_pass) throw new Exception("Passwords do not match.");
                $update_password = true;
            }

            $pdo->beginTransaction();

            $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")
                ->execute([$email, $user_id]);

            // Update Alumni Table
            $stmt = $pdo->prepare("UPDATE alumni SET
                address = ?, contact_no = ?, advanced_degree = ?,
                employment_status = ?, company = ?, job_title = ?,
                discipline_match = ?, years_experience = ?, date_started = ?,
                resume_link = ?, website_link = ?, portfolio_link = ?, linkedin_link = ?,
                updated_at = NOW()
                WHERE user_id = ?");
            
            $stmt->execute([
                $address, $contact, trim($_POST['advanced_degree'] ?? ''),
                $emp_status, 
                !empty($company) ? $company : null,
                !empty($job_title) ? $job_title : null,
                isset($_POST['discipline_match']) ? 1 : 0,
                (int)($_POST['years_experience'] ?? 0),
                !empty($_POST['date_started']) ? $_POST['date_started'] : null,
                $resume, $website, $portfolio, $linkedin,
                $user_id
            ]);

            if ($update_password) {
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([password_hash($new_pass, PASSWORD_DEFAULT), $user_id]);
            }

            // Image Upload Handling
            if (!empty($_FILES['profile_avatar']['name'])) {
                $file = $_FILES['profile_avatar'];
                $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
                
                if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("Upload error: " . $file['error']);
                if ($file['size'] > 5 * 1024 * 1024) throw new Exception("Image is too large (max 5MB)");
                
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($file['tmp_name']);
                if (!in_array($mime, $allowed)) throw new Exception("Invalid file type: " . $mime);
                
                if (!getimagesize($file['tmp_name'])) throw new Exception("The uploaded file is not a valid image.");

                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) throw new Exception("Unauthorized extension.");

                $newName = bin2hex(random_bytes(16)) . '.' . $ext;
                $target = 'uploads/avatars/' . $newName;

                if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../' . $target)) {
                    throw new Exception("Failed to save the image.");
                }

                // Delete old one if exists
                if (!empty($profile['profile_pic'])) {
                    $oldPath = __DIR__ . '/../' . $profile['profile_pic'];
                    if (file_exists($oldPath)) unlink($oldPath);
                }

                $pdo->prepare("UPDATE alumni SET profile_pic = ? WHERE user_id = ?")
                    ->execute([$target, $user_id]);
                
                $_SESSION['profile_pic'] = $target;
            }

            $pdo->commit();
            $success = "Profile updated successfully!";
            
            // Refresh local profile data
            $stmt = $pdo->prepare("SELECT a.*, u.email FROM alumni a JOIN users u ON a.user_id = u.id WHERE a.user_id = ?");
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
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

<main class="flex-1 flex flex-col lg:ml-72">
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

        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <?php echo csrfField(); ?>
            <!-- Personal Info Card -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-5 border-b border-slate-50 bg-slate-50/50 flex items-center justify-between">
                    <h2 class="font-black text-slate-700 uppercase tracking-tighter text-sm">Personal Information</h2>
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest italic">Identity Verification Required</span>
                </div>
                <div class="p-8 border-b border-slate-50 flex flex-col md:flex-row items-center gap-8 bg-slate-50/20">
                    <div class="relative group">
                        <div id="avatar-preview">
                            <?php echo renderAvatar($user_name, 'w-24 h-24 ring-4 ring-slate-100', $profile['profile_pic']); ?>
                        </div>
                        <label for="profile_avatar" class="absolute bottom-0 right-0 w-10 h-10 bg-blue-600 text-white rounded-2xl flex items-center justify-center border-4 border-white cursor-pointer hover:scale-110 active:scale-90 transition-all shadow-xl">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                        </label>
                        <input type="file" name="profile_avatar" id="profile_avatar" class="hidden" accept="image/png, image/jpeg, image/webp" onchange="previewImage(this)">
                    </div>
                    <div class="text-center md:text-left">
                        <p class="text-lg font-black text-slate-800 uppercase tracking-tight">Institutional Portrait</p>
                        <p class="text-xs text-slate-400 font-bold mt-1">PNG, JPG or WebP. Global limit <span class="text-blue-600">5 MB</span>.</p>
                        <p class="text-[10px] text-slate-300 font-medium italic mt-2">"Your image is secured via institutional encryption & tamper-proof naming."</p>
                    </div>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Home Address</label>
                        <textarea name="address" rows="3" placeholder="Full residential address" required
                            class="w-full bg-slate-50 rounded-xl px-4 py-3 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all resize-none"><?php echo htmlspecialchars($profile['address'] ?? ''); ?></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Contact Number</label>
                        <div class="flex gap-2">
                            <select name="country_code" class="w-28 h-11 bg-slate-50 rounded-xl px-2 text-[10px] font-black outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all appearance-none cursor-pointer border border-slate-100">
                                <option value="+63" selected>PH (+63)</option>
                                <option value="+60">MY (+60)</option>
                                <option value="+65">SG (+65)</option>
                                <option value="+1">US/CA (+1)</option>
                                <option value="+44">UK (+44)</option>
                                <option value="+61">AU (+61)</option>
                                <option value="+81">JP (+81)</option>
                                <option value="+82">KR (+82)</option>
                                <option value="+86">CN (+86)</option>
                                <option value="+91">IN (+91)</option>
                                <option value="+971">UAE (+971)</option>
                                <option value="+966">SA (+966)</option>
                                <option value="+852">HK (+852)</option>
                                <option value="+886">TW (+886)</option>
                                <option value="+64">NZ (+64)</option>
                                <option value="+33">FR (+33)</option>
                                <option value="+49">DE (+49)</option>
                                <option value="+39">IT (+39)</option>
                                <option value="+34">ES (+34)</option>
                                <option value="+7">RU (+7)</option>
                                <option value="+27">ZA (+27)</option>
                                <option value="+55">BR (+55)</option>
                                <option value="+52">MX (+52)</option>
                                <option value="+62">ID (+62)</option>
                                <option value="+66">TH (+66)</option>
                                <option value="+84">VN (+84)</option>
                                <option value="+31">NL (+31)</option>
                                <option value="+41">CH (+41)</option>
                                <option value="+46">SE (+46)</option>
                                <option value="+47">NO (+47)</option>
                                <option value="+45">DK (+45)</option>
                            </select>
                            <input type="tel" id="contact_no_input" name="phone_part" value="<?php 
                                $fullNum = $profile['contact_no'] ?? '';
                                // Strip the plus and code if possible for display
                                echo preg_replace('/^\+\d+\s*/', '', $fullNum);
                            ?>" placeholder="Number only" required minlength="7" maxlength="15"
                                class="flex-1 h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Institutional Email</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" placeholder="name@domain.com" required
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

            <!-- Digital Presence Section -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-5 border-b border-slate-50 bg-slate-50/50">
                    <h2 class="font-black text-slate-700 uppercase tracking-tighter text-sm">Digital Presence</h2>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Resume / CV Link</label>
                        <input type="url" name="resume_link" value="<?php echo htmlspecialchars($profile['resume_link'] ?? ''); ?>" placeholder="https://drive.google.com/..."
                            class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Portfolio Link</label>
                        <input type="url" name="portfolio_link" value="<?php echo htmlspecialchars($profile['portfolio_link'] ?? ''); ?>" placeholder="https://behance.net/..."
                            class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">LinkedIn Profile</label>
                        <input type="url" name="linkedin_link" value="<?php echo htmlspecialchars($profile['linkedin_link'] ?? ''); ?>" placeholder="https://linkedin.com/in/..."
                            class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Personal Website</label>
                        <input type="url" name="website_link" value="<?php echo htmlspecialchars($profile['website_link'] ?? ''); ?>" placeholder="https://yourname.com"
                            class="w-full h-11 bg-slate-50 rounded-xl px-4 text-sm outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all">
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
                        <input type="password" name="new_password" placeholder="Leave blank to keep current" minlength="8"
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

function previewImage(input) {
    if (input.files && input.files[0]) {
        if (input.files[0].size > 5 * 1024 * 1024) {
            alert('File is too large (Max 5MB)');
            input.value = '';
            return;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatar-preview');
            preview.innerHTML = `<img src="${e.target.result}" class="w-24 h-24 rounded-full object-cover ring-4 ring-slate-100 border border-slate-200">`;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Restrict contact input to numbers only
document.getElementById('contact_no_input').addEventListener('input', function(e) {
    this.value = this.value.replace(/\D/g, '');
});
</script>
</body>
</html>
