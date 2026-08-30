<?php

/**
 * Session bootstrap + authentication helpers.
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    $isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isSecure,
        'samesite' => 'Lax'
    ]);
    session_start();
}


/**
 * Require the user to be logged in.
 *
 * Usage:
 * require_login();
 * require_login(['admin']);
 * require_login(['doctor', 'admin']);
 */
function require_login(array $allowedRoles = []): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        redirect('/login.php');
    }

    // Session idle timeout: 30 minutes (1800 seconds)
    $maxIdleTime = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $maxIdleTime)) {
        logout_user();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        set_flash('error', 'Your session has expired due to inactivity. Please log in again.');
        redirect('/login.php');
    }
    $_SESSION['last_activity'] = time();

    $stmt = getDB()->prepare('SELECT role, status, force_password_change FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active' || $user['role'] !== $_SESSION['role']) {
        logout_user();
        redirect('/login.php');
    }

    $_SESSION['force_password_change'] = (bool)$user['force_password_change'];

    if (!empty($allowedRoles)
        && !in_array($_SESSION['role'], $allowedRoles, true)
    ) {
        http_response_code(403);
        die('You do not have permission to view this page.');
    }

    /*
     * If the user logged in with a temporary password,
     * restrict access until they change their password.
     */
    if (!empty($_SESSION['force_password_change'])) {
        $currentPage = basename($_SERVER['PHP_SELF']);

        if ($currentPage !== 'change-password.php') {
            redirect('/change-password.php');
        }
    }
}


/**
 * Get the currently logged-in user's ID.
 */
function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}


/**
 * Get the currently logged-in user's role.
 */
function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}


/**
 * Check whether the logged-in user must change their password.
 */
function must_change_password(): bool
{
    return !empty($_SESSION['force_password_change']);
}


/**
 * Create a user login session.
 */
function login_user(
    int $userId,
    string $role,
    bool $forcePasswordChange = false
): void {
    // Prevent session fixation.
    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    $_SESSION['force_password_change'] = $forcePasswordChange;
    $_SESSION['last_activity'] = time();
}


/**
 * Destroy the current user session.
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}