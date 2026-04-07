<?php
// includes/helpers.php

/**
 * Generate Avatar Initials and Color
 * @param string $name
 * @return array
 */
function getAvatar($name) {
    if (empty(trim($name))) {
        return ['initials' => 'U', 'color' => '#6b7280'];
    }
    
    $parts = explode(' ', trim($name));
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (count($parts) > 1) {
        $initials .= strtoupper(substr(end($parts), 0, 1));
    }
    
    $colors = [
        '#2563eb', // blue
        '#16a34a', // green
        '#d97706', // amber
        '#9333ea', // purple
        '#dc2626', // red
        '#0891b2', // teal
        '#db2777', // pink
        '#4b5563'  // gray
    ];
    
    // Consistent color based on name hash
    $colorIndex = array_sum(array_map('ord', str_split($name))) % count($colors);
    $color = $colors[$colorIndex];
    
    return ['initials' => $initials, 'color' => $color];
}

/**
 * Render Avatar Circle or Image
 */
function renderAvatar($name, $size = 'w-10 h-10', $profile_pic = null) {
    if (!empty($profile_pic)) {
        // Path check (uploads are stored relative to root)
        $path = __DIR__ . '/../' . $profile_pic;
        if (file_exists($path)) {
            return sprintf(
                '<img src="/ALUMNI-main/%s" class="%s rounded-full object-cover shadow-sm border border-slate-200 dark:border-slate-800" alt="%s">',
                htmlspecialchars($profile_pic),
                $size,
                htmlspecialchars($name)
            );
        }
    }

    $avatar = getAvatar($name);
    return sprintf(
        '<div class="%s rounded-full flex items-center justify-center text-white font-bold shadow-sm" style="background-color: %s;">%s</div>',
        $size,
        $avatar['color'],
        $avatar['initials']
    );
}

/**
 * Format Date — null-safe
 */
function formatDate($date): string {
    if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') return '—';
    $ts = strtotime($date);
    if ($ts === false || $ts <= 0) return '—';
    return date('M d, Y', $ts);
}

/**
 * Format DateTime — null-safe
 */
function formatDateTime($date): string {
    if (empty($date) || $date === '0000-00-00 00:00:00') return '—';
    $ts = strtotime($date);
    if ($ts === false || $ts <= 0) return '—';
    return date('M d, Y g:i A', $ts);
}

/**
 * Time Ago — human-readable relative time
 */
function timeAgo($date): string {
    if (empty($date)) return '—';
    $diff = time() - strtotime($date);
    if ($diff < 60)   return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return formatDate($date);
}

/**
 * Generate CSRF Token
 */
function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF Token
 */
function verifyCsrf(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * CSRF Hidden Input HTML
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

/**
 * Log a system event for auditing
 */
function logAudit(string $action, string $details = '', string $type = '', int $target_id = 0): void {
    global $pdo;
    $uid = $_SESSION['user_id'] ?? 0;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, target_type, target_id, ip_address) VALUES (?,?,?,?,?,?)");
    $stmt->execute([$uid, $action, $details, $type, $target_id, $ip]);
}

/**
 * Generate a unique Alumni ID Number (LOA-YYYY-XXXXX)
 */
function generateAlumniId($year, $id): string {
    return "LOA-" . $year . "-" . str_pad($id, 5, '0', STR_PAD_LEFT);
}
