<?php
// pages/my-polls.php  — #4 MEDIUM: vote + prevent double-vote + live results
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
$user_id   = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$success   = $error = null;

// Get alumni batch/program for visibility filtering
$stmt = $pdo->prepare("SELECT batch_year, program FROM alumni WHERE user_id = ?");
$stmt->execute([$user_id]);
$ap = $stmt->fetch();
$batch   = $ap['batch_year'] ?? null;
$program = $ap['program']    ?? null;

// Handle vote submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['poll_id'])) {
    $poll_id    = (int)$_POST['poll_id'];
    $option_ids = $_POST['option_ids'] ?? [];
    if (!is_array($option_ids)) $option_ids = [$option_ids];

    // Check if already voted
    $chk = $pdo->prepare("SELECT COUNT(*) FROM poll_votes WHERE poll_id = ? AND user_id = ?");
    $chk->execute([$poll_id, $user_id]);
    if ($chk->fetchColumn() > 0) {
        $error = "You have already voted on this poll.";
    } elseif (empty($option_ids)) {
        $error = "Please select at least one option.";
    } else {
        // Check poll is still open
        $pstmt = $pdo->prepare("SELECT * FROM polls WHERE id = ? AND (close_date IS NULL OR close_date > NOW())");
        $pstmt->execute([$poll_id]);
        $poll = $pstmt->fetch();
        if (!$poll) {
            $error = "This poll is closed or no longer available.";
        } else {
            $vStmt = $pdo->prepare("INSERT INTO poll_votes (poll_id, option_id, user_id) VALUES (?, ?, ?)");
            foreach ($option_ids as $oid) {
                $vStmt->execute([$poll_id, (int)$oid, $user_id]);
            }
            $success = "Your vote has been recorded! ✅";
        }
    }
}

// Fetch polls visible to this alumni
$polls_stmt = $pdo->prepare(
    "SELECT * FROM polls
     WHERE (close_date IS NULL OR close_date > NOW())
       AND (visibility = 'all'
            OR (visibility = 'batch'   AND target_batch   = ?)
            OR (visibility = 'program' AND target_program = ?))
     ORDER BY created_at DESC"
);
$polls_stmt->execute([$batch, $program]);
$open_polls = $polls_stmt->fetchAll();

// Fetch closed polls the user participated in
$past_stmt = $pdo->prepare(
    "SELECT p.* FROM polls p
     INNER JOIN poll_votes pv ON p.id = pv.poll_id
     WHERE pv.user_id = ? AND (p.close_date IS NOT NULL AND p.close_date <= NOW())
     GROUP BY p.id ORDER BY p.close_date DESC"
);
$past_stmt->execute([$user_id]);
$past_polls = $past_stmt->fetchAll();

function getPollData(PDO $pdo, int $poll_id, int $user_id): array {
    $opts = $pdo->prepare(
        "SELECT po.id, po.option_text, COUNT(pv.id) AS votes
         FROM poll_options po
         LEFT JOIN poll_votes pv ON po.id = pv.option_id
         WHERE po.poll_id = ?
         GROUP BY po.id, po.option_text ORDER BY po.id"
    );
    $opts->execute([$poll_id]);
    $options = $opts->fetchAll();

    $voted_chk = $pdo->prepare("SELECT option_id FROM poll_votes WHERE poll_id = ? AND user_id = ?");
    $voted_chk->execute([$poll_id, $user_id]);
    $voted_ids = $voted_chk->fetchAll(PDO::FETCH_COLUMN);

    $total = array_sum(array_column($options, 'votes'));
    return ['options' => $options, 'voted_ids' => $voted_ids, 'total' => $total, 'has_voted' => !empty($voted_ids)];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Polls – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex">

<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3 text-slate-400">
            <a href="dashboard.php" class="text-slate-400 hover:text-slate-600 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Polls & Voting</span>
        </div>
        <span class="text-xs bg-blue-50 text-blue-600 font-bold px-3 py-1 rounded-full"><?php echo count($open_polls); ?> active polls</span>
    </header>

    <div class="p-8 max-w-4xl mx-auto w-full">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 italic">POLLS & VOTING</h1>
            <p class="text-slate-400 text-sm font-medium">Share your voice with the alumni community.</p>
        </div>

        <?php if ($success): ?><div class="mb-6 bg-green-50 border border-green-100 text-green-700 px-5 py-4 rounded-2xl text-sm font-medium flex items-center gap-2"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><?php echo $success; ?></div><?php endif; ?>
        <?php if ($error):?><div class="mb-6 bg-red-50 border border-red-100 text-red-600 px-5 py-4 rounded-2xl text-sm font-medium"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Open Polls -->
        <section class="mb-10">
            <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">Active Polls</h2>
            <?php if (empty($open_polls)): ?>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 py-16 text-center text-slate-300">
                    <div class="text-5xl mb-3">📭</div>
                    <p class="font-medium italic">No active polls right now.</p>
                </div>
            <?php else: ?>
                <div class="space-y-5">
                    <?php foreach ($open_polls as $poll):
                        $pd = getPollData($pdo, $poll['id'], $user_id);
                        $input_type = $poll['poll_type'] === 'multiple' ? 'checkbox' : 'radio';
                    ?>
                    <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden hover:shadow-md transition-all">
                        <div class="p-6">
                            <div class="flex items-start justify-between gap-4 mb-5">
                                <div>
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="text-[10px] font-black px-2 py-0.5 bg-green-100 text-green-600 rounded uppercase">Open</span>
                                        <span class="text-[10px] font-black px-2 py-0.5 bg-indigo-100 text-indigo-600 rounded uppercase"><?php echo $poll['poll_type']; ?></span>
                                        <?php if ($poll['is_anonymous']): ?><span class="text-[10px] font-black px-2 py-0.5 bg-slate-100 text-slate-500 rounded uppercase">Anonymous</span><?php endif; ?>
                                    </div>
                                    <h3 class="font-black text-slate-800 text-lg leading-tight"><?php echo htmlspecialchars($poll['title']); ?></h3>
                                    <?php if ($poll['description']): ?><p class="text-slate-400 text-sm mt-1"><?php echo htmlspecialchars($poll['description']); ?></p><?php endif; ?>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <p class="text-xl font-black text-slate-700"><?php echo $pd['total']; ?></p>
                                    <p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Votes</p>
                                </div>
                            </div>

                            <?php if ($pd['has_voted']): ?>
                                <!-- Show results -->
                                <div class="space-y-3">
                                    <?php foreach ($pd['options'] as $opt):
                                        $pct = $pd['total'] > 0 ? round(($opt['votes']/$pd['total'])*100) : 0;
                                        $is_my_vote = in_array($opt['id'], $pd['voted_ids']);
                                    ?>
                                    <div class="<?php echo $is_my_vote ? 'bg-blue-50 rounded-xl p-3' : 'p-3'; ?>">
                                        <div class="flex justify-between text-sm mb-1.5">
                                            <span class="font-<?php echo $is_my_vote?'black':'medium'; ?> text-slate-<?php echo $is_my_vote?'800':'600'; ?> flex items-center gap-2">
                                                <?php if ($is_my_vote): ?><span class="text-blue-500">✓</span><?php endif; ?>
                                                <?php echo htmlspecialchars($opt['option_text']); ?>
                                            </span>
                                            <span class="font-bold text-slate-500"><?php echo $opt['votes']; ?> (<?php echo $pct; ?>%)</span>
                                        </div>
                                        <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden">
                                            <div class="h-full rounded-full transition-all duration-700 <?php echo $is_my_vote?'bg-blue-500':'bg-slate-300'; ?>" style="width:<?php echo $pct; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <p class="text-xs text-blue-500 font-bold mt-4 text-center">You have voted on this poll ✅</p>
                            <?php else: ?>
                                <!-- Voting form -->
                                <form method="POST" class="space-y-2">
                                    <input type="hidden" name="poll_id" value="<?php echo $poll['id']; ?>">
                                    <?php foreach ($pd['options'] as $opt): ?>
                                    <label class="flex items-center gap-3 p-4 bg-slate-50 rounded-xl cursor-pointer hover:bg-blue-50 hover:border-blue-200 border border-transparent transition-all group">
                                        <input type="<?php echo $input_type; ?>" name="option_ids[]" value="<?php echo $opt['id']; ?>"
                                            class="w-4 h-4 text-blue-600 accent-blue-600">
                                        <span class="text-sm font-medium text-slate-700 group-hover:text-blue-700 transition-colors"><?php echo htmlspecialchars($opt['option_text']); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                    <button type="submit" class="w-full h-11 mt-3 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 active:scale-95 transition-all shadow-lg shadow-blue-200 text-sm">
                                        Submit Vote
                                    </button>
                                </form>
                            <?php endif; ?>

                            <?php if ($poll['close_date']): ?>
                                <p class="text-[10px] text-slate-400 text-right mt-3">Closes <?php echo formatDate($poll['close_date']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Past Polls -->
        <?php if (!empty($past_polls)): ?>
        <section>
            <h2 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4">My Past Polls</h2>
            <div class="space-y-4">
                <?php foreach ($past_polls as $poll):
                    $pd = getPollData($pdo, $poll['id'], $user_id);
                ?>
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden opacity-80">
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <span class="text-[10px] font-black px-2 py-0.5 bg-slate-100 text-slate-500 rounded uppercase mr-2">Closed</span>
                                <h3 class="font-bold text-slate-700 inline"><?php echo htmlspecialchars($poll['title']); ?></h3>
                            </div>
                            <span class="text-xs text-slate-400"><?php echo $pd['total']; ?> total votes</span>
                        </div>
                        <div class="space-y-2">
                            <?php foreach ($pd['options'] as $opt):
                                $pct = $pd['total'] > 0 ? round(($opt['votes']/$pd['total'])*100) : 0;
                                $is_my = in_array($opt['id'], $pd['voted_ids']);
                            ?>
                            <div class="flex items-center gap-3">
                                <span class="text-xs text-slate-<?php echo $is_my?'800 font-black':'500'; ?> w-32 truncate"><?php if($is_my) echo '<span class="text-blue-500">✓ </span>'; echo htmlspecialchars($opt['option_text']); ?></span>
                                <div class="flex-1 h-2 bg-slate-100 rounded-full overflow-hidden">
                                    <div class="h-full <?php echo $is_my?'bg-blue-500':'bg-slate-300'; ?> rounded-full" style="width:<?php echo $pct; ?>%"></div>
                                </div>
                                <span class="text-xs text-slate-400 w-10 text-right"><?php echo $pct; ?>%</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
