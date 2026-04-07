<?php
// pages/directory.php — THE EXECUTIVE DARK DIRECTORY
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$search = $_GET['search'] ?? '';
$program = $_GET['program'] ?? '';
$batch = $_GET['batch'] ?? '';

// Base query
$query = "SELECT u.id as user_id, u.name, a.program, a.batch_year, a.company, a.job_title, a.profile_pic
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
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elite Directory – Lyceum of Alabang</title>
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
        
        .glass-card {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(8px);
            border: 1px solid rgba(255, 255, 255, 0.03);
            transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .glass-card:hover {
            background: rgba(30, 41, 59, 0.7);
            border-color: rgba(59, 130, 246, 0.3);
            transform: translateY(-8px) scale(1.02);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }

        .crimson-gradient {
            background: linear-gradient(135deg, #7f1d1d 0%, #450a0a 100%);
        }
        
        .bg-mesh {
            background-color: #0f172a;
            background-image: 
                radial-gradient(at 0% 0%, rgba(30, 58, 138, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(127, 29, 29, 0.1) 0px, transparent 50%);
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #0f172a; }
        ::-webkit-scrollbar-thumb { background: #1e293b; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #334155; }
    </style>
</head>
<body class="bg-mesh min-h-screen text-slate-300 antialiased">

    <!-- Premium Navigation -->
    <header class="glass-dark border-b border-white/5 sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
            <div class="flex items-center gap-6">
                <a href="dashboard.php" class="w-10 h-10 flex items-center justify-center rounded-2xl bg-white/5 hover:bg-white/10 transition-all text-slate-400 group">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 group-hover:-translate-x-1 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" /></svg>
                </a>
                <div>
                    <h1 class="font-outfit font-black text-white text-2xl tracking-tighter uppercase italic">Community Directory</h1>
                    <p class="text-[9px] font-black text-blue-500 uppercase tracking-[3px] opacity-70">The Elite Connection</p>
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                <div class="hidden md:block text-right">
                    <p class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Signed in as</p>
                    <p class="text-sm font-bold text-white"><?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
                </div>
                <?php echo renderAvatar($_SESSION['user_name'], 'w-12 h-12 border-2 border-white/10 shadow-2xl ring-4 ring-blue-500/10'); ?>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 py-12">
        
        <!-- Executive Search & Filter (Glassmorphism Tier) -->
        <form class="glass-dark p-8 rounded-[40px] border border-white/5 shadow-2xl mb-16 space-y-6 lg:space-y-0 lg:flex lg:items-center lg:gap-6">
            <div class="flex-1 relative">
                <span class="absolute left-6 top-1/2 -translate-y-1/2 text-blue-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                </span>
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                    placeholder="Search by name, company, or role..." 
                    class="w-full h-14 bg-white/5 rounded-3xl pl-16 pr-6 outline-none focus:ring-4 focus:ring-blue-500/20 focus:bg-white/10 transition-all text-sm font-medium text-white placeholder-slate-500 border border-white/5">
            </div>

            <div class="flex flex-wrap md:flex-nowrap gap-4 items-center">
                <div class="relative min-w-[200px]">
                    <select name="program" class="w-full h-14 bg-white/5 rounded-3xl px-8 outline-none focus:ring-4 focus:ring-blue-500/20 focus:bg-white/10 transition-all text-sm font-bold text-white border border-white/5 cursor-pointer appearance-none uppercase tracking-widest">
                        <option value="" class="bg-slate-900">All Programs</option>
                        <?php foreach ($programs as $p): ?>
                            <option value="<?php echo $p; ?>" class="bg-slate-900" <?php echo $program === $p ? 'selected' : ''; ?>><?php echo $p; ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="absolute right-6 top-1/2 -translate-y-1/2 pointer-events-none text-slate-500">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                    </div>
                </div>

                <div class="w-32">
                    <input type="number" name="batch" value="<?php echo htmlspecialchars($batch); ?>" 
                        placeholder="Batch" 
                        class="w-full h-14 bg-white/5 rounded-3xl px-8 outline-none focus:ring-4 focus:ring-blue-500/20 focus:bg-white/10 transition-all text-sm font-black text-white border border-white/5">
                </div>

                <button type="submit" class="h-14 px-10 bg-blue-600 hover:bg-blue-500 text-white rounded-3xl font-black text-xs uppercase tracking-[2px] transition-all active:scale-95 shadow-xl shadow-blue-900/20">Refine Search</button>
            </div>
        </form>

        <!-- Dynamic Grid -->
        <?php if (empty($alumni_list)): ?>
            <div class="glass-dark rounded-[48px] py-32 flex flex-col items-center justify-center text-center px-6">
                <div class="w-24 h-24 bg-white/5 rounded-full flex items-center justify-center mb-8 border border-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                </div>
                <h3 class="text-2xl font-black text-white mb-2 uppercase tracking-tighter italic">No Matches Found</h3>
                <p class="text-slate-500 max-w-md mx-auto leading-relaxed">The elite directory could not find any members matching your refined search criteria.</p>
                <a href="directory.php" class="mt-8 text-blue-500 font-black text-[10px] uppercase tracking-widest hover:text-blue-400 transition-all border-b border-blue-500/20 pb-1">Reset Filters</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
                <?php foreach ($alumni_list as $row): ?>
                <div class="glass-card rounded-[40px] p-8 flex flex-col group relative overflow-hidden">
                    
                    <!-- Decorative Background Light -->
                    <div class="absolute -right-10 -top-10 w-32 h-32 bg-blue-500/5 rounded-full blur-3xl group-hover:bg-blue-500/10 transition-all duration-700"></div>

                    <div class="flex items-start justify-between mb-8 relative z-10">
                        <?php echo renderAvatar($row['name'], 'w-20 h-20 text-2xl border-4 border-white/5 shadow-2xl ring-1 ring-white/10', $row['profile_pic']); ?>
                        <div class="text-right">
                            <span class="px-4 py-1.5 bg-blue-500/10 text-blue-500 text-[10px] font-black rounded-full uppercase tracking-widest border border-blue-500/20">Class of <?php echo $row['batch_year']; ?></span>
                        </div>
                    </div>
                    
                    <div class="mb-8 relative z-10">
                        <h3 class="font-outfit font-black text-white text-xl mb-1 uppercase tracking-tight group-hover:text-blue-400 transition-colors line-clamp-1"><?php echo htmlspecialchars($row['name']); ?></h3>
                        <p class="text-[10px] font-black text-slate-500 uppercase tracking-[2px] opacity-80"><?php echo htmlspecialchars($row['program']); ?></p>
                    </div>
                    
                    <div class="space-y-4 mb-10 relative z-10">
                        <div class="flex items-center gap-4 group/item">
                            <div class="w-8 h-8 rounded-xl bg-white/5 flex items-center justify-center text-blue-500 group-hover/item:bg-blue-500 group-hover/item:text-white transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" /></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-400 group-hover/item:text-white transition-colors truncate"><?php echo htmlspecialchars($row['job_title'] ?? 'N/A'); ?></span>
                        </div>
                        <div class="flex items-center gap-4 group/item">
                            <div class="w-8 h-8 rounded-xl bg-white/5 flex items-center justify-center text-slate-500 group-hover/item:bg-slate-700 group-hover/item:text-white transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-10V4m0 10V4m-4 5h4" /></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-500 group-hover/item:text-slate-300 transition-colors truncate"><?php echo htmlspecialchars($row['company'] ?? 'N/A'); ?></span>
                        </div>
                    </div>

                        View Alumni Profile
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>
