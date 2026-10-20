<?php
/**
 * create_admin.php
 * ---------------------------------------------------------------------------
 * One-off utility to create or reset a login account with a properly hashed
 * password (uses PHP password_hash(), so it always matches password_verify()).
 *
 * Two ways to run it:
 *
 *  1) Command line (recommended):
 *       php create_admin.php <username> <password> <full name> <role>
 *     e.g.
 *       php create_admin.php admin "StrongPass#1" "System Administrator" admin
 *       php create_admin.php jmwangi "Teach#123" "John Mwangi" teacher
 *
 *  2) Browser: open create_admin.php?setup=1 ONCE to create the default
 *     admin/admin123 account, then DELETE this file.
 *
 *  role must be 'admin' or 'teacher'. If the username exists, its password,
 *  name, and role are updated.
 *
 *  >>> DELETE THIS FILE after you have created your accounts. <<<
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';

/** Insert or update a user with a hashed password. */
function upsert_user(PDO $pdo, string $username, string $password, string $fullName, string $role): void
{
    if (!in_array($role, ['admin', 'teacher'], true)) {
        throw new InvalidArgumentException("role must be 'admin' or 'teacher'");
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $sql = 'INSERT INTO users (username, password_hash, full_name, role)
            VALUES (:u, :h, :n, :r)
            ON CONFLICT (username)
            DO UPDATE SET password_hash = EXCLUDED.password_hash,
                          full_name     = EXCLUDED.full_name,
                          role          = EXCLUDED.role,
                          is_active     = TRUE';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':u' => $username, ':h' => $hash, ':n' => $fullName, ':r' => $role]);
}

$pdo = db();

if (PHP_SAPI === 'cli') {
    // ---- CLI mode ----
    $args = $argv;
    array_shift($args); // drop script name
    if (count($args) < 4) {
        fwrite(STDERR, "Usage: php create_admin.php <username> <password> <full name> <role>\n");
        exit(1);
    }
    [$username, $password, $fullName, $role] = $args;
    upsert_user($pdo, $username, $password, $fullName, $role);
    echo "User '{$username}' ({$role}) created/updated successfully.\n";
    echo "Remember to DELETE create_admin.php now.\n";
    exit(0);
}

// ---- Browser mode ----
header('Content-Type: text/plain; charset=utf-8');
if (($_GET['setup'] ?? '') === '1') {
    upsert_user($pdo, 'admin', 'admin123', 'System Administrator', 'admin');
    echo "Default admin created: username 'admin', password 'admin123'.\n";
    echo "Log in, change the password, then DELETE this file (create_admin.php).\n";
} else {
    echo "create_admin.php\n\n";
    echo "Open create_admin.php?setup=1 to create the default admin (admin/admin123),\n";
    echo "or run from the command line:\n";
    echo "  php create_admin.php <username> <password> \"Full Name\" <admin|teacher>\n\n";
    echo "DELETE this file once your accounts exist.\n";
}
