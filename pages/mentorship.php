<?php
// pages/mentorship.php — Alumni: Find professional mentors
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$success = null;

// Handle request to become a mentor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_mentor'])) {
    if (verifyCsrf()) {
        $bio = $_POST['mentor_bio'] ?? '';
        $stmt = $pdo->prepare("UPDATE alumni SET mentor_status = 'pending', mentor_bio = ? WHERE user_id = ?");
        $stmt->execute([$bio, $user_id]);
        $success = "Your mentorship application has been submitted and is pending admin review.";
    }
}

// Fetch mentor status
$stmt = $pdo->prepare("SELECT mentor_status, mentor_bio FROM alumni WHERE user_id = ?");
$stmt->execute([$user_id]);
$my_status = $stmt->fetch();

// Fetch approved mentors for the directory
$mentors = $pdo->query("SELECT u.name, a.program, a.batch_year, a.mentor_bio, a.job_title, a.company, a.years_experience 
                        FROM users u 
                        JOIN alumni a ON u.id = a.user_id 
                        WHERE a.mentor_status = 'approved' 
                        LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mentorship Hub – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif; background: #f8fafc;}</style>
</head>
<body class="min-h-screen">
    <header class="h-20 bg-white border-b border-slate-100 sticky top-0 z-50 flex items-center px-8 justify-between">
        <a href="dashboard.php" class="flex items-center gap-2 text-slate-500 hover:text-slate-900 transition-all font-black text-xs uppercase tracking-widest">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Back to Dashboard
        </a>
        <div class="flex items-center gap-3">
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Alumni Mentors</span>
            <div class="w-10 h-10 bg-amber-50 rounded-xl flex items-center justify-center text-xl border border-amber-100">🎓</div>
        </div>
    </header>

    <div class="p-8 max-w-6xl mx-auto w-full">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <!-- Left: Hero & Opt-in -->
            <div class="lg:col-span-2">
                <div class="mb-12">
                    <h1 class="text-5xl font-black text-slate-900 leading-none tracking-tight mb-4 uppercase">BUILD YOUR <span class="text-amber-500 italic">LEGACY.</span></h1>
                    <p class="text-slate-500 text-lg font-medium leading-relaxed max-w-2xl">Connect with senior alumni for career guidance, or pay it forward by becoming a mentor to the next generation of Lyceum graduates.</p>
                </div>

                <?php if ($my_status['mentor_status'] === 'pending'): ?>
                <div class="mb-10 bg-amber-50 border-2 border-amber-100 rounded-[32px] p-8 flex items-center gap-6 shadow-xl shadow-amber-500/5 transition-all hover:scale-[1.01]">
                    <div class="w-16 h-16 bg-white rounded-2xl flex items-center justify-center text-3xl shadow-sm border border-amber-100 animate-bounce">⏳</div>
                    <div>
                        <h4 class="text-lg font-black text-slate-800 uppercase tracking-tighter">Application Under Review</h4>
                        <p class="text-slate-500 text-sm font-medium italic">Your mentorship profile is being verified by the Lyceum administration. Hang tight!</p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($success): ?><div class="mb-8 bg-amber-100 text-amber-700 p-6 rounded-3xl text-sm font-bold border border-amber-200">🚀 <?php echo $success; ?></div><?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($mentors as $m): ?>
                    <div class="bg-white rounded-[40px] p-8 shadow-sm border border-amber-100 hover:shadow-xl hover:shadow-amber-100/30 transition-all duration-500 group">
                        <div class="flex items-start gap-4 mb-6">
                            <?php echo renderAvatar($m['name'], 'w-16 h-16 text-xl shadow-lg border-4 border-white'); ?>
                            <div>
                                <h3 class="text-xl font-black text-slate-800 uppercase tracking-tighter group-hover:text-amber-600 transition-colors"><?php echo htmlspecialchars($m['name']); ?></h3>
                                <p class="text-amber-600 text-xs font-black uppercase tracking-[1px]"><?php echo htmlspecialchars($m['job_title']); ?></p>
                            </div>
                        </div>

                        <div class="space-y-2 mb-8">
                            <p class="text-sm font-medium text-slate-500 flex items-center gap-2">🏢 <?php echo htmlspecialchars($m['company']); ?></p>
                            <p class="text-sm font-medium text-slate-500 flex items-center gap-2">🎓 <?php echo htmlspecialchars($m['program']); ?></p>
                            <p class="text-sm font-bold text-slate-800 flex items-center gap-2">🏅 <?php echo $m['years_experience']; ?>+ Years Obs.</p>
                        </div>

                        <button class="w-full h-12 bg-slate-900 text-white rounded-2xl text-[10px] font-black uppercase tracking-widest hover:bg-amber-600 transition-all shadow-lg group-hover:scale-105">
                            REQUEST GUIDANCE
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Role Control -->
            <div class="space-y-6">
                <div class="bg-white rounded-[48px] p-10 shadow-sm border border-slate-100 sticky top-24">
                    <div class="w-20 h-20 bg-amber-50 rounded-3xl flex items-center justify-center text-4xl mb-8 border border-amber-100">🤝</div>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tighter uppercase mb-2">Your Role</h2>
                    <p class="text-slate-400 text-sm font-medium mb-10 leading-relaxed italic">Becoming a mentor doesn't mean you're an expert in everything—it means you're willing to share your journey.</p>
                    
                    <form method="POST">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="apply_mentor" value="1">
                        <div class="space-y-4">
                            <label class="field-label">Your Professional Bio</label>
                            <textarea name="mentor_bio" class="w-full h-32 bg-slate-50 border border-slate-100 rounded-3xl p-6 text-sm font-medium text-slate-800 outline-none focus:ring-4 focus:ring-amber-100 transition-all" placeholder="Tell us about your career journey..."><?php echo htmlspecialchars($my_status['mentor_bio'] ?? ''); ?></textarea>
                            
                            <button type="submit" class="w-full h-16 <?php echo $my_status['mentor_status'] === 'pending' ? 'bg-slate-100 text-slate-400 cursor-not-allowed' : 'bg-amber-500 text-white hover:bg-amber-600 shadow-xl shadow-amber-200'; ?> rounded-3xl text-xs font-black uppercase tracking-widest transition-all" <?php echo $my_status['mentor_status'] === 'pending' ? 'disabled' : ''; ?>>
                                <?php 
                                if ($my_status['mentor_status'] === 'pending') echo "⏳ Application Pending";
                                elseif ($my_status['mentor_status'] === 'approved') echo "✅ Update Mentor Bio";
                                else echo "🚀 Become a Mentor";
                                ?>
                            </button>
                        </div>
                    </form>

                    <div class="mt-12 pt-8 border-t border-slate-50">
                        <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest mb-4">Mentor Benefits</p>
                        <ul class="space-y-3 text-xs font-bold text-slate-500">
                            <li class="flex items-center gap-2">✅ Priority Alumni Badging</li>
                            <li class="flex items-center gap-2">✅ Personal Network Growth</li>
                            <li class="flex items-center gap-2">✅ Direct Industry Impact</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
</body>
</html>
