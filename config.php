<?php
// config.php
// Lyceum Alumni Tracking System Configuration

// Database Settings (Standard XAMPP)
define('DB_HOST', 'localhost');
define('DB_NAME', 'lats_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// System Settings
define('SITE_URL', 'http://localhost/ALUMNI-main');
define('SYSTEM_NAME', 'LATS - Lyceum Alumni Tracking System');
define('DEVELOPMENT_MODE', false); // Set to true for debugging

// Load Security Engine
require_once __DIR__ . '/includes/security.php';

// Session config
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
