<?php
// pages/my-id.php — Personal Digital Alumni ID Card
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$alumni = $pdo->query("SELECT u.name, a.* FROM users u JOIN alumni a ON u.id = a.user_id WHERE u.id = $user_id")->fetch();

if (!$alumni['alumni_id_num']) {
    $error = "Your Alumni ID is currently being processed by the administration. Check back shortly.";
}

// Data for QR Code - Now points to a real public verification portal
$verify_path = str_replace('my-id.php', 'verify-id.php', $_SERVER['REQUEST_URI']);
$verify_url = "http://" . $_SERVER['HTTP_HOST'] . $verify_path . "?id=" . ($alumni['alumni_id_num'] ?? 'PENDING');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Identity – Lyceum</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body{font-family:'Inter',sans-serif;}
        .id-card { perspective: 1000px; }
        .card-inner {
            transition: transform 0.8s cubic-bezier(0.4, 0, 0.2, 1);
            transform-style: preserve-3d;
        }
        .id-card:hover .card-inner { transform: rotateY(180deg); }
        .back { transform: rotateY(180deg); backface-visibility: hidden; }
        .front { backface-visibility: hidden; }
        .shadow-glow { box-shadow: 0 0 25px rgba(37, 99, 235, 0.2); }
        
        .modal-overlay { background: rgba(0,0,0,0.9); backdrop-filter: blur(12px); display: none; }
        .modal-overlay.active { display: flex; }

        @media print { .no-print { display: none; } body { background: white; } .id-card { transform: none !important; } }
    </style>
</head>
<body class="bg-slate-50 transition-colors duration-500 min-h-screen">
    <?php require_once '../includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col lg:ml-64">
        <?php $topbar_title = 'Digital Identity'; $topbar_subtitle = 'Institutional Credential Tracking'; require_once '../includes/topbar.php'; ?>

        <div class="p-8 flex flex-col items-center justify-center flex-1">
            <?php if (isset($error)): ?>
                <div class="text-center bg-white p-16 rounded-[64px] shadow-sm border border-slate-100 max-w-lg">
                    <div class="text-7xl mb-6">🏰</div>
                    <h2 class="text-3xl font-black text-slate-800 uppercase tracking-tighter italic">Pending ID</h2>
                    <p class="text-slate-400 mt-4 font-medium italic"><?php echo $error; ?></p>
                </div>
            <?php else: ?>
                <div class="id-card w-[420px] h-[600px] cursor-pointer group mb-12">
                    <div class="card-inner relative w-full h-full shadow-[0_35px_60px_-15px_rgba(0,0,0,0.3)] rounded-[48px]">
                        
                        <!-- FRONT -->
                        <div class="front absolute inset-0 bg-white rounded-[48px] border-4 border-slate-50 overflow-hidden flex flex-col pt-12 shadow-inner">
                            <div class="px-10 flex items-center justify-between mb-10">
                                <img src="../loalogo.png" class="w-14 h-14 object-contain" alt="Logo">
                                <div class="text-right">
                                    <p class="text-[9px] font-black uppercase tracking-[3px] text-slate-300 italic">Verified Alumni</p>
                                    <p class="text-[11px] font-black uppercase text-blue-600 tracking-tighter">Credential Portal</p>
                                </div>
                            </div>

                            <div class="flex flex-col items-center flex-1">
                                <div class="w-44 h-44 rounded-full border-[12px] border-slate-50 shadow-2xl overflow-hidden mb-8 ring-4 ring-blue-500/10 scale-100 group-hover:scale-105 transition-transform duration-500">
                                    <?php echo renderAvatar($alumni['name'], 'w-full h-full text-5xl font-black bg-slate-900 text-white'); ?>
                                </div>
                                <h1 class="text-3xl font-black text-slate-800 uppercase tracking-tighter italic"><?php echo htmlspecialchars($alumni['name']); ?></h1>
                                <p class="text-blue-600 font-bold text-sm uppercase tracking-[3px] mt-2 italic"><?php echo htmlspecialchars($alumni['program']); ?></p>
                                <p class="text-slate-300 text-[11px] font-black uppercase tracking-[5px] mt-4 italic opacity-80">BATCH <?php echo $alumni['batch_year']; ?></p>
                            </div>

                            <div class="bg-slate-950 p-8 flex justify-between items-center mt-auto">
                                <div class="flex-1">
                                    <p class="text-[9px] font-black uppercase tracking-[4px] text-slate-500 mb-1 italic">Institutional ID</p>
                                    <p class="text-lg font-black text-white tracking-[4px] italic leading-none"><?php echo $alumni['alumni_id_num']; ?></p>
                                </div>
                                <div class="h-12 w-[1px] bg-white/10 rounded-full mx-6"></div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-[9px] font-black uppercase tracking-[4px] text-slate-500 mb-1 italic">Issued On</p>
                                    <p class="text-[11px] font-black text-slate-200 uppercase tracking-widest italic"><?php echo date('M Y'); ?></p>
                                </div>
                            </div>
                        </div>

                        <!-- BACK -->
                        <div class="back absolute inset-0 bg-[#070b14] rounded-[48px] overflow-hidden flex flex-col p-12 text-center items-center justify-center border-4 border-slate-900/50">
                            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_var(--tw-gradient-stops))] from-blue-900/10 via-transparent to-transparent"></div>
                            
                            <h3 class="text-white text-lg font-black uppercase tracking-[6px] mb-12 italic relative z-10 animate-pulse">Lyceum</h3>
                            
                            <div class="bg-white p-6 rounded-[40px] mb-10 shadow-glow relative z-10 ring-8 ring-white/5 group-hover:ring-white/10 transition-all">
                                <div id="qrcode" class="w-40 h-40"></div>
                            </div>

                            <p class="text-slate-400 text-[10px] font-black leading-relaxed max-w-xs uppercase tracking-widest opacity-50 italic px-4 relative z-10">
                                Scan this institutional marker to independently verify the authenticity of this alumni credential.
                            </p>

                            <div class="mt-auto pt-10 relative z-10">
                                <div class="h-[1px] w-40 bg-white/10 rounded-full mx-auto mb-6"></div>
                                <p class="text-[9px] font-black text-slate-500 tracking-[8px] uppercase italic opacity-40">LYCEUM ALUMNI BOARD</p>
                            </div>
                        </div>

                    </div>
                </div>
                
                <div id="urlDisplay" class="mb-8 text-[10px] font-black text-slate-300 uppercase tracking-widest no-print">
                    Verification Node: <span class="text-blue-500" id="currentUrl">Loading Node...</span>
                </div>

                <div class="flex gap-4 no-print relative z-20">
                    <button onclick="window.print()" class="px-12 py-5 bg-slate-900 text-white rounded-[24px] font-black shadow-2xl hover:bg-blue-600 transition-all uppercase tracking-widest text-[10px] italic active:scale-95">Print Card</button>
                    <button onclick="toggleNetworkModal()" class="px-8 py-5 bg-blue-600 text-white rounded-[24px] font-black shadow-lg shadow-blue-500/20 hover:scale-105 transition-all uppercase tracking-widest text-[10px] italic">Go Public (Secure)</button>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Network Modal -->
    <div id="networkModal" class="modal-overlay fixed inset-0 z-[100] items-center justify-center p-6">
        <div class="bg-white dark:bg-slate-950 rounded-[48px] p-12 max-w-md w-full shadow-2xl border border-white/5">
            <h2 class="text-2xl font-black text-slate-800 dark:text-white uppercase italic mb-4 tracking-tighter">Secure Public Node</h2>
            
            <div class="bg-blue-50 dark:bg-blue-900/10 p-6 rounded-3xl mb-8 border border-blue-500/10">
                <p class="text-blue-600 dark:text-blue-400 text-[11px] font-black uppercase tracking-widest mb-2">Step 1: Open Terminal (CMD)</p>
                <code class="text-[10px] text-slate-500 dark:text-slate-400 font-mono block bg-white dark:bg-black/20 p-3 rounded-xl select-all">npx -y localtunnel --port 80</code>
            </div>

            <p class="text-slate-400 text-[11px] font-black uppercase tracking-widest mb-4">Step 2: Paste Secure Link</p>
            <div class="space-y-4">
                <input type="text" id="ipInput" placeholder="https://random-word.loca.lt" class="w-full bg-slate-50 dark:bg-white/5 rounded-2xl px-6 py-5 text-sm font-black border-none outline-none focus:ring-4 focus:ring-blue-500/20 text-slate-800 dark:text-white">
                <div class="flex gap-3">
                    <button onclick="applyIP()" class="flex-1 bg-blue-600 text-white py-5 rounded-2xl font-black text-[10px] uppercase tracking-[2px] shadow-xl hover:bg-blue-700 transition-all">Go Live</button>
                    <button onclick="toggleNetworkModal()" class="px-8 bg-slate-50 dark:bg-white/5 text-slate-400 py-5 rounded-2xl font-black text-[10px] uppercase tracking-widest hover:text-slate-900 transition-all">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let qr = null;
        const verifyPath = "<?php echo $verify_path; ?>";
        const alumniId = "<?php echo ($alumni['alumni_id_num'] ?? 'PENDING'); ?>";

        function generateQR(url) {
            document.getElementById("qrcode").innerHTML = "";
            document.getElementById("currentUrl").innerText = url;
            qr = new QRCode(document.getElementById("qrcode"), {
                text: url,
                width: 160,
                height: 160,
                colorDark : "#070b14",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        }

        window.addEventListener('DOMContentLoaded', (event) => {
            const initialUrl = "http://" + window.location.host + verifyPath + "?id=" + alumniId;
            generateQR(initialUrl);
        });

        function toggleNetworkModal() {
            const modal = document.getElementById('networkModal');
            modal.classList.toggle('active');
        }

        function applyIP() {
            let input = document.getElementById('ipInput').value.trim();
            if(!input) return alert("Please enter the secure link from your terminal.");
            
            // Auto-format to ensure it points to the verify-id path
            if (input.endsWith('/')) input = input.slice(0, -1);
            if (!input.toLowerCase().startsWith('http')) input = 'https://' + input;
            
            const newUrl = `${input}${verifyPath}?id=${alumniId}`;
            generateQR(newUrl);
            toggleNetworkModal();
            
            setTimeout(() => {
                const hub = document.querySelector('h3');
                hub.innerText = "NODE SECURED";
                hub.classList.add('text-green-500');
                setTimeout(() => {
                    hub.innerText = "LYCEUM";
                    hub.classList.remove('text-green-500');
                }, 3000);
            }, 500);
        }
    </script>
</body>
</html>
