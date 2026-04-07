<?php
/**
 * LATS - Global Security Hardening Engine
 * Implements industry-standard protections against common web vulnerabilities.
 */

// 1. Force Secure Session Environment
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');

// If using HTTPS (production), force secure cookies
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

// 2. Anti-Exploit Security Headers
header("X-Frame-Options: SAMEORIGIN"); // Prevent Clickjacking
header("X-Content-Type-Options: nosniff"); // Prevent MIME sniffing
header("X-XSS-Protection: 1; mode=block"); // Modern XSS Filter
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Permissions-Policy: geolocation=(), camera=(), microphone=()"); // Privacy

// 3. Sanitization Engine (Recursive)
function sanitizeInput(&$data) {
    if (is_array($data)) {
        foreach ($data as $key => &$value) {
            sanitizeInput($value);
        }
    } else {
        // Prevent Null Byte Injection
        $data = str_replace(chr(0), '', $data);
        // Basic trim
        $data = trim($data);
    }
}
sanitizeInput($_GET);
sanitizeInput($_POST);
sanitizeInput($_COOKIE);

// 4. Global POST CSRF Enforcement (Strict Mode)
// This will auto-verify CSRF for all POST requests if is_post() helper is used or manually checked.
function globalCsrfGuard() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
            http_response_code(403);
            die("SECURITY ALERT: CSRF Token Validation Failed. Potential cross-site request forgery detected.");
        }
    }
}

// 5. Production Error Masking
// Ensure sensitive server paths are never leaked to the browser.
if (defined('DEVELOPMENT_MODE') && !DEVELOPMENT_MODE) {
    error_reporting(0);
    ini_set('display_errors', 0);
}
