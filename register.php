<?php
// register.php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = $_POST['first_name'] ?? '';
    $last_name  = $_POST['last_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $email       = $_POST['email'] ?? '';
    $password    = $_POST['password'] ?? '';
    $gender      = $_POST['gender'] ?? '';
    $degree      = $_POST['degree'] ?? '';
    $college     = $_POST['college'] ?? '';
    $batch_year  = $_POST['batch_year'] ?? '';
    $address     = $_POST['address'] ?? '';
    $contact_no  = $_POST['contact_no'] ?? '';
    $company     = $_POST['company'] ?? '';
    $position    = $_POST['position'] ?? '';
    
    // Optional fields
    $advanced_degree = $_POST['advanced_degree'] ?? '';
    $masteral        = $_POST['masteral'] ?? '';
    $doctorate       = $_POST['doctorate'] ?? '';

    if (empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            $pdo->beginTransaction();

            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception("Email already registered.");
            }

            // Server-side Password Security Validation
            $hasNumber = preg_match('@[0-9]@', $password);
            $hasSpecial = preg_match('@[^\w]@', $password);
            if (strlen($password) < 8 || !$hasNumber || !$hasSpecial) {
                throw new Exception("Password must be at least 8 characters long and contain both numbers and special characters for security.");
            }

            // Insert into users
            $full_name = trim("$first_name $middle_name $last_name");
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_active, first_login) VALUES (?, ?, ?, 'alumni', TRUE, FALSE)");
            $stmt->execute([$full_name, $email, $password_hash]);
            $user_id = $pdo->lastInsertId();

            // Insert into alumni
            $stmt = $pdo->prepare("INSERT INTO alumni 
                (user_id, first_name, last_name, middle_name, gender, degree, college, batch_year, address, contact_no, company, job_title, advanced_degree, masteral, doctorate, verification_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            
            $stmt->execute([
                $user_id,
                $first_name,
                $last_name,
                $middle_name,
                $gender,
                $degree,
                $college,
                $batch_year ?: null,
                $address,
                $contact_no,
                $company,
                $position,
                $advanced_degree,
                $masteral,
                $doctorate
            ]);

            $pdo->commit();
            $success = "Registration successful! You can now <a href='index.php' class='underline font-bold text-blue-600'>login</a>.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Lyceum Alumni Tracking System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        :root { --bg-surface: #eef2f7; --card-bg: #ffffff; --text-main: #1f2937; --text-muted: #6b7280; --border-clr: #f1f5f9; }
        .dark { --bg-surface: #0f172a; --card-bg: #1e293b; --text-main: #f8fafc; --text-muted: #94a3b8; --border-clr: #334155; }
        
        body { background-color: var(--bg-surface); min-height: 100vh; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; padding: 40px 20px; }
        .fade-slide-in { animation: fadeSlideIn 0.8s ease-out forwards; }
        @keyframes fadeSlideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .bg-gradient { background: linear-gradient(135deg, #1a3faa 0%, #1e56d9 100%); }
        .glass-card { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid var(--border-clr); }
        .dark .glass-card { background: rgba(30, 41, 59, 0.95); }
        
        .input-field { 
            width: 100%; height: 44px; background-color: rgba(0,0,0,0.03); border-radius: 12px; padding: 0 16px; 
            transition: all 0.2s; border: 1px solid transparent; outline: none; color: var(--text-main);
        }
        .dark .input-field { background-color: rgba(255,255,255,0.05); }
        .input-field:focus { background-color: #fff; border-color: #2563eb; box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1); }
        .dark .input-field:focus { background-color: #1e293b; }
        
        label { display: block; font-size: 13px; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; margin-left: 4px; }
    </style>
    
    <script>
        function togglePass() {
            const input = document.getElementById('password-input');
            const icon = document.getElementById('eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            }
        }

        function checkStrength(pass) {
            let strength = 0;
            const label = document.getElementById('strength-label');
            const bars = [document.getElementById('bar-1'), document.getElementById('bar-2'), document.getElementById('bar-3'), document.getElementById('bar-4')];

            if (pass.length >= 8) strength++;
            if (pass.match(/[0-9]/)) strength++;
            if (pass.match(/[^A-Za-z0-9]/)) strength++;
            if (pass.match(/[A-Z]/)) strength++;

            // Reset bars
            bars.forEach(b => b.className = 'flex-1 rounded-full bg-slate-100 dark:bg-slate-800 transition-all duration-500');

            let color = 'bg-slate-400';
            let txt = 'Poor';

            if (strength === 1) { color = 'bg-rose-500'; txt = 'Poor'; }
            if (strength === 2) { color = 'bg-amber-500'; txt = 'Okay'; }
            if (strength === 3) { color = 'bg-blue-500'; txt = 'Good'; }
            if (strength === 4) { color = 'bg-emerald-500'; txt = 'Excellent'; }

            if (pass.length === 0) {
                label.innerText = 'Security: Poor';
                label.className = 'text-[9px] font-black uppercase tracking-widest text-slate-400';
            } else {
                label.innerText = 'Security: ' + txt;
                label.className = 'text-[9px] font-black uppercase tracking-widest text-' + color.split('-')[1] + '-600';
                for(let i=0; i<strength; i++) {
                    bars[i].classList.remove('bg-slate-100', 'dark:bg-slate-800');
                    bars[i].classList.add(color);
                }
            }
        }
    </script>
</head>
<body>
    <!-- Global Theme Toggle -->
    <div class="fixed top-8 right-8 z-50">
        <button onclick="toggleDarkMode()" class="p-3 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl text-blue-600 dark:text-white hover:scale-110 active:scale-95 transition-all shadow-xl">
            <span id="dm-icon" class="text-xl">🌙</span>
        </button>
    </div>

    <div class="w-full max-w-4xl fade-slide-in">
        <div class="glass-card rounded-[30px] shadow-2xl overflow-hidden flex flex-col md:flex-row">
            <!-- Left Branding Side -->
            <div class="bg-gradient w-full md:w-1/3 p-10 flex flex-col justify-between text-white">
                <div class="relative z-10">
                    <div class="w-20 h-20 mb-8 p-2 bg-white/10 rounded-2xl backdrop-blur-md border border-white/20">
                        <img src="loalogo.png" alt="Logo" class="w-full h-full object-contain">
                    </div>
                    <h1 class="text-3xl font-extrabold uppercase tracking-tight">Join Our Alumni</h1>
                    <p class="text-blue-100 mt-2 text-sm">Be part of the ever-growing Lycean community.</p>
                </div>
                <div class="mt-12 relative z-10">
                    <p class="text-xs opacity-60">Already have an account?</p>
                    <a href="index.php" class="inline-block mt-2 px-6 py-2 bg-white/10 border border-white/20 rounded-xl font-bold hover:bg-white/20 transition-all">Sign In</a>
                </div>
                
                <!-- Decorative Blobs -->
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full blur-3xl -mr-16 -mt-16"></div>
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-white/10 rounded-full blur-3xl -ml-16 -mb-16"></div>
            </div>

            <!-- Right Form Side -->
            <div class="w-full md:w-2/3 p-10 md:p-12">
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-2">Create Account</h2>
                <p class="text-gray-500 dark:text-gray-400 text-sm mb-8">Please fill in your details to register.</p>

                <?php if ($error): ?>
                    <div class="bg-red-50 dark:bg-red-900/20 text-red-600 dark:text-red-400 p-4 rounded-xl text-sm mb-6 border border-red-100 dark:border-red-900/40">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="bg-green-50 dark:bg-green-900/20 text-green-600 dark:text-green-400 p-4 rounded-xl text-sm mb-6 border border-green-100 dark:border-green-900/40">
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form action="register.php" method="POST" class="space-y-6">
                    <!-- Name Section -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label>First Name*</label>
                            <input type="text" name="first_name" required class="input-field" placeholder="Juan">
                        </div>
                        <div>
                            <label>Middle Name</label>
                            <input type="text" name="middle_name" class="input-field" placeholder="Protacio">
                        </div>
                        <div>
                            <label>Last Name*</label>
                            <input type="text" name="last_name" required class="input-field" placeholder="Dela Cruz">
                        </div>
                    </div>

                    <!-- Auth Section -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>Email Address*</label>
                            <input type="email" name="email" required class="input-field" placeholder="juan@example.com">
                        </div>
                        <div class="relative group">
                            <label class="flex justify-between items-center mb-2">
                                <span>Password*</span>
                                <span id="strength-label" class="text-[9px] font-black uppercase tracking-widest text-slate-400">Security: Poor</span>
                            </label>
                            <div class="relative">
                                <input type="password" name="password" id="password-input" required 
                                    class="input-field pr-12 focus:ring-2 focus:ring-blue-500/20 transition-all" 
                                    placeholder="••••••••" 
                                    onkeyup="checkStrength(this.value)">
                                <button type="button" onclick="togglePass()" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-blue-500 transition-colors">
                                    <svg id="eye-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                            </div>
                            <!-- Strength Meter Bar -->
                            <div class="mt-3 flex gap-1 h-1">
                                <div id="bar-1" class="flex-1 rounded-full bg-slate-100 dark:bg-slate-800 transition-all duration-500"></div>
                                <div id="bar-2" class="flex-1 rounded-full bg-slate-100 dark:bg-slate-800 transition-all duration-500"></div>
                                <div id="bar-3" class="flex-1 rounded-full bg-slate-100 dark:bg-slate-800 transition-all duration-500"></div>
                                <div id="bar-4" class="flex-1 rounded-full bg-slate-100 dark:bg-slate-800 transition-all duration-500"></div>
                            </div>
                            <p class="text-[9px] text-slate-400 mt-2 italic flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Use 8+ characters, numbers, and symbols.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>Gender*</label>
                            <select name="gender" required class="input-field appearance-none cursor-pointer">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label>Batch Year*</label>
                            <input type="number" name="batch_year" required class="input-field" placeholder="2024" min="1950" max="<?php echo date('Y') + 1; ?>">
                        </div>
                    </div>

                    <!-- Academic Section -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>Degree*</label>
                            <input type="text" name="degree" required class="input-field" placeholder="BS in Information Technology">
                        </div>
                        <div>
                            <label>College*</label>
                            <select name="college" required class="input-field appearance-none cursor-pointer">
                                <option value="">Select College</option>
                                <option value="College of Arts and Sciences">College of Arts and Sciences</option>
                                <option value="College of Accountancy">College of Accountancy</option>
                                <option value="College of Business Administration">College of Business Administration</option>
                                <option value="College of Computer Studies">College of Computer Studies</option>
                                <option value="College of Customs Administration">College of Customs Administration</option>
                                <option value="College of Criminal Justice">College of Criminal Justice</option>
                                <option value="College Education">College Evaluation</option>
                                <option value="College of Engineering">College of Engineering</option>
                                <option value="College of Real Estate Management">College of Real Estate Management</option>
                                <option value="College of Tourism and Hospitality Management">College of Tourism and Hospitality Management</option>
                            </select>
                        </div>
                    </div>

                    <!-- Advanced Academic -->
                    <div class="p-6 bg-gray-50 dark:bg-white/5 rounded-2xl space-y-4 border border-gray-100 dark:border-white/10">
                        <h3 class="text-sm font-bold text-gray-700 dark:text-gray-300">Advanced Academic (Optional)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label>Other Advance Degree</label>
                                <input type="text" name="advanced_degree" class="input-field" placeholder="Certifications">
                            </div>
                            <div>
                                <label>Masteral</label>
                                <input type="text" name="masteral" class="input-field" placeholder="Master of Science">
                            </div>
                            <div>
                                <label>Doctorate</label>
                                <input type="text" name="doctorate" class="input-field" placeholder="Ph.D.">
                            </div>
                        </div>
                    </div>

                    <!-- Contact -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>Contact No.*</label>
                            <input type="text" name="contact_no" required class="input-field" placeholder="0917XXXXXXX">
                        </div>
                        <div>
                            <label>Address*</label>
                            <input type="text" name="address" required class="input-field" placeholder="City, Province">
                        </div>
                    </div>

                    <!-- Work -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label>Current Company</label>
                            <input type="text" name="company" class="input-field" placeholder="Company Name">
                        </div>
                        <div>
                            <label>Position</label>
                            <input type="text" name="position" class="input-field" placeholder="Job Title">
                        </div>
                    </div>

                    <div class="pt-4">
                        <button type="submit" class="w-full h-12 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 active:scale-[0.98] transition-all shadow-lg flex items-center justify-center space-x-2">
                            <span>Register Now</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function updateDarkModeUI(isDark) {
            const icon = document.getElementById('dm-icon');
            if (isDark) {
                document.documentElement.classList.add('dark');
                if(icon) icon.innerText = '☀️';
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                if(icon) icon.innerText = '🌙';
                localStorage.setItem('theme', 'light');
            }
        }

        function toggleDarkMode() {
            const isDark = document.documentElement.classList.contains('dark');
            updateDarkModeUI(!isDark);
        }

        if (localStorage.getItem('theme') === 'dark' || (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            updateDarkModeUI(true);
        }
    </script>
</body>
</html>
