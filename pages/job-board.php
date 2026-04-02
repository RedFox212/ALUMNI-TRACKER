<?php
// pages/job-board.php — Alumni: View/Post jobs
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$success = $error = null;

// Ensure schema is updated for Job Board
try {
    $pdo->exec("ALTER TABLE jobs ADD COLUMN IF NOT EXISTS status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'");
} catch(Exception $e) {}

// Handle Job Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job'])) {
    if (!verifyCsrf()) { $error = "Security mismatch."; }
    else {
        $job_id = (int)$_POST['job_id'];
        
        // Check permissions: Owner or Admin
        $stmt = $pdo->prepare("SELECT posted_by FROM jobs WHERE id = ?");
        $stmt->execute([$job_id]);
        $owner = $stmt->fetchColumn();
        
        if ($owner == $user_id || $user_role === 'admin') {
            $stmt = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
            $stmt->execute([$job_id]);
            $success = "Job listing has been permanently removed.";
        } else {
            $error = "Unauthorized action.";
        }
    }
}

// Handle Job Posting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_job'])) {
    if (!verifyCsrf()) { $error = "Security mismatch."; }
    else {
        // Enforce ONE post limit (Pending or Approved)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM jobs WHERE posted_by = ? AND status != 'rejected'");
        $stmt->execute([$user_id]);
        $existing_posts = $stmt->fetchColumn();

        if ($existing_posts > 0 && $user_role !== 'admin') {
            $error = "You already have an active job listing (pending or approved). Please remove your current one to post again.";
        } else {
            $title    = trim($_POST['title']);
            $company  = trim($_POST['company']);
            $location = trim($_POST['location'] ?? 'Remote');
            $type     = $_POST['job_type'] ?? 'Full-time';
            $desc     = trim($_POST['description']);
            $link     = trim($_POST['apply_link'] ?? '');

            if ($title && $company && $desc) {
                // Initial insert sets is_active to 0 so it stays hidden until admin approval
                $stmt = $pdo->prepare("INSERT INTO jobs (posted_by, title, company, location, job_type, description, apply_link, is_active, status) VALUES (?,?,?,?,?,?,?,0,'pending')");
                $stmt->execute([$user_id, $title, $company, $location, $type, $desc, $link]);
                $success = "Job submitted! It will appear on the board after admin approval.";
            } else {
                $error = "Please fill in all required fields.";
            }
        }
    }
}

// Fetch active approved jobs
$jobs = $pdo->query("SELECT j.*, u.name as poster_name FROM jobs j JOIN users u ON j.posted_by = u.id WHERE j.is_active = 1 AND j.status = 'approved' ORDER BY j.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Job Board – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;} .modal{display:none;} .modal.open{display:flex;}</style>
</head>
<body class="bg-slate-50 min-h-screen flex">
<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-64">
    <?php 
        $topbar_title = 'Job Board';
        $topbar_subtitle = 'Career Opportunities';
        $topbar_actions = '<button onclick="document.getElementById(\'postJobModal\').classList.add(\'open\')" class="bg-blue-600 text-white text-[10px] font-black px-5 py-2.5 rounded-2xl hover:bg-blue-700 transition-all uppercase tracking-widest shadow-lg shadow-blue-200">Post a Job</button>';
        require_once '../includes/topbar.php'; 
    ?>

    <div class="p-8 max-w-6xl mx-auto w-full">
        <div class="mb-10">
            <h1 class="text-3xl font-black text-slate-800 italic">OPPORTUNITIES</h1>
            <p class="text-slate-400 text-sm font-medium">Find jobs posted by fellow Lyceum Alumni or share openings at your firm.</p>
        </div>

        <?php if ($success): ?><div class="mb-6 bg-green-100 text-green-700 p-4 rounded-2xl text-sm font-bold animate-pulse">✅ <?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-6 bg-red-100 text-red-600 p-4 rounded-2xl text-sm font-bold">⚠️ <?php echo $error; ?></div><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($jobs as $job): ?>
            <div class="bg-white rounded-[32px] p-8 shadow-sm border border-slate-100 flex flex-col hover:shadow-xl hover:-translate-y-1 transition-all duration-300 group relative">
                
                <!-- Admin/Owner Actions -->
                <?php if ($job['posted_by'] == $user_id || $user_role === 'admin'): ?>
                <div class="absolute top-6 right-6 z-10">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to remove this job listing?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="job_id" value="<?php echo $job['id']; ?>">
                        <button type="submit" name="delete_job" class="w-8 h-8 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="flex items-start justify-between mb-6">
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center font-black text-xl group-hover:bg-blue-600 group-hover:text-white transition-colors">
                        <?php echo strtoupper(substr($job['company'], 0, 1)); ?>
                    </div>
                    <span class="px-3 py-1 bg-slate-50 text-slate-500 text-[10px] font-black rounded-full uppercase tracking-widest border border-slate-100 mr-8">
                        <?php echo $job['job_type']; ?>
                    </span>
                </div>
                
                <h3 class="text-xl font-black text-slate-800 leading-tight group-hover:text-blue-600 transition-colors uppercase tracking-tighter"><?php echo htmlspecialchars($job['title']); ?></h3>
                <p class="text-blue-600 text-sm font-bold mt-1"><?php echo htmlspecialchars($job['company']); ?></p>
                <p class="text-slate-400 text-xs font-medium flex items-center gap-1 mt-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <?php echo htmlspecialchars($job['location']); ?>
                </p>

                <div class="mt-6 flex-1 line-clamp-3 text-slate-500 text-sm leading-relaxed mb-8">
                    <?php echo nl2br(htmlspecialchars($job['description'])); ?>
                </div>

                <div class="pt-6 border-t border-slate-50 flex items-center justify-between">
                    <div>
                        <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest">Posted by</p>
                        <p class="text-xs font-bold text-slate-600"><?php echo htmlspecialchars($job['poster_name']); ?></p>
                    </div>
                    <?php if ($job['apply_link']): ?>
                    <a href="<?php echo htmlspecialchars($job['apply_link']); ?>" target="_blank" class="h-10 px-4 bg-slate-900 text-white rounded-xl flex items-center gap-2 text-xs font-black hover:bg-blue-600 transition-all">
                        Apply Now
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($jobs)): ?>
            <div class="col-span-full py-20 text-center bg-white rounded-[40px] border-2 border-dashed border-slate-200">
                <div class="text-5xl mb-4">🚀</div>
                <h3 class="text-xl font-bold text-slate-800">No jobs posted yet</h3>
                <p class="text-slate-400">Be the first to share an opportunity with your network!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Post Job Modal -->
<div id="postJobModal" class="modal fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 items-center justify-center p-4">
    <div class="bg-white rounded-[40px] w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="bg-blue-600 p-8 text-white">
            <h2 class="text-2xl font-black italic tracking-tighter uppercase">Share an Opportunity</h2>
            <p class="text-blue-100 text-sm mt-1">Help fellow alumni grow their careers.</p>
        </div>
        <form method="POST" class="p-8 space-y-4">
            <?php echo csrfField(); ?>
            <input type="hidden" name="post_job" value="1">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Job Title *</label>
                    <input type="text" name="title" required class="w-full h-12 bg-slate-50 rounded-2xl px-4 outline-none focus:ring-4 focus:ring-blue-100 transition-all font-medium">
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Company *</label>
                    <input type="text" name="company" required class="w-full h-12 bg-slate-50 rounded-2xl px-4 outline-none focus:ring-4 focus:ring-blue-100 transition-all font-medium">
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Location</label>
                    <input type="text" name="location" placeholder="e.g. Makati / Remote" class="w-full h-12 bg-slate-50 rounded-2xl px-4 outline-none focus:ring-4 focus:ring-blue-100 transition-all font-medium">
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Job Type</label>
                    <select name="job_type" class="w-full h-12 bg-slate-50 rounded-2xl px-4 outline-none focus:ring-4 focus:ring-blue-100 transition-all font-medium cursor-pointer">
                        <option>Full-time</option><option>Part-time</option><option>Freelance</option><option>Internship</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Apply Link / Email</label>
                    <input type="text" name="apply_link" placeholder="External URL or email" class="w-full h-12 bg-slate-50 rounded-2xl px-4 outline-none focus:ring-4 focus:ring-blue-100 transition-all font-medium">
                </div>
            </div>
            <div>
                <label class="block text-xs font-black text-slate-500 uppercase tracking-widest mb-2">Job Description *</label>
                <textarea name="description" rows="5" required class="w-full bg-slate-50 rounded-2xl px-4 py-3 outline-none focus:ring-4 focus:ring-blue-100 transition-all font-medium resize-none"></textarea>
            </div>
            <div class="flex gap-4 pt-4">
                <button type="submit" class="flex-1 h-12 bg-blue-600 text-white rounded-2xl font-black shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all">POST JOB</button>
                <button type="button" onclick="document.getElementById('postJobModal').classList.remove('open')" class="flex-1 h-12 bg-slate-100 text-slate-600 rounded-2xl font-black hover:bg-slate-200 transition-all">CANCEL</button>
            </div>
        </form>
    </div>
</div>

<script>document.getElementById('postJobModal').addEventListener('click', e=> { if(e.target===e.currentTarget) e.currentTarget.classList.remove('open'); });</script>
</body>
</html>
