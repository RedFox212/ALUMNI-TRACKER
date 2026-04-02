<?php
// includes/auth.php
require_once __DIR__ . '/../config.php';

define('SESSION_TIMEOUT', 1800); // 30 minutes

/**
 * Check if user is logged in (with idle timeout)
 */
function isLoggedIn(): bool {
    if (!isset($_SESSION['user_id'])) return false;
    // Idle timeout check
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Redirect to login if not authenticated
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . (str_contains($_SERVER['PHP_SELF'], '/pages/') ? '../index.php' : 'index.php'));
        exit;
    }
}

/**
 * Check for specific role
 */
function requireRole(string $role): void {
    requireLogin();
    if ($_SESSION['user_role'] !== $role) {
        header('Location: dashboard.php?error=unauthorized');
        exit;
    }
}

/**
 * Handle Login
 */
function handleLogin(string $email, string $password, PDO $pdo): array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        if (!$user['is_active']) return ['success' => false, 'message' => 'Account is disabled. Contact the admin.'];

        // Regenerate session ID on login (prevents session fixation)
        session_regenerate_id(true);

        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_name']     = $user['name'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['user_role']     = $user['role'];
        $_SESSION['last_activity'] = time();

        // Check verification for alumni
        if ($user['role'] === 'alumni') {
            $stmt = $pdo->prepare("SELECT verification_status FROM alumni WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $alumni = $stmt->fetch();
            $_SESSION['verification_status'] = $alumni['verification_status'] ?? 'pending';
        } else {
            $_SESSION['verification_status'] = 'verified'; // Admins
        }

        return ['success' => true, 'first_login' => (bool)$user['first_login']];
    }
    return ['success' => false, 'message' => 'Invalid email or password.'];
}

/**
 * Handle Logout — clean session destruction
 */
function handleLogout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
    header('Location: index.php');
    exit;
}

