<?php
/**
 * includes/auth.php
 * ---------------------------------------------------------------------------
 * Session bootstrap + role-based access control.
 *
 * Include this at the very TOP of every protected page:
 *     require_once __DIR__ . '/includes/auth.php';
 *     require_login();               // any logged-in user
 *     require_role('admin');         // admins only (optional)
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

// Harden session cookies before starting the session.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,   // JS cannot read the session cookie
        'samesite' => 'Lax',  // basic CSRF hardening
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    ]);
    session_start();
}

/**
 * True if a user is currently logged in.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * The current user's role ('admin' | 'teacher') or null.
 */
function current_role(): ?string
{
    return $_SESSION['role'] ?? null;
}

/**
 * True if the logged-in user is an administrator.
 */
function is_admin(): bool
{
    return current_role() === 'admin';
}

/**
 * Block access unless a user is logged in. Redirects to login otherwise.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

/**
 * Restrict a page/action to one or more roles.
 * Example: require_role('admin');
 */
function require_role(string ...$roles): void
{
    require_login();
    if (!in_array(current_role(), $roles, true)) {
        http_response_code(403);
        set_flash('error', 'You do not have permission to perform that action.');
        redirect('dashboard.php');
    }
}

/**
 * Log a user in from a matched users row (sets session state).
 * Regenerates the session id to prevent fixation.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['user_id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role']      = $user['role'];
}

/**
 * Log the current user out and destroy the session.
 */
function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
