<?php
/**
 * login.php
 * ---------------------------------------------------------------------------
 * Authenticates a user against the `users` table using password_verify(),
 * starts a session, and redirects to dashboard.php. Replaces login.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

// Already signed in? Go straight to the dashboard.
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $username = input('username');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        // Parameterised query - no SQL injection possible.
        $stmt = db()->prepare(
            'SELECT user_id, username, password_hash, full_name, role, is_active
               FROM users
              WHERE username = :u
              LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $user = $stmt->fetch();

        // Verify the hash produced by PHP password_hash().
        if ($user && $user['is_active'] && password_verify($password, $user['password_hash'])) {

            // Transparently upgrade the hash if PHP's default cost changed.
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $new = password_hash($password, PASSWORD_DEFAULT);
                $up  = db()->prepare('UPDATE users SET password_hash = :h WHERE user_id = :id');
                $up->execute([':h' => $new, ':id' => $user['user_id']]);
            }

            login_user($user);
            log_activity('Signed in', 'Session', null);
            set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
            redirect('dashboard.php');
        }

        // Wrong password, unknown username, or a deactivated account. Record
        // the attempt so an admin can spot someone trying to get in, but never
        // reveal which part was wrong to the person at the keyboard.
        log_activity(
            'Failed sign-in',
            'Session',
            ($user && !$user['is_active']) ? 'account is deactivated' : null,
            [
                'user_id'   => $user['user_id'] ?? null,
                'username'  => $username,
                'full_name' => $user['full_name'] ?? 'Unknown user',
                'role'      => $user['role'] ?? null,
            ]
        );

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login - Green Valley SRMS</title>
    <link rel="stylesheet" href="css/style.css">

    <!-- Progressive Web App tags, so the app is installable/offline-aware from
         the very first screen (the login page is the start page when signed out). -->
    <meta name="theme-color" content="#1f5138">
    <link rel="manifest" href="manifest.webmanifest">
    <link rel="icon" href="icons/favicon-32.png" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GV Records">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-crest" aria-hidden="true">GV</div>
        <h2>Green Valley SRMS</h2>
        <p>Sign in to the records system</p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="post" data-validate class="inline">
            <?= csrf_field() ?>
            <label for="username">Username</label>
            <input type="text" id="username" name="username"
                   value="<?= e($_POST['username'] ?? '') ?>"
                   placeholder="Enter username" required>

            <label for="password">Password</label>
            <input type="password" id="password" name="password"
                   placeholder="Enter password" required>

            <button type="submit">Sign in</button>
        </form>
    </div>
    <script src="js/script.js"></script>
    <script src="js/pwa.js" defer></script>
</body>
</html>
