<?php
/**
 * Session bootstrap + auth guard helpers.
 * Include this at the top of every protected page.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Call at the top of any page that requires login.
 * Optionally restrict to one or more roles.
 *
 * Usage:
 *   require_login();                 // any logged-in user
 *   require_login(['admin']);        // admins only
 *   require_login(['doctor','admin']);
 */
function require_login(array $allowedRoles = []): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        redirect('/login.php');
    }

    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        http_response_code(403);
        die('You do not have permission to view this page.');
    }
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

function login_user(int $userId, string $role): void
{
    // Regenerate session ID on privilege change to prevent session fixation.
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}
