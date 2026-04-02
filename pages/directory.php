<?php
// pages/directory.php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$search = $_GET['search'] ?? '';
$program = $_GET['program'] ?? '';
$batch = $_GET['batch'] ?? '';

// Base query
$query = "SELECT u.id as user_id, u.name, a.program, a.batch_year, a.company, a.job_title 
          FROM users u 
          JOIN alumni a ON u.id = a.user_id 
          WHERE u.first_login = 0";

$params = [];

if ($search) {
    $query .= " AND (u.name LIKE ? OR a.company LIKE ? OR a.job_title LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if ($program) {
    $query .= " AND a.program = ?";
    $params[] = $program;
}

if ($batch) {
    $query .= " AND a.batch_year = ?";
    $params[] = $batch;
}

$query .= " ORDER BY a.batch_year DESC, u.name ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$alumni_list = $stmt->fetchAll();

// Fetch programs for filter
$programs = $pdo->query("SELECT DISTINCT program FROM alumni WHERE program IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Directory - LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen">

    <header class="bg-white border-b border-slate-200 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="dashboard.php" class="p-2 hover:bg-slate-50 rounded-lg transition-all text-slate-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
                </a>
                <span class="font-black text-slate-800 text-xl tracking-tight">ALUMNI DIRECTORY</span>
            </div>
            
            <div class="flex items-center gap-4">
                <?php echo renderAvatar($_SESSION['user_name'], 'w-10 h-10 border-2 border-white shadow-sm ring-1 ring-slate-100'); ?>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-10">
        <!-- Search and Filter Bar -->
        <form class="bg-white p-6 rounded-3xl shadow-sm border border-slate-100 mb-10 flex flex-wrap lg:flex-nowrap gap-4">
            <div class="flex-1 relative">
                <span class="absolute left-4 top-3.5 text-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </span>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, company, or role..." class="w-full h-12 bg-slate-50 rounded-2xl pl-12 pr-4 outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all text-sm">
            </div>

            <div class="w-full lg:w-48">
                <select name="program" class="w-full h-12 bg-slate-50 rounded-2xl px-4 outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all text-sm cursor-pointer appearance-none">
                    <option value="">All Programs</option>
                    <?php foreach ($programs as $p): ?>
                        <option value="<?php echo $p; ?>" <?php echo $program === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="w-full lg:w-32">
                <input type="number" name="batch" value="<?php echo htmlspecialchars($batch); ?>" placeholder="Batch" class="w-full h-12 bg-slate-50 rounded-2xl px-4 outline-none focus:ring-4 focus:ring-blue-100 focus:bg-white transition-all text-sm">
            </div>

            <button type="submit" class="w-full lg:w-40 h-12 bg-blue-600 text-white rounded-2xl font-bold hover:bg-blue-700 active:scale-95 transition-all shadow-lg shadow-blue-100">Apply Filters</button>
        </form>

        <!-- Grid -->
        <?php if (empty($alumni_list)): ?>
            <div class="flex flex-col items-center justify-center py-20 text-slate-400">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-20 w-20 opacity-20" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 9.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <p class="mt-4 font-medium italic">No alumni found matching your criteria.</p>
                <a href="directory.php" class="text-blue-600 font-bold hover:underline mt-4">Clear all filters</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                <?php foreach ($alumni_list as $alumni): ?>
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-slate-100 hover:shadow-xl hover:translate-y-[-4px] transition-all duration-300 flex flex-col group">
                    <div class="flex items-start justify-between mb-6">
                        <?php echo renderAvatar($alumni['name'], 'w-14 h-14 text-lg border-4 border-white shadow-md ring-1 ring-slate-100'); ?>
                        <span class="px-3 py-1 bg-blue-50 text-blue-600 text-[10px] font-black rounded-full uppercase tracking-widest"><?php echo $alumni['batch_year']; ?></span>
                    </div>
                    
                    <h3 class="font-bold text-slate-800 text-lg mb-1 group-hover:text-blue-600 transition-colors"><?php echo htmlspecialchars($alumni['name']); ?></h3>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-4"><?php echo htmlspecialchars($alumni['program']); ?></p>
                    
                    <div class="mt-auto space-y-3 pt-6 border-t border-slate-50">
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            <span class="truncate"><?php echo htmlspecialchars($alumni['job_title'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="flex items-center gap-3 text-sm text-slate-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-10V4m0 10V4m-4 5h4" /></svg>
                            <span class="truncate"><?php echo htmlspecialchars($alumni['company'] ?? 'N/A'); ?></span>
                        </div>
                    </div>

                    <a href="view-profile.php?id=<?php echo $alumni['user_id'] ?? ''; ?>" class="block w-full mt-8 py-3 rounded-2xl bg-slate-50 text-slate-400 font-bold text-xs uppercase tracking-widest group-hover:bg-slate-900 group-hover:text-white transition-all shadow-inner text-center">View Profile</a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>
