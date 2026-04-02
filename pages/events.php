<?php
// pages/events.php — Alumni: Events and Reunions
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$success = $error = null;

// Handle RSVP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rsvp'])) {
    if (!verifyCsrf()) { $error = "Security mismatch."; }
    else {
        $ev_id  = (int)$_POST['event_id'];
        $status = $_POST['status'] ?? 'Going';
        $stmt = $pdo->prepare("INSERT INTO event_rsvps (event_id, user_id, status) VALUES (?,?,?) 
                               ON DUPLICATE KEY UPDATE status = VALUES(status)");
        if ($stmt->execute([$ev_id, $user_id])) {
            $success = "RSVP Updated to: " . $status;
        }
    }
}

// Fetch events from announcements (category 'Event')
$events = $pdo->query("SELECT *, (SELECT status FROM event_rsvps WHERE event_id = announcements.id AND user_id = $user_id) as my_rsvp FROM announcements WHERE category = 'Event' ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Events – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;}</style>
</head>
<body class="bg-slate-50 transition-colors duration-500 min-h-screen">
    <?php require_once '../includes/sidebar.php'; ?>

    <main class="flex-1 flex flex-col lg:ml-64">
        <?php 
            $topbar_title = 'Event Tracker';
            $topbar_subtitle = count($events) . ' Total Gatherings';
            require_once '../includes/topbar.php'; 
        ?>

    <div class="p-8 max-w-5xl mx-auto w-full">
        <div class="mb-12">
            <h2 class="text-4xl font-black tracking-tight leading-none mb-3">GRAND REUNIONS & <span class="text-blue-500">SEMINARS</span></h2>
            <p class="text-slate-500 text-sm font-medium">Keep track of upcoming alumni gatherings and professional development sessions.</p>
        </div>

        <?php if ($success): ?><div class="mb-8 bg-blue-500/10 text-blue-400 p-5 rounded-3xl text-sm font-bold border border-blue-500/20">📅 <?php echo $success; ?></div><?php endif; ?>

        <div class="space-y-6">
            <?php foreach ($events as $ev): ?>
            <div class="bg-white/5 rounded-[40px] p-8 border border-white/5 hover:border-white/20 transition-all duration-500 hover:bg-white/[0.07] group">
                <div class="flex flex-col md:flex-row gap-10">
                    <div class="flex-shrink-0 w-full md:w-32 flex flex-col items-center justify-center p-6 bg-blue-600 rounded-[32px] text-white">
                        <span class="text-[10px] font-black uppercase tracking-widest opacity-60">Month</span>
                        <span class="text-3xl font-black leading-none my-1"><?php echo date('M', strtotime($ev['created_at'])); ?></span>
                        <span class="text-xl font-medium opacity-80"><?php echo date('d', strtotime($ev['created_at'])); ?></span>
                    </div>
                    
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-4">
                            <span class="px-3 py-1 bg-white/5 text-[10px] font-black rounded-full uppercase tracking-widest text-slate-400 border border-white/5">ALUMNI OFFICIAL</span>
                        </div>
                        <h3 class="text-2xl font-black text-white group-hover:text-blue-400 transition-colors uppercase tracking-tight mb-2"><?php echo htmlspecialchars($ev['title']); ?></h3>
                        <p class="text-slate-400 text-sm leading-relaxed mb-8"><?php echo nl2br(htmlspecialchars($ev['body'])); ?></p>
                        
                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" class="flex gap-2 bg-black/20 p-2 rounded-2xl border border-white/5">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="event_id" value="<?php echo $ev['id']; ?>">
                                <input type="hidden" name="rsvp" value="1">
                                
                                <button type="submit" name="status" value="Going" class="px-6 h-10 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $ev['my_rsvp'] === 'Going' ? 'bg-blue-600 text-white shadow-lg' : 'text-slate-400 hover:bg-white/5'; ?>">Going</button>
                                <button type="submit" name="status" value="Maybe" class="px-6 h-10 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $ev['my_rsvp'] === 'Maybe' ? 'bg-amber-600/20 text-amber-500 border border-amber-500/20' : 'text-slate-400 hover:bg-white/5'; ?>">Maybe</button>
                                <button type="submit" name="status" value="Declined" class="px-6 h-10 rounded-xl text-[10px] font-black uppercase tracking-widest transition-all <?php echo $ev['my_rsvp'] === 'Declined' ? 'bg-red-600/20 text-red-500 border border-red-500/20' : 'text-slate-400 hover:bg-white/5'; ?>">Skip</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($events)): ?>
            <div class="py-24 text-center bg-white/5 rounded-[48px] border-2 border-dashed border-white/10">
                <div class="text-7xl mb-8 opacity-20">🎫</div>
                <h3 class="text-3xl font-black text-white uppercase tracking-tight">Stay Tuned!</h3>
                <p class="text-slate-500 mt-4 max-w-sm mx-auto font-medium">No grand events are scheduled at the moment. Keep checking your notifications.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

</body>
</html>
