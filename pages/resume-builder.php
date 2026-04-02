<?php
// pages/resume-builder.php — Alumni: Auto-generate printable resume
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$user_id = $_SESSION['user_id'];

// Fetch latest profile
$stmt = $pdo->prepare("SELECT a.*, u.name, u.email 
                       FROM alumni a 
                       JOIN users u ON a.user_id = u.id 
                       WHERE a.user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

// If no alumni profile exists (e.g. for Admin), create a dummy profile so the builder doesn't crash 
// and only redirect REAL alumni to setup if they haven't finished it.
if (!$profile) {
    if ($_SESSION['user_role'] === 'admin') {
        $profile = [
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'job_title' => 'System Administrator',
            'company' => 'Lyceum of Alabang',
            'contact_no' => 'N/A',
            'address' => 'N/A',
            'program' => 'Administration',
            'batch_year' => date('Y'),
            'date_started' => date('Y-m-d'),
            'years_experience' => 'N/A',
            'website' => null,
            'advanced_degree' => null,
            'is_mentor' => 0
        ];
    } else {
        header('Location: setup-profile.php'); 
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resume Builder – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=EB+Garamond:wght@400;500;600;700;800&family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body{font-family:'EB Garamond',serif; background:#f1f5f9; color: #111;}
        .preview-body { font-family: 'EB Garamond', serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; margin: 0 !important; padding: 0 !important; }
            .resume-canvas { box-shadow: none !important; border: none !important; padding: 0 !important; width: 100% !important; margin: 0 !important; }
        }
        .resume-canvas { background: white; width: 210mm; min-height: 297mm; padding: 18mm 22mm; position: relative; shadow: 0 40px 100px -20px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; transform-origin: top center; overflow: hidden; }
        .edit-sidebar { width: 440px; background: #f8fafc; border-right: 1px solid #e2e8f0; height: calc(100vh - 80px); overflow-y: auto; font-family: 'Inter', sans-serif; padding: 40px; }
        .preview-area { flex: 1; background: #cbd5e1; height: calc(100vh - 80px); overflow-y: auto; padding: 60px 0; }
        .field-label { display: block; font-size: 11px; font-weight: 900; color: #64748b; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 10px; font-family: 'Inter', sans-serif; }
        .editor-input { width: 100%; height: 56px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 0 20px; font-size: 14px; font-weight: 600; color: #1e293b; outline: none; transition: all 0.2s; font-family: 'Inter', sans-serif; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .editor-input:focus { border-color: #3b82f6; background: #ffffff; ring: 4px solid #dbeafe; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        
        /* Harvard Executive Font Scaling */
        .section-title { border-bottom: 1.5px solid #000; font-weight: bold; font-size: 13pt; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: 12px; margin-top: 24px; padding-bottom: 2px; }
        .item-header { display: flex; justify-content: space-between; font-weight: bold; font-size: 12.5pt; }
        .item-sub { display: flex; justify-content: space-between; font-style: italic; font-size: 11.5pt; color: #222; margin-top: 4px; }
        .bullet-list { margin-left: 24px; list-style-type: disc; margin-top: 8px; font-size: 11.5pt; line-height: 1.6; color: #111; }
        .harvard-name { font-size: 38pt; line-height: 1; margin-bottom: 12px; font-weight: bold; }
        .harvard-contact { font-size: 11pt; }
    </style>
</head>
<body class="min-h-screen">

<nav class="h-20 bg-slate-900 text-white flex items-center justify-between px-10 border-b border-white/5 no-print sticky top-0 z-50">
    <div class="flex items-center gap-6">
        <a href="dashboard.php" class="text-slate-400 hover:text-white transition-all">← Back to Dashboard</a>
        <h1 class="text-xl font-black uppercase tracking-tighter italic">ALUMNI <span class="text-blue-500">RESUME</span> BUILDER</h1>
    </div>
    <button onclick="window.print()" class="h-11 px-8 bg-blue-600 text-sm font-black rounded-xl hover:bg-blue-700 active:scale-95 transition-all shadow-xl shadow-blue-600/30">
        🖨️ PRINT / DOWNLOAD PDF
    </button>
</nav>

<?php
// Handle form submission to save resume data
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resume'])) {
    if (verifyCsrf()) {
        try {
            $stmt2 = $pdo->query("SHOW COLUMNS FROM alumni LIKE 'skills'");
            if (!$stmt2->fetch()) {
                $pdo->exec("ALTER TABLE alumni ADD COLUMN skills TEXT DEFAULT NULL");
            }
            $stmt3 = $pdo->query("SHOW COLUMNS FROM alumni LIKE 'experience_summary'");
            if (!$stmt3->fetch()) {
                $pdo->exec("ALTER TABLE alumni ADD COLUMN experience_summary TEXT DEFAULT NULL");
            }
        } catch(Exception $e) {}

        $stmt = $pdo->prepare("UPDATE alumni SET 
            job_title = ?, 
            company = ?, 
            contact_no = ?, 
            address = ?, 
            website = ?,
            advanced_degree = ?,
            years_experience = ?,
            skills = ?,
            experience_summary = ?
            WHERE user_id = ?");
        $stmt->execute([
            $_POST['job_title'],
            $_POST['company'],
            $_POST['contact_no'],
            $_POST['address'],
            $_POST['website'],
            $_POST['advanced_degree'],
            (int)$_POST['years_experience'],
            $_POST['skills'],
            $_POST['experience_summary'],
            $user_id
        ]);
        header("Location: resume-builder.php?success=1");
        exit;
    }
}
?>

<div class="flex no-print">
    <!-- Editor Sidebar -->
    <aside class="edit-sidebar no-print p-8">
        <div class="mb-8">
            <h2 class="text-xs font-black text-blue-600 uppercase tracking-[2px] mb-2">Live Editor</h2>
            <p class="text-slate-400 text-xs font-medium">Changes appear in real-time below.</p>
        </div>

        <form method="POST" id="resumeForm" class="space-y-6">
            <?php echo csrfField(); ?>
            <input type="hidden" name="save_resume" value="1">
            
            <div>
                <label class="field-label">Full Name</label>
                <input type="text" disabled value="<?php echo htmlspecialchars($profile['name']); ?>" class="editor-input opacity-50 cursor-not-allowed">
            </div>

            <div>
                <label class="field-label">Professional Title</label>
                <input type="text" name="job_title" id="input_job_title" value="<?php echo htmlspecialchars($profile['job_title']??''); ?>" 
                    class="editor-input" placeholder="e.g. Lead Software Engineer">
            </div>

            <div>
                <label class="field-label">Current Company</label>
                <input type="text" name="company" id="input_company" value="<?php echo htmlspecialchars($profile['company']??''); ?>" 
                    class="editor-input" placeholder="e.g. Google PH">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="field-label">Phone</label>
                    <input type="text" name="contact_no" id="input_contact" value="<?php echo htmlspecialchars($profile['contact_no']??''); ?>" class="editor-input">
                </div>
                <div>
                    <label class="field-label">Exp (Years)</label>
                    <input type="number" name="years_experience" id="input_exp" value="<?php echo htmlspecialchars($profile['years_experience']??'0'); ?>" class="editor-input">
                </div>
            </div>

            <div>
                <label class="field-label">Location / Address</label>
                <input type="text" name="address" id="input_address" value="<?php echo htmlspecialchars($profile['address']??''); ?>" class="editor-input">
            </div>

            <div>
                <label class="field-label">Portfolio Website</label>
                <input type="text" name="website" id="input_website" value="<?php echo htmlspecialchars($profile['website']??''); ?>" class="editor-input" placeholder="e.g. github.com/user">
            </div>

            <div>
                <label class="field-label">Advanced Degree (Optional)</label>
                <input type="text" name="advanced_degree" id="input_degree" value="<?php echo htmlspecialchars($profile['advanced_degree']??''); ?>" class="editor-input" placeholder="e.g. MS in Data Science">
            </div>

            <div>
                <label class="field-label">Technical Skills (Comma separated)</label>
                <textarea name="skills" id="input_skills" 
                    class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-100 rounded-2xl p-4 text-xs font-bold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100 transition-all h-32" 
                    placeholder="e.g. PHP, Laravel, UI/UX Design, Public Speaking"><?php echo htmlspecialchars($profile['skills']??''); ?></textarea>
            </div>

            <div>
                <label class="field-label">Experience Summary (Bullet Points)</label>
                <textarea name="experience_summary" id="input_exp_summary" 
                    class="w-full bg-slate-50 dark:bg-slate-900 border border-slate-100 rounded-2xl p-4 text-xs font-bold text-slate-800 outline-none focus:ring-4 focus:ring-blue-100 transition-all h-48" 
                    placeholder="Each line will be a bullet point. e.g. Led a team of 5 developers. Created a new PHP framework."><?php echo htmlspecialchars($profile['experience_summary']??''); ?></textarea>
            </div>

            <div class="pt-6 border-t border-slate-100 flex gap-4">
                <button type="submit" class="flex-1 h-12 bg-blue-600 text-white font-black text-xs uppercase tracking-widest rounded-2xl hover:bg-blue-700 transition-all shadow-xl shadow-blue-200">
                    💾 Save Data
                </button>
            </div>
        </form>
    </aside>

    <!-- Preview Area -->
    <main class="preview-area">
        <div class="flex justify-center">
            <div class="resume-canvas text-gray-900 shadow-2xl shadow-slate-200">
                <!-- Harvard Header -->
                <div class="text-center mb-8">
                    <h1 class="harvard-name mb-4"><?php echo htmlspecialchars($profile['name']); ?></h1>
                    <div class="harvard-contact space-x-3 italic">
                        <span><?php echo htmlspecialchars($profile['address']); ?></span>
                        <span>•</span>
                        <span id="view_contact"><?php echo htmlspecialchars($profile['contact_no']); ?></span>
                        <span>•</span>
                        <a href="mailto:<?php echo $profile['email']; ?>" class="underline font-bold"><?php echo htmlspecialchars($profile['email']); ?></a>
                        <?php if ($profile['website']): ?>
                        <span>•</span>
                        <span id="view_website_wrapper"><span id="view_website" class="font-bold underline text-blue-800"><?php echo htmlspecialchars($profile['website']); ?></span></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Education -->
                <div class="section-title">Education</div>
                <div class="mb-4">
                    <div class="item-header">
                        <span>LYCEUM OF ALABANG</span>
                        <span>Muntinlupa City</span>
                    </div>
                    <div class="item-sub">
                        <span>Bachelor of Science in <?php echo htmlspecialchars($profile['program']); ?></span>
                        <span>Batch <?php echo $profile['batch_year']; ?></span>
                    </div>
                    <?php if ($profile['advanced_degree']): ?>
                    <div class="item-sub mt-2" id="view_degree_area">
                        <span><?php echo htmlspecialchars($profile['advanced_degree']); ?></span>
                        <span>Post-graduate</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Experience -->
                <div class="section-title">Professional Experience</div>
                <div class="mb-4">
                    <div class="item-header">
                        <span id="view_company"><?php echo htmlspecialchars($profile['company'] ?? 'PRIVATE CORPORATION'); ?></span>
                        <span><?php echo $profile['date_started'] ? date('M Y', strtotime($profile['date_started'])) : 'PRESENT'; ?> – PRESENT</span>
                    </div>
                    <div class="item-sub">
                        <span id="view_job_title"><?php echo htmlspecialchars($profile['job_title'] ?? 'Professional'); ?></span>
                    </div>
                    <ul class="bullet-list" id="view_bullets">
                        <?php 
                        $summary = $profile['experience_summary'] ?? "Directed core operational strategies within the division.\nSpearheaded departmental growth initiatives.\nCollaborated with cross-functional teams.";
                        $bullets = array_filter(array_map('trim', explode("\n", $summary)));
                        foreach($bullets as $b):
                        ?>
                        <li><?php echo htmlspecialchars($b); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <!-- Skills -->
                <div class="section-title">Skills & Additional Information</div>
                <div class="text-[11.5pt] leading-7 mt-2">
                    <span class="font-bold">TECHNICAL EXPERTISE:</span> 
                    <span id="view_skills_area" class="italic">
                        <?php 
                        $skills = array_filter(array_map('trim', explode(',', $profile['skills']??'')));
                        echo htmlspecialchars(implode(', ', $skills));
                        ?>
                    </span>
                </div>
                <div class="text-[11.5pt] mt-2">
                    <span class="font-bold">OFFICIAL CERTIFICATIONS:</span> 
                    <span>Lyceum of Alabang Verified Alumni (Class of <?php echo $profile['batch_year']; ?>)</span><?php if ($profile['is_mentor']): ?>, <span>Certified Senior Alumni Mentor</span><?php endif; ?>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
    // Live Review Logic
    const inputs = [
        { id: 'input_job_title', viewId: 'view_job_title' },
        { id: 'input_company', viewId: 'view_company' },
        { id: 'input_contact', viewId: 'view_contact' },
        { id: 'input_address', viewId: 'view_address' },
        { id: 'input_website', viewId: 'view_website' },
        { id: 'input_exp', viewId: 'view_exp_years' },
        { id: 'input_degree', viewId: 'view_degree' }
    ];

    inputs.forEach(pair => {
        const input = document.getElementById(pair.id);
        const view = document.getElementById(pair.viewId);
        
        if (input && view) {
            input.addEventListener('input', () => {
                view.innerText = input.value || '—';
                if (pair.id === 'input_degree') {
                    const area = document.getElementById('view_degree_area');
                    if(area) area.classList.toggle('hidden', !input.value);
                }
            });
        }
    });

    const skillsInput = document.getElementById('input_skills');
    const skillsArea = document.getElementById('view_skills_area');
    if(skillsInput && skillsArea) {
        skillsInput.addEventListener('input', () => {
            skillsArea.innerText = skillsInput.value;
        });
    }

    const expInput = document.getElementById('input_exp_summary');
    const bulletArea = document.getElementById('view_bullets');
    if(expInput && bulletArea) {
        expInput.addEventListener('input', () => {
            const vals = expInput.value.split('\n').map(s => s.trim()).filter(s => s.length > 0);
            bulletArea.innerHTML = vals.map(v => `<li>${v}</li>`).join('');
        });
    }

    // Handle Print
    window.addEventListener('beforeprint', () => {
        // Ensure preview looks good
    });
</script>

<div class="no-print h-20"></div>

</body>
</html>
