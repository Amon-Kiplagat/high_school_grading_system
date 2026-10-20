<?php
/**
 * config/database.php
 * ---------------------------------------------------------------------------
 * Secure PDO connection to PostgreSQL for Green Valley SRMS.
 *
 * Credentials are read from a .env file (or real environment variables) so that
 * no sensitive values are hard-coded here or exposed to HTML/JavaScript.
 *
 * Returns a shared PDO instance via db() so every page reuses one connection.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

/**
 * Minimal .env loader (no external dependencies).
 * Populates getenv()/$_ENV with KEY=VALUE pairs from the given file, once.
 */
function load_env(string $path): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    if (!is_readable($path)) {
        return; // Fall back to real environment variables if no .env file.
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue; // skip blank lines and comments
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Strip an inline comment that is not inside quotes.
        if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
            $value = preg_split('/\s+#/', $value)[0];
            $value = trim($value);
        }
        // Strip surrounding quotes if present.
        if (strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"')
             || ($value[0] === "'" && substr($value, -1) === "'"))) {
            $value = substr($value, 1, -1);
        }

        if (getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

/**
 * Read a config value from the environment, with an optional default.
 */
function env(string $key, ?string $default = null): ?string
{
    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

/**
 * Return the shared PDO connection (created on first call).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Load .env from the project root (one level up from /config).
    load_env(dirname(__DIR__) . '/.env');

    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '5432');
    $name = env('DB_NAME', 'green_valley');
    $user = env('DB_USER', 'postgres');
    $pass = env('DB_PASS', '');

    $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $name);

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // throw on error
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // assoc arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                   // real prepared stmts
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Do not leak credentials/details in production.
        if (env('APP_ENV', 'production') === 'development') {
            die('Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        http_response_code(500);
        die('A database error occurred. Please contact the administrator.');
    }

    return $pdo;
}
