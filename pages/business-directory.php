<?php
// pages/business-directory.php — Alumni: View/Register businesses
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/helpers.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_role = $_SESSION['user_role'];
$success = $error = null;

// Ensure schema for Map and Status
try {
    $pdo->exec("ALTER TABLE businesses ADD COLUMN IF NOT EXISTS latitude DECIMAL(10,8) DEFAULT NULL");
    $pdo->exec("ALTER TABLE businesses ADD COLUMN IF NOT EXISTS longitude DECIMAL(11,8) DEFAULT NULL");
    $pdo->exec("ALTER TABLE businesses ADD COLUMN IF NOT EXISTS status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending'");
} catch(Exception $e) {}

// Handle Business Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_biz'])) {
    if (!verifyCsrf()) { $error = "Security mismatch."; }
    else {
        $biz_id = (int)$_POST['biz_id'];
        
        // Check permissions: Owner or Admin
        $stmt = $pdo->prepare("SELECT owner_id FROM businesses WHERE id = ?");
        $stmt->execute([$biz_id]);
        $owner = $stmt->fetchColumn();
        
        if ($owner == $user_id || $user_role === 'admin') {
            $stmt = $pdo->prepare("DELETE FROM businesses WHERE id = ?");
            $stmt->execute([$biz_id]);
            $success = "Business enterprise has been removed from the directory.";
        } else {
            $error = "Unauthorized action.";
        }
    }
}

// Handle Business Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reg_biz'])) {
    if (!verifyCsrf()) { $error = "Security mismatch."; }
    else {
        // Enforce ONE business limit (Pending or Verified)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM businesses WHERE owner_id = ? AND status != 'rejected'");
        $stmt->execute([$user_id]);
        $existing_biz = $stmt->fetchColumn();

        if ($existing_biz > 0 && $user_role !== 'admin') {
            $error = "You already have an active business enterprise listing. Please remove your current one to register a new one.";
        } else {
            $biz_name = trim($_POST['biz_name']);
            $category = $_POST['category'] ?? 'Others';
            $desc     = trim($_POST['description']);
            $website  = trim($_POST['website'] ?? '');
            $contact  = trim($_POST['contact_no'] ?? '');
            $lat      = $_POST['latitude'] ?? null;
            $lng      = $_POST['longitude'] ?? null;

            if ($biz_name && $desc && $contact) {
                // By default, new registrations are 'pending' for admin review
                $stmt = $pdo->prepare("INSERT INTO businesses (owner_id, biz_name, category, description, website, contact_no, latitude, longitude, status) VALUES (?,?,?,?,?,?,?,?,'pending')");
                $stmt->execute([$user_id, $biz_name, $category, $desc, $website, $contact, $lat, $lng]);
                $success = "Business registered! It will appear on the directory after admin verification.";
            } else {
                $error = "Business Name, Description, and Contact info are required.";
            }
        }
    }
}

// Fetch verified businesses
$businesses = $pdo->query("SELECT b.*, u.name as owner_name FROM businesses b JOIN users u ON b.owner_id = u.id WHERE b.status = 'verified' ORDER BY b.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alumni Businesses – LATS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>body{font-family:'Inter',sans-serif;} .modal{display:none;} .modal.open{display:flex;}</style>
    <!-- Leaflet.js (Open Source Map) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
</head>
<body class="bg-white min-h-screen flex">
<?php require_once '../includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col lg:ml-72">
    <?php 
        $topbar_title = 'Business Directory';
        $topbar_subtitle = 'Shop Alumni First';
        $topbar_actions = '<button onclick="document.getElementById(\'regBizModal\').classList.add(\'open\')" class="bg-rose-500 text-white text-[10px] font-black px-5 py-2.5 rounded-2xl hover:bg-rose-600 transition-all uppercase tracking-widest shadow-lg shadow-rose-200">Register Business</button>';
        require_once '../includes/topbar.php'; 
    ?>

    <div class="p-8 max-w-6xl mx-auto w-full">
        <div class="mb-12">
            <h1 class="text-4xl font-black text-slate-800 italic uppercase tracking-tighter">ALUMNI ENTERPRISE</h1>
            <p class="text-slate-400 text-sm font-medium">Discover and support businesses owned by your fellow Lyceum graduates.</p>
        </div>

        <?php if ($success): ?><div class="mb-8 bg-rose-50 text-rose-700 p-4 rounded-2xl text-sm font-bold border border-rose-100 animate-pulse">🛍️ <?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="mb-8 bg-red-50 text-red-600 p-4 rounded-2xl text-sm font-bold border border-red-100">⚠️ <?php echo $error; ?></div><?php endif; ?>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php foreach ($businesses as $biz): ?>
            <div class="group relative bg-slate-50 rounded-[48px] p-10 border border-transparent hover:border-rose-200 hover:bg-white transition-all duration-500 hover:shadow-2xl hover:shadow-rose-100">
                
                <!-- Admin/Owner Actions -->
                <?php if ($biz['owner_id'] == $user_id || $user_role === 'admin'): ?>
                <div class="absolute top-8 right-8 z-10">
                    <form method="POST" onsubmit="return confirm('Are you sure you want to remove this business enterprise?');">
                        <?php echo csrfField(); ?>
                        <input type="hidden" name="biz_id" value="<?php echo $biz['id']; ?>">
                        <button type="submit" name="delete_biz" class="w-10 h-10 bg-white text-rose-500 rounded-full flex items-center justify-center hover:bg-rose-500 hover:text-white transition-all shadow-md group-hover:shadow-rose-200">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </form>
                </div>
                <?php endif; ?>

                <div class="flex items-center justify-between mb-8">
                    <div class="w-16 h-16 bg-white rounded-3xl flex items-center justify-center text-3xl font-black text-rose-500 shadow-sm transition-all group-hover:scale-110 group-hover:bg-rose-600 group-hover:text-white group-hover:rotate-6">
                        <?php echo strtoupper(substr($biz['biz_name'], 0, 1)); ?>
                    </div>
                    <?php if ($biz['owner_id'] != $user_id && $user_role !== 'admin'): ?>
                    <span class="px-4 py-1.5 bg-white text-rose-500 text-[10px] font-black rounded-full uppercase tracking-widest shadow-sm group-hover:bg-rose-50 transition-colors">
                        <?php echo htmlspecialchars($biz['category']); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <div class="mb-6">
                    <h3 class="text-2xl font-black text-slate-800 truncate uppercase tracking-tighter group-hover:text-rose-600 transition-colors"><?php echo htmlspecialchars($biz['biz_name']); ?></h3>
                    <p class="text-[10px] font-black text-slate-300 uppercase tracking-widest mt-2">Owner: <span class="text-slate-600"><?php echo htmlspecialchars($biz['owner_name']); ?></span></p>
                </div>

                <div class="text-slate-500 text-sm leading-relaxed mb-10 line-clamp-3">
                    <?php echo nl2br(htmlspecialchars($biz['description'])); ?>
                </div>

                <div class="pt-8 border-t border-slate-100 flex items-center gap-3">
                    <?php if ($biz['website']): ?>
                    <a href="<?php echo htmlspecialchars($biz['website']); ?>" target="_blank" class="flex-1 h-12 bg-slate-900 text-white rounded-2xl flex items-center justify-center gap-2 text-xs font-black hover:bg-rose-600 transition-all">
                        WEBSITE
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    <?php endif; ?>
                    <a href="tel:<?php echo htmlspecialchars($biz['contact_no']); ?>" class="h-12 w-12 bg-white text-slate-400 rounded-2xl flex items-center justify-center border border-slate-100 hover:text-rose-500 hover:border-rose-200 transition-all">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($businesses)): ?>
            <div class="col-span-full py-20 text-center bg-slate-50 rounded-[48px] border-2 border-dashed border-slate-200">
                <div class="text-6xl mb-6">🏬</div>
                <h3 class="text-2xl font-black text-slate-800 tracking-tighter uppercase">No businesses listed</h3>
                <p class="text-slate-400 max-w-xs mx-auto text-sm mt-2">Support our alumni by registering your own business enterprise today.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Reg Biz Modal -->
<div id="regBizModal" class="modal fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 items-center justify-center p-4">
    <div class="bg-white rounded-[56px] w-full max-w-lg shadow-2xl overflow-hidden">
        <div class="bg-rose-500 p-10 text-white">
            <h2 class="text-3xl font-black italic tracking-tighter uppercase leading-none">Register Enterprise</h2>
            <p class="text-rose-100 text-sm mt-3 font-medium">Join our community of alumni entrepreneurs.</p>
        </div>
        <form method="POST" class="p-10 space-y-6">
            <?php echo csrfField(); ?>
            <input type="hidden" name="reg_biz" value="1">
            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-[10px] font-black text-slate-300 uppercase tracking-[3px] mb-2">Business Name *</label>
                    <input type="text" name="biz_name" required class="w-full h-14 bg-slate-50 border border-slate-100 rounded-2xl px-5 outline-none focus:ring-4 focus:ring-rose-50 focus:border-rose-200 transition-all font-bold text-slate-800">
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-300 uppercase tracking-[3px] mb-2">Category</label>
                    <select name="category" class="w-full h-14 bg-slate-50 border border-slate-100 rounded-2xl px-5 outline-none focus:ring-4 focus:ring-rose-50 focus:border-rose-200 transition-all font-bold text-slate-800 cursor-pointer">
                        <option>Technology</option><option>Food & Bev</option><option>Retail</option><option>Services</option><option>Real Estate</option><option>Health</option><option>Education</option><option>Others</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-300 uppercase tracking-[3px] mb-2">Website (URL)</label>
                    <input type="text" name="website" placeholder="https://" class="w-full h-14 bg-slate-50 border border-slate-100 rounded-2xl px-5 outline-none focus:ring-4 focus:ring-rose-50 focus:border-rose-200 transition-all font-bold text-slate-800">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-300 uppercase tracking-[3px] mb-2">Contact Number *</label>
                <input type="text" name="contact_no" required placeholder="For alumni to reach you" class="w-full h-14 bg-slate-50 border border-slate-100 rounded-2xl px-5 outline-none focus:ring-4 focus:ring-rose-50 focus:border-rose-200 transition-all font-bold text-slate-800">
            </div>
            <div>
                <label class="block text-[10px] font-black text-slate-300 uppercase tracking-[3px] mb-2">Description *</label>
                <textarea name="description" rows="3" required class="w-full bg-slate-50 border border-slate-100 rounded-3xl px-5 py-4 outline-none focus:ring-4 focus:ring-rose-50 focus:border-rose-200 transition-all font-bold text-slate-800 resize-none"></textarea>
            </div>
            
            <div class="col-span-2">
                <label class="block text-[10px] font-black text-slate-300 uppercase tracking-[3px] mb-2">Pin Business Location (Optional)</label>
                <div id="map" class="h-60 rounded-3xl border-4 border-slate-50 overflow-hidden shadow-inner bg-slate-100"></div>
                <p class="text-[10px] font-medium text-slate-400 mt-2 italic">Click the map to drop a pin on your enterprise headquarters.</p>
                <input type="hidden" name="latitude" id="lat">
                <input type="hidden" name="longitude" id="lng">
            </div>
            
            <div class="flex gap-4 pt-4">
                <button type="submit" class="flex-1 h-14 bg-rose-500 text-white rounded-2xl font-black shadow-lg shadow-rose-200 hover:bg-rose-600 transition-all tracking-widest">PUBLISH LISTING</button>
                <button type="button" onclick="document.getElementById('regBizModal').classList.remove('open')" class="flex-1 h-14 bg-slate-100 text-slate-600 rounded-2xl font-black hover:bg-slate-200 transition-all">CANCEL</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.getElementById('regBizModal').addEventListener('click', e=> { if(e.target===e.currentTarget) e.currentTarget.classList.remove('open'); });

    // Initialize Map
    let map, marker;
    function initMap() {
        if (map) return;
        // Default to Lyceum Alabang area or PH
        map = L.map('map').setView([14.4081, 121.0415], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        map.on('click', function(e) {
            const { lat, lng } = e.latlng;
            if (marker) map.removeLayer(marker);
            marker = L.marker([lat, lng]).addTo(map);
            document.getElementById('lat').value = lat;
            document.getElementById('lng').value = lng;
        });
    }

    // Lazy load map when modal opens
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.target.classList.contains('open')) {
                setTimeout(initMap, 100);
            }
        });
    });
    observer.observe(document.getElementById('regBizModal'), { attributes: true, attributeFilter: ['class'] });
</script>
</body>
</html>
