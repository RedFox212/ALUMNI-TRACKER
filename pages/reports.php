<?php
// pages/reports.php  — MEDIUM-HARD (analytics + CSV export)
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: dashboard.php'); exit; }

$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

// Global Filters
$f_program = $_GET['program'] ?? '';
$f_batch   = $_GET['batch']   ?? '';

$where_clauses = ["1=1"];
$params = [];

if ($f_program) {
    $where_clauses[] = "program = ?";
    $params[] = $f_program;
}
if ($f_batch) {
    $where_clauses[] = "batch_year = ?";
    $params[] = $f_batch;
}

$where_sq = " WHERE " . implode(" AND ", $where_clauses);
$where_sq_u = " WHERE " . implode(" AND ", str_replace("program", "a.program", str_replace("batch_year", "a.batch_year", $where_clauses)));


// Handle CSV Export
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lats_' . $type . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');

    if ($type === 'alumni') {
        fputcsv($out, ['Name', 'Email', 'Program', 'Batch Year', 'Company', 'Job Title', 'Employment Status', 'Years Experience', 'Address', 'Contact']);
        $rows = $pdo->query("SELECT u.name, u.email, a.program, a.batch_year, a.company, a.job_title, a.employment_status, a.years_experience, a.address, a.contact_no FROM users u JOIN alumni a ON u.id = a.user_id ORDER BY a.batch_year DESC, u.name ASC")->fetchAll();
        foreach ($rows as $r) { fputcsv($out, $r); }
    } elseif ($type === 'employment') {
        fputcsv($out, ['Employment Status', 'Count']);
        $rows = $pdo->query("SELECT employment_status, COUNT(*) as count FROM alumni GROUP BY employment_status ORDER BY count DESC")->fetchAll();
        foreach ($rows as $r) { fputcsv($out, [$r['employment_status'], $r['count']]); }
    } elseif ($type === 'program') {
        fputcsv($out, ['Program', 'Count', 'Employed', 'In-Discipline']);
        $rows = $pdo->query("SELECT program, COUNT(*) as total, SUM(CASE WHEN employment_status='Employed' THEN 1 ELSE 0 END) as employed, SUM(CASE WHEN discipline_match=1 THEN 1 ELSE 0 END) as indiscipline FROM alumni GROUP BY program ORDER BY total DESC")->fetchAll();
        foreach ($rows as $r) { fputcsv($out, [$r['program'], $r['total'], $r['employed'], $r['indiscipline']]); }
    } elseif ($type === 'master') {
        fputcsv($out, [
            'System ID', 'Alumni ID Num', 'Full Name', 'Gender', 'Email', 
            'Program', 'Batch Year', 'Degree', 'College', 
            'Employment Status', 'Company', 'Job Title', 'Job Alignment', 'Years Experience', 
            'Skills', 'Advanced Degrees', 'Contact No', 'Verification Status'
        ]);
        $rows = $pdo->query("
            SELECT 
                a.id, a.alumni_id_num, CONCAT(a.first_name, ' ', a.middle_name, ' ', a.last_name) as full_name, 
                a.gender, u.email, a.program, a.batch_year, a.degree, a.college, 
                a.employment_status, a.company, a.job_title, 
                (CASE WHEN a.discipline_match=1 THEN 'Aligned' ELSE 'Not Aligned' END) as alignment,
                a.years_experience, a.skills, 
                CONCAT_WS(', ', NULLIF(a.masteral, ''), NULLIF(a.doctorate, '')) as advanced,
                a.contact_no, a.verification_status
            FROM users u 
            JOIN alumni a ON u.id = a.user_id 
            ORDER BY a.batch_year DESC, full_name ASC
        ")->fetchAll();
        foreach ($rows as $r) { fputcsv($out, $r); }
    }
    fclose($out); exit;
}

// ── Stats ──────────────────────────────────────────
$total_alumni   = $pdo->prepare("SELECT COUNT(*) FROM alumni" . $where_sq);
$total_alumni->execute($params);
$total_alumni = $total_alumni->fetchColumn();

// Employment & Discipline
$employed_q = "SELECT COUNT(*) FROM alumni" . $where_sq . ($f_program || $f_batch ? " AND " : " WHERE ") . "employment_status='Employed'";
// Wait, the logic above is a bit messy with the WHERE. Let's simplify.
$emp_stmt = $pdo->prepare("SELECT COUNT(*) FROM alumni " . $where_sq . " AND employment_status='Employed'");
$emp_stmt->execute($params);
$employed = $emp_stmt->fetchColumn();

$disc_stmt = $pdo->prepare("SELECT COUNT(*) FROM alumni " . $where_sq . " AND discipline_match=1");
$disc_stmt->execute($params);
$in_discipline = $disc_stmt->fetchColumn();

$emp_rate       = $total_alumni > 0 ? round(($employed / $total_alumni) * 100) : 0;
$disc_rate      = $total_alumni > 0 ? round(($in_discipline / $total_alumni) * 100) : 0;

$total_ann      = $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$total_imports  = $pdo->query("SELECT COALESCE(SUM(records_added),0) FROM import_logs")->fetchColumn();

// ── By Program ─────────────────────────────────────
$bp_stmt = $pdo->prepare(
    "SELECT program,
        COUNT(*) as total,
        SUM(CASE WHEN employment_status='Employed' THEN 1 ELSE 0 END) as employed,
        SUM(CASE WHEN discipline_match=1 THEN 1 ELSE 0 END) as indiscipline
     FROM alumni
     " . $where_sq . "
     GROUP BY program ORDER BY total DESC"
);
$bp_stmt->execute($params);
$by_program = $bp_stmt->fetchAll();

// ── By Batch ───────────────────────────────────────
$bb_stmt = $pdo->prepare(
    "SELECT batch_year,
        COUNT(*) as total,
        SUM(CASE WHEN employment_status='Employed' THEN 1 ELSE 0 END) as employed
     FROM alumni
     " . $where_sq . "
     GROUP BY batch_year ORDER BY batch_year DESC LIMIT 20"
);
$bb_stmt->execute($params);
$by_batch = $bb_stmt->fetchAll();

// ── Employment Breakdown ────────────────────────────
// Fetch aligned vs other employment details
$aligned_stmt = $pdo->prepare("SELECT COUNT(*) FROM alumni " . $where_sq . " AND employment_status='Employed' AND discipline_match=1");
$aligned_stmt->execute($params);
$aligned_count = $aligned_stmt->fetchColumn();

$misaligned_stmt = $pdo->prepare("SELECT COUNT(*) FROM alumni " . $where_sq . " AND employment_status='Employed' AND discipline_match=0");
$misaligned_stmt->execute($params);
$misaligned_count = $misaligned_stmt->fetchColumn();

// Fetch other statuses
$eb_stmt = $pdo->prepare(
    "SELECT employment_status, COUNT(*) as count FROM alumni " . $where_sq . " AND employment_status != 'Employed' GROUP BY employment_status ORDER BY count DESC"
);
$eb_stmt->execute($params);
$other_statuses = $eb_stmt->fetchAll();

$breakdown = [];
if ($aligned_count > 0)    $breakdown[] = ['label' => 'Working in Field', 'count' => $aligned_count, 'color' => 'bg-green-600'];
if ($misaligned_count > 0) $breakdown[] = ['label' => 'In Other Careers',  'count' => $misaligned_count, 'color' => 'bg-blue-400'];

foreach ($other_statuses as $os) {
    $c = 'bg-slate-400';
    if ($os['employment_status'] === 'Self-employed') $c = 'bg-indigo-500';
    if ($os['employment_status'] === 'Unemployed')    $c = 'bg-rose-400';
    if ($os['employment_status'] === 'Studying')      $c = 'bg-amber-400';
    $breakdown[] = ['label' => $os['employment_status'], 'count' => $os['count'], 'color' => $c];
}

$emp_total = array_sum(array_column($breakdown, 'count')) ?: 1;

// ── Batch Analytics Summary ────────────────────────
$peak_batch = !empty($by_batch) ? $by_batch[array_search(max(array_column($by_batch, 'total')), array_column($by_batch, 'total'))] : null;
$best_job_batch = null;
if (!empty($by_batch)) {
    $highest_rate = -1;
    foreach($by_batch as $b) {
        $rate = $b['total'] > 0 ? ($b['employed'] / $b['total']) : 0;
        if ($rate > $highest_rate) {
            $highest_rate = $rate;
            $best_job_batch = $b;
        }
    }
}

// ── Import Logs ─────────────────────────────────────
$import_logs = $pdo->query(
    "SELECT il.*, u.name as admin_name FROM import_logs il JOIN users u ON il.imported_by = u.id ORDER BY il.imported_at DESC LIMIT 10"
)->fetchAll();

// Fetch filter options
$programs_list = $pdo->query("SELECT DISTINCT program FROM alumni WHERE program IS NOT NULL ORDER BY program")->fetchAll(PDO::FETCH_COLUMN);
$batches_list  = $pdo->query("SELECT DISTINCT batch_year FROM alumni WHERE batch_year IS NOT NULL ORDER BY batch_year DESC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #cbd5e1; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex">

<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-8 flex items-center justify-between sticky top-0 z-30 transition-all">
        <div class="flex items-center gap-3 text-slate-400">
            <span class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tighter">Reports</span>
            <span class="w-1 h-1 bg-slate-200 dark:bg-slate-700 rounded-full"></span>
            <span class="text-xs italic">Analytics Overview</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="?export=master"     class="h-9 px-4 bg-indigo-600 text-white font-bold text-[10px] rounded-xl hover:bg-indigo-700 flex items-center gap-1.5 transition-all shadow-lg shadow-indigo-100 dark:shadow-none uppercase tracking-widest">📊 Master Executive CSV</a>
            <a href="?export=alumni"     class="h-9 px-4 bg-blue-50 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 font-bold text-[10px] rounded-xl hover:bg-blue-100 dark:hover:bg-blue-900/60 transition-all uppercase tracking-widest">👥 Alumni Export</a>
        </div>
    </header>

    <div class="p-8">
        <div class="mb-12">
            <h1 class="text-3xl font-black text-slate-900 dark:text-white italic uppercase tracking-tighter">REPORTS & ANALYTICS</h1>
            <p class="text-slate-400 dark:text-slate-500 text-sm font-medium">Institutional performance metrics and alumni engagement depth.</p>
        </div>

        <!-- Executive Filter Bar -->
        <form class="bg-white p-4 rounded-[28px] shadow-sm border border-slate-100 flex flex-wrap items-center gap-4 mb-10">
            <div class="flex-1 min-w-[200px] relative">
                <select name="program" onchange="this.form.submit()" class="w-full h-12 pl-6 pr-10 bg-slate-50 border-none rounded-2xl text-xs font-bold text-slate-600 appearance-none cursor-pointer focus:ring-2 focus:ring-blue-500/20 transition-all uppercase tracking-widest">
                    <option value="">All Academic Programs</option>
                    <?php foreach($programs_list as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo $f_program === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>

            <div class="w-48 relative">
                <select name="batch" onchange="this.form.submit()" class="w-full h-12 pl-6 pr-10 bg-slate-50 border-none rounded-2xl text-xs font-bold text-slate-600 appearance-none cursor-pointer focus:ring-2 focus:ring-blue-500/20 transition-all uppercase tracking-widest">
                    <option value="">All Batches</option>
                    <?php foreach($batches_list as $b): ?>
                        <option value="<?php echo $b; ?>" <?php echo $f_batch == $b ? 'selected' : ''; ?>>Year <?php echo $b; ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                </div>
            </div>

            <?php if ($f_program || $f_batch): ?>
                <a href="reports.php" class="h-12 px-6 flex items-center justify-center text-rose-500 font-bold text-[10px] uppercase tracking-widest hover:bg-rose-50 rounded-2xl transition-all">Reset</a>
            <?php endif; ?>
        </form>



        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- By Program -->
            <div class="lg:col-span-2 bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-all">
                <div class="p-6 border-b border-slate-50 dark:border-slate-800 flex justify-between items-center bg-slate-50/30 dark:bg-slate-800/30">
                    <h2 class="font-black text-slate-900 dark:text-white uppercase tracking-tighter">Alumni by Program</h2>
                    <a href="?export=program" class="text-xs text-blue-600 dark:text-blue-400 font-black hover:underline uppercase tracking-widest">Export All →</a>
                </div>
                <div class="max-h-[400px] overflow-y-auto custom-scrollbar">
                    <table class="w-full border-collapse">
                        <thead><tr class="bg-slate-50/70 dark:bg-slate-800/70 sticky top-0 z-10 transition-all">
                            <th class="text-left py-3 px-6 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Program</th>
                            <th class="text-center py-3 px-4 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Registered</th>
                            <th class="text-center py-3 px-4 text-[10px] font-black text-slate-400 dark:text-slate-500 uppercase tracking-widest">Aligned</th>
                        </tr></thead>
                        <tbody class="divide-y divide-slate-50 dark:divide-slate-800/50">
                            <?php foreach ($by_program as $row): ?>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all">
                                <td class="py-4 px-6 font-black text-slate-800 dark:text-slate-200 text-sm"><?php echo htmlspecialchars($row['program']); ?></td>
                                <td class="py-4 px-4 text-center">
                                    <span class="text-lg font-black text-slate-900 dark:text-white"><?php echo $row['total']; ?></span>
                                </td>
                                <td class="py-4 px-4 text-center text-sm font-black text-purple-600 dark:text-purple-400"><?php echo $row['indiscipline']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Employment Breakdown -->
            <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden transition-all">
                <div class="p-6 border-b border-slate-50 dark:border-slate-800 bg-slate-50/30 dark:bg-slate-800/30">
                    <h2 class="font-black text-slate-900 dark:text-white uppercase tracking-tighter">Employment Status</h2>
                </div>
                <div class="p-6 space-y-5">
                    <?php
                    foreach ($breakdown as $e):
                        $pct = round(($e['count'] / $emp_total) * 100);
                    ?>
                    <div>
                        <div class="flex justify-between text-[11px] mb-2">
                            <span class="font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest"><?php echo htmlspecialchars($e['label']); ?></span>
                            <span class="text-slate-900 dark:text-white font-black tracking-tight"><?php echo $e['count']; ?> <span class="text-slate-400 dark:text-slate-600 font-bold ml-1">(<?php echo $pct; ?>%)</span></span>
                        </div>
                        <div class="h-2.5 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-1000 <?php echo $e['color']; ?>" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Batch Breakdown -->
        <div class="bg-white dark:bg-slate-900 rounded-3xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-hidden mb-6 transition-all">
            <div class="p-6 border-b border-slate-50 dark:border-slate-800 flex justify-between items-center bg-slate-50/30 dark:bg-slate-800/30">
                <h2 class="font-black text-slate-900 dark:text-white uppercase tracking-tighter">Alumni by Batch Year</h2>
                <span class="text-xs text-slate-400 uppercase font-black tracking-widest">Chronological View</span>
            </div>
            <div class="p-6">
                <!-- Executive Summary -->
                <?php if ($peak_batch): ?>
                <div class="flex gap-3 mb-8 overflow-x-auto pb-2 custom-scrollbar">
                    <div class="flex-shrink-0 bg-blue-50 dark:bg-blue-900/20 rounded-2xl p-4 border border-blue-100 dark:border-blue-800/50 min-w-[180px]">
                        <p class="text-[9px] font-black text-blue-600 dark:text-blue-400 uppercase tracking-widest mb-1">Peak Batch Size</p>
                        <div class="flex items-baseline gap-2">
                            <span class="text-xl font-black text-slate-900 dark:text-white"><?php echo $peak_batch['batch_year']; ?></span>
                            <span class="text-xs font-black text-blue-600 dark:text-blue-400"><?php echo $peak_batch['total']; ?> Alumni</span>
                        </div>
                    </div>
                    <?php if ($best_job_batch): ?>
                    <div class="flex-shrink-0 bg-emerald-50 dark:bg-emerald-900/20 rounded-2xl p-4 border border-emerald-100 dark:border-emerald-800/50 min-w-[200px]">
                        <p class="text-[9px] font-black text-emerald-600 dark:text-emerald-500 uppercase tracking-widest mb-1">Peak Placement Rate</p>
                        <div class="flex items-baseline gap-2">
                            <span class="text-xl font-black text-emerald-900 dark:text-emerald-400">Class of <?php echo $best_job_batch['batch_year']; ?></span>
                            <span class="text-xs font-black text-emerald-600 dark:text-emerald-300"><?php echo round(($best_job_batch['employed']/$best_job_batch['total'])*100); ?>% Placed</span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="max-h-[500px] overflow-y-auto pr-4 custom-scrollbar space-y-6">
                    <?php if (empty($by_batch)): ?>
                        <p class="text-center py-10 text-slate-400 text-sm italic">No records found for current filters.</p>
                    <?php else: ?>
                        <?php foreach ($by_batch as $b):
                            $bar_w = round(($b['total'] / $max_batch) * 100);
                            $emp_w = $b['total'] > 0 ? round(($b['employed']/$b['total'])*100) : 0;
                            $other_w = 100 - $emp_w;
                        ?>
                        <div class="group">
                            <div class="flex justify-between items-end mb-2 px-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-lg font-black text-slate-900 dark:text-white tracking-tighter transition-all group-hover:text-blue-600"><?php echo $b['batch_year']; ?></span>
                                    <span class="px-2 py-0.5 bg-slate-100 dark:bg-slate-800 rounded-lg text-[9px] font-black text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-slate-700 uppercase">Population: <?php echo $b['total']; ?></span>
                                </div>
                                <div class="flex gap-4">
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full bg-blue-500 shadow-sm shadow-blue-500/50"></div>
                                        <span class="text-[10px] font-black text-slate-900 dark:text-slate-300 tracking-tight transition-all"><?php echo $emp_w; ?>% Working</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <div class="w-2 h-2 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                                        <span class="text-[10px] font-black text-slate-400 dark:text-slate-500 tracking-tight transition-all"><?php echo $other_w; ?>% Other</span>
                                    </div>
                                </div>
                            </div>
                            <div class="h-4 bg-slate-50 dark:bg-slate-800/50 rounded-full overflow-hidden flex relative shadow-inner border border-slate-100/50 dark:border-slate-800/50" style="width:<?php echo max(35, $bar_w); ?>%">
                                <div class="h-full bg-gradient-to-r from-blue-600 to-blue-400 rounded-r-lg transition-all duration-1000 delay-100 shadow-lg shadow-blue-500/20" style="width:<?php echo $emp_w; ?>%"></div>
                                <div class="h-full bg-slate-200 dark:bg-slate-700 transition-all duration-1000 delay-300" style="width:<?php echo $other_w; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Additional Stats Row -->
        <div class="flex justify-center">
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 text-center w-full max-w-sm">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Records Imported</p>
                <p class="text-4xl font-black text-green-600"><?php echo number_format($total_imports); ?></p>
            </div>
        </div>
    </div>
</main>

</body>
</html>
