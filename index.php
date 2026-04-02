<?php
// index.php (Login Page)
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = null;

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    $result = handleLogin($email, $password, $pdo);
    if ($result['success']) {
        // Fetch first_login status
        $stmt = $pdo->prepare("SELECT role, first_login FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user['role'] === 'admin') {
            header('Location: pages/dashboard.php'); // Admin uses main dashboard
        } elseif ($user['first_login']) {
            header('Location: pages/setup-profile.php');
        } else {
            header('Location: pages/dashboard.php');
        }
        exit;
    } else {
        $error = $result['message'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Lyceum of Alabang Alumni Tracking System</title>
    <!-- Tailwind via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        :root { --bg-surface: #eef2f7; --card-bg: #ffffff; --text-main: #1f2937; --text-muted: #6b7280; --border-clr: #f1f5f9; }
        .dark { --bg-surface: #0f172a; --card-bg: #1e293b; --text-main: #f8fafc; --text-muted: #94a3b8; --border-clr: #334155; }
        
        body { background-color: var(--bg-surface); overflow: hidden; height: 100vh; display: flex; align-items: center; justify-content: center; transition: all 0.3s ease; }
        .fade-slide-in { animation: fadeSlideIn 0.8s ease-out forwards; }
        @keyframes fadeSlideIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .left-panel { background: linear-gradient(135deg, #1a3faa 0%, #1e56d9 100%); }
        .glass-badge { background: rgba(255, 255, 255, 0.1); backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.2); }
        @keyframes blobFloat { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(20px, 30px) scale(1.1); } }
        .blob { position: absolute; border-radius: 50%; background: rgba(255, 255, 255, 0.1); animation: blobFloat 15s infinite ease-in-out; }
        
        .login-card { background-color: var(--card-bg) !important; color: var(--text-main) !important; }
        .text-gray-800 { color: var(--text-main) !important; }
        .text-gray-500, .text-gray-600 { color: var(--text-muted) !important; }
        .bg-white { background-color: var(--card-bg) !important; }
        .bg-gray-50 { background-color: rgba(0,0,0,0.02) !important; border: 1px solid var(--border-clr) !important; color: var(--text-main) !important; }
    </style>
</head>
<body>
    <!-- Global Theme Toggle (Login) -->
    <div class="fixed top-8 right-8 z-50">
        <button onclick="toggleDarkMode()" class="p-3 bg-white/10 backdrop-blur-md border border-white/20 rounded-2xl text-white hover:scale-110 active:scale-95 transition-all shadow-xl">
            <span id="dm-icon-header" class="text-xl">🌙</span>
        </button>
    </div>

    <div class="w-full max-w-4xl h-[600px] p-5">
        <div class="login-card flex bg-white rounded-[20px] h-full overflow-hidden shadow-2xl fade-slide-in">
            
            <!-- Left Panel (40%) -->
            <div class="left-panel relative w-2/5 p-10 flex flex-col justify-center items-center text-center text-white overflow-hidden">
                <div class="blob w-40 h-40 top-1/4 left-0" style="animation-delay: 0s;"></div>
                <div class="blob w-20 h-20 bottom-1/4 right-0" style="animation-delay: -5s;"></div>
                
                <div class="relative z-10">
                    <div class="glass-badge w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6 shadow-lg border-2 border-white/20">
                        <img src="loalogo.png" alt="LATS logo" class="w-16 h-16 object-contain">
                    </div>
                    <h1 class="text-4xl font-extrabold tracking-wider">WELCOME</h1>
                    <h2 class="text-[#f5c842] text-xl font-semibold tracking-[4px] mt-2">ALUMNI</h2>
                    <p class="text-xs opacity-80 mt-6">Lyceum of Alabang Alumni Tracking System</p>
                    <p class="text-sm italic opacity-70 mt-8 leading-relaxed">"Elevate your professional journey... Stay connected, share your success, and contribute to the legacy of your alma mater."</p>
                </div>
            </div>

            <!-- Right Panel (60%) -->
            <div class="w-3/5 p-16 flex items-center bg-white">
                <div class="w-full max-w-sm mx-auto">
                    <h2 class="text-3xl font-bold text-gray-800">Sign in</h2>
                    <p class="text-gray-500 mt-2 mb-8">Enter your credentials to access the portal.</p>

                    <?php if ($error): ?>
                        <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-6 border border-red-100"><?php echo $error; ?></div>
                    <?php endif; ?>

                    <form action="index.php" method="POST" class="space-y-4">
                        <div class="relative">
                            <span class="absolute left-3 top-3 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                            </span>
                            <input type="email" name="email" placeholder="Email Address" required class="w-full h-11 bg-gray-50 rounded-xl pl-11 pr-4 focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all outline-none border-none">
                        </div>

                        <div class="relative">
                            <span class="absolute left-3 top-3 text-gray-400">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" /></svg>
                            </span>
                            <input type="password" id="password" name="password" placeholder="Password" required class="w-full h-11 bg-gray-50 rounded-xl pl-11 pr-16 focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all outline-none border-none">
                            <button type="button" onclick="togglePass()" class="absolute right-3 top-3 text-xs font-bold text-blue-600 hover:text-blue-700 tracking-wider">SHOW</button>
                        </div>

                        <div class="flex justify-between items-center text-sm py-2">
                            <label class="flex items-center text-gray-600 cursor-pointer">
                                <input type="checkbox" class="rounded text-blue-600 mr-2"> Remember me
                            </label>
                            <a href="#" class="text-blue-600 font-medium hover:underline">Forgot Password?</a>
                        </div>

                        <button type="submit" id="loginBtn" class="w-full h-11 bg-[#2563eb] text-white rounded-full font-bold hover:bg-blue-700 active:scale-95 transition-all shadow-md mt-4 flex items-center justify-center group">
                            <span id="btnText">Sign In</span>
                            <svg id="spinner" class="animate-spin h-5 w-5 text-white hidden" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        </button>

                    <p class="text-center text-gray-400 text-xs mt-10">
                        &copy; 2026 Lyceum of Alabang – Alumni System
                    </p>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function updateDarkModeUI(isDark) {
            const headerIcon = document.getElementById('dm-icon-header');
            if (isDark) {
                document.documentElement.classList.add('dark');
                if(headerIcon) headerIcon.innerText = '☀️';
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.classList.remove('dark');
                if(headerIcon) headerIcon.innerText = '🌙';
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

        function togglePass() {
            const pass = document.getElementById('password');
            const btn = event.target;
            if (pass.type === 'password') {
                pass.type = 'text';
                btn.innerText = 'HIDE';
            } else {
                pass.type = 'password';
                btn.innerText = 'SHOW';
            }
        }

        document.querySelector('form').onsubmit = function() {
            const btnText = document.getElementById('btnText');
            const spinner = document.getElementById('spinner');
            const loginBtn = document.getElementById('loginBtn');
            btnText.classList.add('hidden');
            spinner.classList.remove('hidden');
            loginBtn.disabled = true;
        };
    </script>
</body>
</html>
