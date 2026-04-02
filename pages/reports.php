<?php
// pages/reports.php  — MEDIUM-HARD (analytics + CSV export)
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: dashboard.php'); exit; }

$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];

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
    }
    fclose($out); exit;
}

// ── Stats ──────────────────────────────────────────
$total_alumni   = $pdo->query("SELECT COUNT(*) FROM alumni")->fetchColumn();
$total_users    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='alumni'")->fetchColumn();
$employed       = $pdo->query("SELECT COUNT(*) FROM alumni WHERE employment_status='Employed'")->fetchColumn();
$in_discipline  = $pdo->query("SELECT COUNT(*) FROM alumni WHERE discipline_match=1")->fetchColumn();
$emp_rate       = $total_alumni > 0 ? round(($employed / $total_alumni) * 100) : 0;
$disc_rate      = $total_alumni > 0 ? round(($in_discipline / $total_alumni) * 100) : 0;
$total_polls    = $pdo->query("SELECT COUNT(*) FROM polls")->fetchColumn();
$total_votes    = $pdo->query("SELECT COUNT(*) FROM poll_votes")->fetchColumn();
$total_ann      = $pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$total_imports  = $pdo->query("SELECT COALESCE(SUM(records_added),0) FROM import_logs")->fetchColumn();

// ── By Program ─────────────────────────────────────
$by_program = $pdo->query(
    "SELECT program,
        COUNT(*) as total,
        SUM(CASE WHEN employment_status='Employed' THEN 1 ELSE 0 END) as employed,
        SUM(CASE WHEN discipline_match=1 THEN 1 ELSE 0 END) as indiscipline,
        ROUND(AVG(years_experience),1) as avg_exp
     FROM alumni
     GROUP BY program ORDER BY total DESC"
)->fetchAll();

// ── By Batch ───────────────────────────────────────
$by_batch = $pdo->query(
    "SELECT batch_year,
        COUNT(*) as total,
        SUM(CASE WHEN employment_status='Employed' THEN 1 ELSE 0 END) as employed
     FROM alumni
     GROUP BY batch_year ORDER BY batch_year DESC LIMIT 10"
)->fetchAll();

// ── Employment Breakdown ────────────────────────────
$emp_breakdown = $pdo->query(
    "SELECT employment_status, COUNT(*) as count FROM alumni GROUP BY employment_status ORDER BY count DESC"
)->fetchAll();
$emp_total = array_sum(array_column($emp_breakdown, 'count')) ?: 1;

// ── Import Logs ─────────────────────────────────────
$import_logs = $pdo->query(
    "SELECT il.*, u.name as admin_name FROM import_logs il JOIN users u ON il.imported_by = u.id ORDER BY il.imported_at DESC LIMIT 10"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex">

<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-64">
    <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3 text-slate-400">
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Reports</span>
            <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
            <span class="text-xs">Analytics Overview</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="?export=alumni"     class="h-9 px-4 bg-blue-50 text-blue-600 font-bold text-xs rounded-xl hover:bg-blue-100 flex items-center gap-1.5 transition-all">📥 Alumni CSV</a>
            <a href="?export=program"    class="h-9 px-4 bg-purple-50 text-purple-600 font-bold text-xs rounded-xl hover:bg-purple-100 flex items-center gap-1.5 transition-all">📥 Program CSV</a>
            <a href="?export=employment" class="h-9 px-4 bg-green-50 text-green-600 font-bold text-xs rounded-xl hover:bg-green-100 flex items-center gap-1.5 transition-all">📥 Employment CSV</a>
        </div>
    </header>

    <div class="p-8">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 italic">REPORTS & ANALYTICS</h1>
            <p class="text-slate-400 text-sm font-medium">Live data from the Lyceum of Alabang Alumni Database. Export to CSV at any time.</p>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            <?php
            $kpis = [
                ['Total Alumni', $total_alumni, 'bg-blue-50 text-blue-600', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                ['Employment Rate', $emp_rate.'%', 'bg-green-50 text-green-600', 'M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z'],
                ['In-Discipline', $disc_rate.'%', 'bg-purple-50 text-purple-600', 'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
                ['Total Polls', $total_polls, 'bg-amber-50 text-amber-600', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
            ];
            foreach ($kpis as [$label, $val, $color, $path]):
            ?>
            <div class="bg-white rounded-3xl p-5 shadow-sm border border-slate-100 flex items-center gap-4">
                <div class="w-12 h-12 rounded-2xl flex items-center justify-center shadow-inner <?php echo $color; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo $path; ?>"/></svg>
                </div>
                <div>
                    <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest"><?php echo $label; ?></p>
                    <p class="text-2xl font-black text-slate-800"><?php echo $val; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- By Program -->
            <div class="lg:col-span-2 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-50 flex justify-between items-center">
                    <h2 class="font-black text-slate-800 uppercase tracking-tighter">Alumni by Program</h2>
                    <a href="?export=program" class="text-xs text-blue-600 font-bold hover:underline">Export →</a>
                </div>
                <table class="w-full">
                    <thead><tr class="bg-slate-50/70">
                        <th class="text-left py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Program</th>
                        <th class="text-center py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Total</th>
                        <th class="text-center py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">Employed</th>
                        <th class="text-center py-3 px-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">In-Disc.</th>
                        <th class="text-center py-3 px-6 text-[10px] font-black text-slate-400 uppercase tracking-widest">Avg Exp</th>
                    </tr></thead>
                    <tbody class="divide-y divide-slate-50">
                        <?php foreach ($by_program as $row):
                            $emp_pct = $row['total'] > 0 ? round(($row['employed']/$row['total'])*100) : 0;
                        ?>
                        <tr class="hover:bg-slate-50 transition-all">
                            <td class="py-4 px-6 font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($row['program']); ?></td>
                            <td class="py-4 px-4 text-center">
                                <span class="text-lg font-black text-slate-800"><?php echo $row['total']; ?></span>
                            </td>
                            <td class="py-4 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <div class="w-16 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-green-500 rounded-full" style="width:<?php echo $emp_pct;?>%"></div>
                                    </div>
                                    <span class="text-xs text-slate-500 font-bold"><?php echo $emp_pct; ?>%</span>
                                </div>
                            </td>
                            <td class="py-4 px-4 text-center text-sm font-bold text-purple-600"><?php echo $row['indiscipline']; ?></td>
                            <td class="py-4 px-6 text-center text-sm text-slate-500"><?php echo $row['avg_exp']; ?> yrs</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Employment Breakdown -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                <div class="p-6 border-b border-slate-50">
                    <h2 class="font-black text-slate-800 uppercase tracking-tighter">Employment Status</h2>
                </div>
                <div class="p-6 space-y-4">
                    <?php
                    $emp_colors = ['Employed'=>'bg-green-500','Self-employed'=>'bg-blue-500','Unemployed'=>'bg-red-400','Student'=>'bg-amber-400'];
                    foreach ($emp_breakdown as $e):
                        $pct = round(($e['count']/$emp_total)*100);
                        $color = $emp_colors[$e['employment_status']] ?? 'bg-slate-400';
                    ?>
                    <div>
                        <div class="flex justify-between text-sm mb-1.5">
                            <span class="font-bold text-slate-700"><?php echo htmlspecialchars($e['employment_status']); ?></span>
                            <span class="text-slate-400 font-bold"><?php echo $e['count']; ?> (<?php echo $pct; ?>%)</span>
                        </div>
                        <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700 <?php echo $color; ?>" style="width:<?php echo $pct; ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Batch Breakdown -->
        <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden mb-6">
            <div class="p-6 border-b border-slate-50 flex justify-between items-center">
                <h2 class="font-black text-slate-800 uppercase tracking-tighter">Alumni by Batch Year (Top 10)</h2>
                <span class="text-xs text-slate-400">Most recent first</span>
            </div>
            <div class="p-6">
                <?php
                $max_batch = max(array_column($by_batch, 'total')) ?: 1;
                ?>
                <div class="space-y-3">
                    <?php foreach ($by_batch as $b):
                        $bar_w = round(($b['total'] / $max_batch) * 100);
                        $emp_pct = $b['total'] > 0 ? round(($b['employed']/$b['total'])*100) : 0;
                    ?>
                    <div class="flex items-center gap-4">
                        <span class="text-xs font-black text-slate-500 w-12 flex-shrink-0"><?php echo $b['batch_year']; ?></span>
                        <div class="flex-1 h-8 bg-slate-50 rounded-xl overflow-hidden relative">
                            <div class="h-full bg-gradient-to-r from-blue-500 to-indigo-400 rounded-xl transition-all duration-700 flex items-center px-3" style="width:<?php echo $bar_w; ?>%">
                                <?php if ($bar_w > 20): ?><span class="text-white text-xs font-bold"><?php echo $b['total']; ?></span><?php endif; ?>
                            </div>
                            <?php if ($bar_w <= 20): ?><span class="absolute left-2 top-1/2 -translate-y-1/2 text-slate-500 text-xs font-bold"><?php echo $b['total']; ?></span><?php endif; ?>
                        </div>
                        <span class="text-xs font-bold text-green-600 w-16 text-right flex-shrink-0"><?php echo $emp_pct; ?>% emp.</span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Additional Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 text-center">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Total Poll Responses</p>
                <p class="text-4xl font-black text-blue-600"><?php echo number_format($total_votes); ?></p>
            </div>
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 text-center">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Announcements Sent</p>
                <p class="text-4xl font-black text-purple-600"><?php echo number_format($total_ann); ?></p>
            </div>
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 text-center">
                <p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2">Records Imported</p>
                <p class="text-4xl font-black text-green-600"><?php echo number_format($total_imports); ?></p>
            </div>
        </div>
    </div>
</main>

</body>
</html>
