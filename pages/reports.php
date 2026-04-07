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
     GROUP BY batch_year ORDER BY batch_year DESC"
);
$bb_stmt->execute($params);
$batch_results = $bb_stmt->fetchAll();

// Fill in gaps from 2004 to current (2026)
$current_year = (int)date('Y');
$target_max_year = max(2026, $current_year);
$full_range = range(2004, $target_max_year);
$by_batch = [];
$batch_map = [];
foreach ($batch_results as $r) { $batch_map[$r['batch_year']] = $r; }

foreach ($full_range as $yr) {
    if (isset($batch_map[$yr])) {
        $by_batch[] = $batch_map[$yr];
    } else {
        $by_batch[] = ['batch_year' => $yr, 'total' => 0, 'employed' => 0];
    }
}
// Reverse to show newest first in the logical flow or keep chronological? 
// User wants to see every batch, usually newest first is better for dashboards.
usort($by_batch, function($a, $b) { return $b['batch_year'] <=> $a['batch_year']; });

$max_batch = 1;
if (!empty($by_batch)) {
    $max_batch = max(array_column($by_batch, 'total')) ?: 1;
}

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
$batches_list  = range(2004, (int)date('Y'));
rsort($batches_list);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <!-- Flash Guard -->
    <script>(function(){const t=localStorage.getItem('lats-theme');if(t==='dark'||(!t&&window.matchMedia('(prefers-color-scheme: dark)').matches))document.documentElement.classList.add('dark');})();</script>
    <style>
        :root { background-color: #f1f5f9; }
        .dark { background-color: #0c111d; }
        body { font-family: 'Inter', sans-serif; }
        .custom-scrollbar::-webkit-scrollbar { width: 6px; height: 6px; }
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
        <div></div>
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
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-widest leading-none">Total registered </span>
                        <span class="px-3 py-1 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 text-xs font-black rounded-full border border-indigo-100 dark:border-indigo-800/50">
                            <?php echo number_format($total_alumni); ?>
                        </span>
                    </div>
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

                <div class="max-h-[400px] overflow-y-auto pr-4 custom-scrollbar">
                    <div class="space-y-1">
                    <?php if (empty($by_batch)): ?>
                        <div class="text-center py-6 text-slate-400 text-[10px] italic">No records found.</div>
                    <?php else: ?>
                        <?php foreach ($by_batch as $b):
                            $emp_w = $b['total'] > 0 ? round(($b['employed']/$b['total'])*100) : 0;
                        ?>
                        <div class="group flex items-center gap-4 py-1.5 px-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-all border border-transparent hover:border-slate-100 dark:hover:border-slate-800">
                            <!-- Year & Count -->
                            <div class="w-16 flex-shrink-0">
                                <h3 class="text-sm font-black text-slate-900 dark:text-white tracking-tighter transition-all group-hover:text-indigo-600"><?php echo $b['batch_year']; ?></h3>
                                <p class="text-[7px] font-black text-slate-400 uppercase tracking-widest -mt-0.5"><?php echo $b['total']; ?> Alumni</p>
                            </div>

                            <!-- Progress Bar Alignment -->
                            <div class="flex-1 flex items-center gap-3">
                                <div class="flex-1 h-1 bg-slate-100 dark:bg-slate-800/60 rounded-full overflow-hidden">
                                    <div class="h-full bg-indigo-500 rounded-full transition-all duration-1000 group-hover:bg-indigo-400" style="width:<?php echo $emp_w; ?>%"></div>
                                </div>
                                <div class="w-10 text-right">
                                    <span class="text-[10px] font-black text-slate-800 dark:text-slate-300"><?php echo $emp_w; ?>%</span>
                                </div>
                            </div>
                            
                            <!-- Small Status Dot -->
                            <div class="w-1.5 h-1.5 rounded-full <?php echo $emp_w > 70 ? 'bg-emerald-500' : ($emp_w > 30 ? 'bg-amber-400' : 'bg-rose-400'); ?> shadow-sm"></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Database Counter -->
        <div class="flex justify-center mt-12 mb-8">
            <div class="bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-950/20 dark:to-teal-950/20 rounded-[32px] p-8 shadow-sm border border-emerald-100 dark:border-emerald-800/50 text-center w-full max-w-sm relative overflow-hidden transition-all hover:shadow-xl hover:-translate-y-1 group">
                <!-- Decorative Elements -->
                <div class="absolute -top-6 -right-6 w-24 h-24 bg-emerald-500 rounded-full opacity-[0.03] dark:opacity-[0.1] transform group-hover:scale-125 transition-transform duration-700"></div>
                <div class="absolute -bottom-8 -left-8 w-32 h-32 bg-teal-500 rounded-full opacity-[0.02] dark:opacity-[0.05] transform group-hover:scale-110 transition-transform duration-1000"></div>
                
                <div class="relative z-10">
                    <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-[2px] mb-3">Total Alumni Database</p>
                    <div class="flex items-center justify-center gap-4 mb-2">
                        <span class="text-5xl font-black text-emerald-700 dark:text-emerald-300 tracking-tighter"><?php echo number_format($total_alumni); ?></span>
                        <div class="h-8 w-[1px] bg-emerald-200 dark:bg-emerald-800"></div>
                        <div class="text-left">
                            <p class="text-[9px] font-black text-emerald-600 dark:text-emerald-500 uppercase leading-none">Database</p>
                            <p class="text-[9px] font-bold text-emerald-400 dark:text-emerald-600 uppercase">Synced</p>
                        </div>
                    </div>
                    <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/60 dark:bg-emerald-900/30 rounded-full border border-emerald-100 dark:border-emerald-800/40">
                        <span class="relative flex h-1.5 w-1.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
                        </span>
                        <span class="text-[8px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-widest">System Active</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

</body>
</html>
