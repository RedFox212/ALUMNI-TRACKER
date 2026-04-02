<?php
// pages/import.php  — HARDEST (CSV upload, parse, validate, import, log)
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();
if ($_SESSION['user_role'] !== 'admin') { header('Location: dashboard.php'); exit; }

$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$result = null; // import result summary

// ── Column mapping from CSV header ─────────────────────────────────────────────
// Expected columns (case-insensitive, flexible):
// name, email, student_id, program, batch_year, address, contact_no,
// employment_status, company, job_title, discipline_match, years_experience

function normalizeKey($key) {
    return strtolower(trim(preg_replace('/[^a-z0-9]/i', '_', $key)));
}

$MAP = [
    'name'              => ['name','full_name','fullname'],
    'email'             => ['email','email_address','e_mail'],
    'student_id'        => ['student_id','studentid','student_no','id'],
    'program'           => ['program','course','degree'],
    'batch_year'        => ['batch_year','batchyear','batch','graduation_year','grad_year','year'],
    'address'           => ['address','home_address','location'],
    'contact_no'        => ['contact_no','contact','phone','mobile','contact_number'],
    'employment_status' => ['employment_status','employment','status'],
    'company'           => ['company','employer','company_name','organization'],
    'job_title'         => ['job_title','jobtitle','position','title','designation'],
    'discipline_match'  => ['discipline_match','in_discipline','related_field','matched'],
    'years_experience'  => ['years_experience','experience','years','exp'],
];

function mapHeaders(array $headers): array {
    global $MAP;
    $mapped = [];
    foreach ($headers as $i => $h) {
        $norm = normalizeKey($h);
        foreach ($MAP as $field => $aliases) {
            if (in_array($norm, $aliases)) { $mapped[$i] = $field; break; }
        }
    }
    return $mapped;
}

function parseDisc($val): int {
    $v = strtolower(trim($val));
    return in_array($v, ['1','yes','true','y','matched','related']) ? 1 : 0;
}

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file    = $_FILES['csv_file'];
    $errors  = [];
    $added   = 0;
    $skipped = 0;
    $invalid = 0;
    $row_errors = [];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "File upload failed. Please try again.";
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'csv') {
        $errors[] = "Only CSV files are allowed.";
    } elseif ($file['size'] > 5 * 1024 * 1024) {
        $errors[] = "File too large. Max 5 MB.";
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        $raw_headers = fgetcsv($handle);
        if (!$raw_headers) { $errors[] = "Empty or invalid CSV."; }
        else {
            $header_map = mapHeaders($raw_headers);
            $row_num = 1;

            while (($row = fgetcsv($handle)) !== false) {
                $row_num++;
                // Map row to named fields
                $data = [];
                foreach ($header_map as $idx => $field) {
                    $data[$field] = isset($row[$idx]) ? trim($row[$idx]) : '';
                }

                // Validate required fields
                if (empty($data['name']) || empty($data['email'])) {
                    $row_errors[] = "Row $row_num: Missing name or email — skipped.";
                    $invalid++; continue;
                }

                // Sanitize
                $email   = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
                $batch   = isset($data['batch_year']) && is_numeric($data['batch_year']) ? (int)$data['batch_year'] : null;
                $yrs_exp = isset($data['years_experience']) && is_numeric($data['years_experience']) ? (int)$data['years_experience'] : 0;
                $disc    = isset($data['discipline_match']) ? parseDisc($data['discipline_match']) : 0;

                // Check duplicate by email
                $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $chk->execute([$email]);
                if ($chk->fetchColumn()) { $skipped++; continue; }

                try {
                    $pdo->beginTransaction();

                    // Insert user
                    $default_hash = password_hash('password', PASSWORD_DEFAULT);
                    $uStmt = $pdo->prepare("INSERT INTO users (name, email, password_hash, role, is_active, first_login) VALUES (?, ?, ?, 'alumni', 1, 1)");
                    $uStmt->execute([$data['name'], $email, $default_hash]);
                    $new_uid = $pdo->lastInsertId();

                    // Insert alumni profile
                    $aStmt = $pdo->prepare(
                        "INSERT INTO alumni (user_id, student_id, program, batch_year, address, contact_no, employment_status, company, job_title, discipline_match, years_experience)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $aStmt->execute([
                        $new_uid,
                        $data['student_id']        ?? null,
                        $data['program']            ?? null,
                        $batch,
                        $data['address']            ?? null,
                        $data['contact_no']         ?? null,
                        $data['employment_status']  ?? 'Employed',
                        $data['company']            ?? null,
                        $data['job_title']          ?? null,
                        $disc,
                        $yrs_exp,
                    ]);

                    $pdo->commit();
                    $added++;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $row_errors[] = "Row $row_num ({$data['name']}): DB error — " . $e->getMessage();
                    $invalid++;
                }
            }
            fclose($handle);

            // Log import
            $pdo->prepare("INSERT INTO import_logs (filename, records_added, duplicates_skipped, imported_by) VALUES (?, ?, ?, ?)")
                ->execute([$file['name'], $added, $skipped, $_SESSION['user_id']]);

            $result = compact('added', 'skipped', 'invalid', 'row_errors');
        }
    }
    if (!empty($errors)) $result = ['errors' => $errors];
}

// Fetch import logs
$import_logs = $pdo->query(
    "SELECT il.*, u.name as admin_name FROM import_logs il JOIN users u ON il.imported_by = u.id ORDER BY il.imported_at DESC LIMIT 15"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Data – LATS Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; } .drag-active { border-color: #2563eb; background: #eff6ff; }</style>
</head>
<body class="bg-slate-50 min-h-screen flex">

<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-64">
    <header class="h-16 bg-white border-b border-slate-200 px-8 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center gap-3 text-slate-400">
            <span class="text-sm font-medium text-slate-800 uppercase tracking-tighter">Import Data</span>
            <span class="w-1 h-1 bg-slate-200 rounded-full"></span>
            <span class="text-xs">Bulk Alumni CSV Upload</span>
        </div>
        <a href="?download_template=1" class="h-9 px-4 bg-slate-100 text-slate-600 font-bold text-xs rounded-xl hover:bg-slate-200 flex items-center gap-1.5 transition-all">📋 Download Template</a>
    </header>

    <?php
    // Template download
    if (isset($_GET['download_template'])) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="lats_import_template.csv"');
        $f = fopen('php://output', 'w');
        fputcsv($f, ['name','email','student_id','program','batch_year','address','contact_no','employment_status','company','job_title','discipline_match','years_experience']);
        fputcsv($f, ['Juan Dela Cruz','juan@example.com','2024-0001','BSIT','2024','Muntinlupa City','09171234567','Employed','GCash','Junior Dev','yes','0']);
        fclose($f); exit;
    }
    ?>

    <div class="p-8 max-w-5xl mx-auto w-full">
        <div class="mb-8">
            <h1 class="text-3xl font-black text-slate-800 italic">IMPORT ALUMNI DATA</h1>
            <p class="text-slate-400 text-sm font-medium">Upload a CSV file to bulk-import alumni records. Download the template for correct formatting.</p>
        </div>

        <!-- Result Banner -->
        <?php if ($result): ?>
            <?php if (isset($result['errors'])): ?>
                <div class="mb-6 bg-red-50 border border-red-100 rounded-3xl p-5">
                    <p class="font-bold text-red-700 mb-2">Upload Failed</p>
                    <?php foreach ($result['errors'] as $e): ?><p class="text-sm text-red-500">• <?php echo htmlspecialchars($e); ?></p><?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="mb-6 bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-emerald-400 p-6">
                        <h2 class="text-white font-black text-xl">✅ Import Complete!</h2>
                    </div>
                    <div class="p-6 grid grid-cols-3 gap-4">
                        <div class="text-center">
                            <p class="text-3xl font-black text-green-600"><?php echo $result['added']; ?></p>
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest mt-1">Records Added</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-black text-amber-500"><?php echo $result['skipped']; ?></p>
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest mt-1">Duplicates Skipped</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-black text-red-500"><?php echo $result['invalid']; ?></p>
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest mt-1">Invalid Rows</p>
                        </div>
                    </div>
                    <?php if (!empty($result['row_errors'])): ?>
                        <div class="border-t border-slate-50 p-6">
                            <p class="text-xs font-black text-slate-400 uppercase tracking-widest mb-3">Row Errors</p>
                            <div class="space-y-1 max-h-40 overflow-y-auto">
                                <?php foreach ($result['row_errors'] as $re): ?>
                                    <p class="text-xs text-red-500">• <?php echo htmlspecialchars($re); ?></p>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Upload Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="bg-gradient-to-br from-slate-800 to-slate-900 p-6">
                        <h2 class="text-white font-black text-lg italic">UPLOAD CSV</h2>
                        <p class="text-slate-400 text-xs mt-1">Max file size: 5 MB</p>
                    </div>
                    <div class="p-6">
                        <form method="POST" enctype="multipart/form-data" id="importForm">
                            <!-- Drag-drop zone -->
                            <div id="dropzone"
                                 class="border-2 border-dashed border-slate-200 rounded-2xl p-8 text-center cursor-pointer hover:border-blue-400 hover:bg-blue-50 transition-all mb-4"
                                 onclick="document.getElementById('csv_file').click()">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                <p class="font-bold text-slate-500 text-sm" id="dropzone_label">Click to browse or drag & drop</p>
                                <p class="text-xs text-slate-400 mt-1">Only .csv files accepted</p>
                            </div>
                            <input type="file" name="csv_file" id="csv_file" accept=".csv" class="hidden" onchange="updateDropzone(this)">

                            <button type="submit" id="submitBtn" class="w-full h-12 bg-blue-600 text-white rounded-xl font-bold hover:bg-blue-700 active:scale-95 transition-all shadow-lg shadow-blue-200">
                                📥 Import Records
                            </button>
                        </form>

                        <!-- Format Guide -->
                        <div class="mt-6 bg-slate-50 rounded-2xl p-4">
                            <p class="text-xs font-black text-slate-500 uppercase tracking-widest mb-3">Required CSV Columns</p>
                            <div class="space-y-1">
                                <?php foreach (['name ✓','email ✓','student_id','program','batch_year','employment_status','company','job_title','discipline_match (yes/no)','years_experience'] as $col): ?>
                                    <p class="text-xs font-mono text-slate-600 flex items-center gap-1">
                                        <?php echo strpos($col,'✓') !== false ? '🔴' : '⚪'; ?>
                                        <?php echo $col; ?>
                                    </p>
                                <?php endforeach; ?>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-3">🔴 = Required. All others optional.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Import Log -->
            <div class="lg:col-span-3">
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 border-b border-slate-50 flex justify-between items-center bg-slate-50/50">
                        <h2 class="font-black text-slate-800 uppercase tracking-tighter">Import History</h2>
                        <span class="text-xs text-slate-400"><?php echo count($import_logs); ?> imports</span>
                    </div>
                    <?php if (empty($import_logs)): ?>
                        <div class="py-16 text-center text-slate-300">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-14 w-14 mx-auto mb-4 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <p class="font-medium italic">No imports yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="divide-y divide-slate-50">
                            <?php foreach ($import_logs as $log): ?>
                            <div class="p-5 hover:bg-slate-50 transition-all flex items-center gap-4">
                                <div class="w-10 h-10 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-bold text-slate-800 text-sm truncate"><?php echo htmlspecialchars($log['filename']); ?></p>
                                    <p class="text-xs text-slate-400 mt-0.5"><?php echo formatDate($log['imported_at']); ?> by <?php echo htmlspecialchars($log['admin_name']); ?></p>
                                </div>
                                <div class="flex-shrink-0 flex items-center gap-3 text-center">
                                    <div>
                                        <p class="text-sm font-black text-green-600"><?php echo $log['records_added']; ?></p>
                                        <p class="text-[9px] text-slate-400 uppercase font-bold">added</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-black text-amber-500"><?php echo $log['duplicates_skipped']; ?></p>
                                        <p class="text-[9px] text-slate-400 uppercase font-bold">skip</p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('csv_file');

dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-active'); });
dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-active'));
dropzone.addEventListener('drop', e => {
    e.preventDefault();
    dropzone.classList.remove('drag-active');
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        updateDropzone(fileInput);
    }
});

function updateDropzone(input) {
    if (input.files && input.files[0]) {
        document.getElementById('dropzone_label').textContent = '📄 ' + input.files[0].name;
        dropzone.classList.add('drag-active');
    }
}
</script>
</body>
</html>
