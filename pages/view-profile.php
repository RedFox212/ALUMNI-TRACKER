<?php
// pages/view-profile.php — THE EXECUTIVE DARK DOSSIER
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$view_id   = (int)($_GET['id'] ?? 0);
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

if (!$view_id) { header('Location: directory.php'); exit; }

// Fetch the user + alumni profile (including NEW DIGITAL LINKS)
$stmt = $pdo->prepare(
    "SELECT u.id as user_id, u.name, u.email, u.role, u.created_at,
            a.student_id, a.program, a.batch_year, a.address, a.contact_no,
            a.advanced_degree, a.employment_status, a.company, a.job_title,
            a.position_level, a.discipline_match, a.years_experience, a.date_started,
            a.resume_link, a.website_link, a.portfolio_link, a.linkedin_link
     FROM users u
     LEFT JOIN alumni a ON u.id = a.user_id
     WHERE u.id = ? AND u.role = 'alumni' AND u.is_active = 1"
);
$stmt->execute([$view_id]);
$person = $stmt->fetch();

if (!$person) { header('Location: directory.php?error=notfound'); exit; }

// Years of experience calculation
$exp_years = 0;
if (!empty($person['date_started'])) {
    $start     = new DateTime($person['date_started']);
    $exp_years = $start->diff(new DateTime())->y;
}
$exp_total = max($exp_years, $person['years_experience'] ?? 0);

// Is this the viewer's own profile?
$is_own = ($view_id == $_SESSION['user_id']);

$emp_status_colors = [
    'Employed'      => 'bg-green-500/10 text-green-500 border-green-500/20',
    'Self-employed' => 'bg-blue-500/10 text-blue-500 border-blue-500/20',
    'Freelancing'   => 'bg-teal-500/10 text-teal-500 border-teal-500/20',
    'Unemployed'    => 'bg-rose-500/10 text-rose-500 border-rose-500/20',
    'Studying'      => 'bg-amber-500/10 text-amber-500 border-amber-500/20',
];
$emp_color_class = $emp_status_colors[$person['employment_status'] ?? ''] ?? 'bg-slate-500/10 text-slate-500 border-slate-500/20';
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($person['name']); ?> Dossier – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&family=Outfit:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .font-outfit { font-family: 'Outfit', sans-serif; }
        .glass-dark {
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .bg-mesh {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(30, 58, 138, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(127, 29, 29, 0.1) 0px, transparent 50%);
        }
    </style>
</head>
<body class="bg-mesh min-h-screen flex text-slate-300 antialiased">

<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <header class="h-20 glass-dark border-b border-white/5 px-8 flex items-center justify-between sticky top-0 z-50">
        <div class="flex items-center gap-6">
            <a href="directory.php" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white/5 hover:bg-white/10 transition-all text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <h1 class="font-outfit font-black text-white text-xl tracking-tighter uppercase italic">Alumni Profile</h1>
        </div>
        <?php if ($is_own): ?>
            <a href="edit-profile.php" class="h-10 px-6 bg-blue-600 text-white font-black text-[10px] uppercase tracking-widest rounded-2xl hover:bg-blue-500 flex items-center gap-2 transition-all active:scale-95 shadow-xl shadow-blue-900/20">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                Modify My Record
            </a>
        <?php endif; ?>
    </header>

    <div class="p-8 max-w-5xl mx-auto w-full">
        
        <!-- Hero Dossier -->
        <div class="glass-dark rounded-[48px] overflow-hidden border border-white/5 mb-10 shadow-2xl relative">
            <!-- Strategic Header Strip -->
            <div class="h-40 bg-gradient-to-r from-slate-900 via-blue-900/30 to-slate-900 relative">
                <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(#ffffff 1px, transparent 0); background-size: 24px 24px;"></div>
                <div class="absolute inset-0 bg-mesh opacity-40"></div>
            </div>
            
            <div class="px-12 pb-12">
                <div class="flex flex-col md:flex-row items-start md:items-end gap-8 -mt-20 mb-10">
                    <div class="relative group">
                        <div class="absolute -inset-4 bg-blue-500/20 rounded-full blur-2xl opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <div class="w-40 h-40 flex-shrink-0 ring-8 ring-slate-900 rounded-full shadow-2xl relative z-10">
                            <?php echo renderAvatar($person['name'], 'w-full h-full text-5xl border-4 border-white/5'); ?>
                        </div>
                    </div>
                    
                    <div class="flex-1 pb-4 relative z-10">
                        <div class="flex flex-wrap items-center gap-3 mb-4">
                            <span class="px-4 py-1.5 bg-blue-500/10 text-blue-500 text-[10px] font-black rounded-full uppercase tracking-widest border border-blue-500/20 italic font-outfit"><?php echo htmlspecialchars($person['program']); ?></span>
                            <span class="px-4 py-1.5 bg-white/5 text-slate-400 text-[10px] font-black rounded-full uppercase tracking-widest border border-white/10">Legacy Batch <?php echo $person['batch_year']; ?></span>
                            <span class="px-4 py-1.5 text-[10px] font-black rounded-full uppercase tracking-widest border <?php echo $emp_color_class; ?>"><?php echo htmlspecialchars($person['employment_status'] ?? 'Status Unknown'); ?></span>
                        </div>
                        <h1 class="text-5xl font-outfit font-black text-white tracking-tighter uppercase italic mb-2"><?php echo htmlspecialchars($person['name']); ?></h1>
                        <p class="text-xl font-medium text-slate-400 italic">
                            <?php echo htmlspecialchars($person['job_title'] ?? 'Strategic Explorer'); ?>
                            <span class="mx-3 text-slate-700">|</span>
                            <span class="text-slate-300"><?php echo htmlspecialchars($person['company'] ?? 'Add your employer'); ?></span>
                        </p>
                    </div>
                </div>

                <!-- Digital Integration Links -->
                <div class="flex flex-wrap gap-4 mb-10 border-y border-white/5 py-8">
                    <?php if ($person['linkedin_link']): ?>
                    <a href="<?php echo htmlspecialchars($person['linkedin_link']); ?>" target="_blank" class="h-12 px-6 bg-white/5 rounded-2xl flex items-center gap-3 hover:bg-blue-600 hover:text-white transition-all group border border-white/5">
                        <svg class="w-5 h-5 text-blue-500 group-hover:text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                        <span class="text-xs font-black uppercase tracking-widest">Connect on LinkedIn</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($person['portfolio_link']): ?>
                    <a href="<?php echo htmlspecialchars($person['portfolio_link']); ?>" target="_blank" class="h-12 px-6 bg-white/5 rounded-2xl flex items-center gap-3 hover:bg-purple-600 hover:text-white transition-all group border border-white/5">
                        <svg class="w-5 h-5 text-purple-500 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span class="text-xs font-black uppercase tracking-widest">View Portfolio</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($person['resume_link']): ?>
                    <a href="<?php echo htmlspecialchars($person['resume_link']); ?>" target="_blank" class="h-12 px-6 bg-white/5 rounded-2xl flex items-center gap-3 hover:bg-rose-600 hover:text-white transition-all group border border-white/5">
                        <span class="w-5 h-5 flex items-center justify-center font-black text-[10px] text-rose-500 group-hover:text-white">CV</span>
                        <span class="text-xs font-black uppercase tracking-widest">Download Resume</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($person['website_link']): ?>
                    <a href="<?php echo htmlspecialchars($person['website_link']); ?>" target="_blank" class="h-12 px-6 bg-white/5 rounded-2xl flex items-center gap-3 hover:bg-slate-700 hover:text-white transition-all group border border-white/5">
                        <svg class="w-5 h-5 text-slate-500 group-hover:text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"/></svg>
                        <span class="text-xs font-black uppercase tracking-widest">Site</span>
                    </a>
                    <?php endif; ?>
                </div>

                    <div class="bg-white/5 rounded-[32px] p-8 border border-white/5 shadow-inner md:col-span-3">
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[3px] mb-4">Professional Milestone</p>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-2xl bg-purple-500/10 text-purple-500 flex items-center justify-center border border-white/5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <p class="font-outfit font-black text-white uppercase tracking-tighter"><?php echo $exp_total; ?>+ Years of Excellence</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alumni Information Details -->
        <div class="glass-dark rounded-[40px] p-10 border border-white/5">
            <h2 class="font-outfit font-black text-white text-xl uppercase tracking-tighter italic mb-8 border-b border-white/5 pb-4">Alumni Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
                <div class="space-y-6">
                    <div>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[3px] mb-2">Institutional Email</p>
                        <p class="text-white font-bold tracking-tight"><?php echo htmlspecialchars($person['email']); ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[3px] mb-2">Primary Contact</p>
                        <p class="text-white font-bold tracking-tight"><?php echo htmlspecialchars($person['contact_no'] ?? 'Unlisted'); ?></p>
                    </div>
                </div>
                <div class="space-y-6">
                    <div>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[3px] mb-2">Residential Area</p>
                        <p class="text-white font-bold tracking-tight line-clamp-2"><?php echo htmlspecialchars($person['address'] ?? 'Private Registry'); ?></p>
                    </div>
                    <div>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[3px] mb-2">Academic Credentials</p>
                        <p class="text-blue-500 font-bold tracking-tight"><?php echo htmlspecialchars($person['advanced_degree'] ?? 'Bachelor Undergraduate'); ?></p>
                    </div>
                </div>
                
                <!-- DIGITAL PRESENCE INTEGRATION (REPEATED FOR VISIBILITY) -->
                <div class="space-y-6 md:col-span-2 lg:col-span-1">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-[3px] mb-2">Digital Connections</p>
                    <div class="flex flex-col gap-3">
                        <?php if ($person['linkedin_link']): ?>
                        <a href="<?php echo htmlspecialchars($person['linkedin_link']); ?>" target="_blank" class="text-xs font-bold text-blue-400 hover:text-blue-300 transition-all flex items-center gap-2">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z"/></svg>
                            LinkedIn Profile
                        </a>
                        <?php endif; ?>
                        <?php if ($person['portfolio_link']): ?>
                        <a href="<?php echo htmlspecialchars($person['portfolio_link']); ?>" target="_blank" class="text-xs font-bold text-purple-400 hover:text-purple-300 transition-all flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Professional Portfolio
                        </a>
                        <?php endif; ?>
                        <?php if (!$person['linkedin_link'] && !$person['portfolio_link']): ?>
                        <p class="text-[10px] text-slate-600 italic">No external links provided.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>
</body>
</html>
