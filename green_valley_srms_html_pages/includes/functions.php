<?php
/**
 * includes/functions.php
 * ---------------------------------------------------------------------------
 * Small helpers shared across the app: output escaping, flash messages,
 * CSRF protection, and the grade calculator.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

/**
 * Escape a value for safe output in HTML (prevents XSS).
 * Use everywhere untrusted/DB data is printed into a page.
 */
function e($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Convert a numeric mark (0-100) into a KCSE 12-point grade.
 * Returns the grade string (A, A-, B+, ... E).
 */
function calculate_grade($marks): string
{
    $m = (float)$marks;
    if ($m < 0)  { $m = 0; }
    if ($m > 100){ $m = 100; }

    return match (true) {
        $m >= 80 => 'A',
        $m >= 75 => 'A-',
        $m >= 70 => 'B+',
        $m >= 65 => 'B',
        $m >= 60 => 'B-',
        $m >= 55 => 'C+',
        $m >= 50 => 'C',
        $m >= 45 => 'C-',
        $m >= 40 => 'D+',
        $m >= 35 => 'D',
        $m >= 30 => 'D-',
        default  => 'E',
    };
}

/* ------------------------------- Flash messages ------------------------- */

/**
 * Queue a one-time message shown on the next page load.
 * $type is 'success' or 'error'.
 */
function set_flash(string $type, string $message): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Pull all queued flash messages and clear them.
 * @return array<int,array{type:string,message:string}>
 */
function get_flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Render queued flash messages as HTML (call once near the top of a page).
 */
function render_flashes(): string
{
    $html = '';
    foreach (get_flashes() as $f) {
        $cls = $f['type'] === 'success' ? 'alert-success' : 'alert-error';
        $html .= '<div class="alert ' . $cls . '">' . e($f['message']) . '</div>';
    }
    return $html;
}

/* ------------------------------- CSRF tokens ---------------------------- */

/**
 * Return the current CSRF token, generating one if needed.
 */
function csrf_token(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden input carrying the CSRF token; drop this inside every POST form.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate the submitted CSRF token. Aborts the request on mismatch.
 */
function verify_csrf(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $sent = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$sent)) {
        http_response_code(400);
        die('Invalid or missing CSRF token. Please go back and try again.');
    }
}

/* ------------------------------- Phone numbers -------------------------- */

/**
 * Validate and normalise a Kenyan phone number.
 *
 * ONLY digits are ever stored. Common separators (spaces, dashes, dots,
 * brackets) are allowed while typing but stripped before saving; letters or
 * any other symbol is rejected outright.
 *
 * Accepted forms, all stored as the canonical 10-digit local form:
 *     0712345678        ->  0712345678
 *     0112 345 678      ->  0112345678
 *     +254 712 345678   ->  0712345678
 *     254712345678      ->  0712345678
 *     00254712345678    ->  0712345678
 *     712345678         ->  0712345678
 *
 * An empty value is valid and stores NULL, because the phone field is optional.
 *
 * @return array{ok:bool, value:?string, error:?string}
 *         ok    - whether the input is acceptable
 *         value - the normalised number (or null when the field was left blank)
 *         error - a message to show the user when ok is false
 */
function normalize_phone(string $raw): array
{
    $raw = trim($raw);

    // Blank is fine - the field is optional.
    if ($raw === '') {
        return ['ok' => true, 'value' => null, 'error' => null];
    }

    // Reject anything that is not a digit or an accepted separator / plus sign.
    if (preg_match('/[^0-9+\s\-().]/', $raw)) {
        return [
            'ok'    => false,
            'value' => null,
            'error' => 'Phone number may contain digits only - no letters or symbols. Example: 0712345678.',
        ];
    }

    // A "+" is only meaningful at the very start (country code).
    if (strpos($raw, '+') !== false && strpos($raw, '+') !== 0) {
        return [
            'ok'    => false,
            'value' => null,
            'error' => 'The "+" may only appear at the start of a phone number. Example: +254712345678.',
        ];
    }

    // Strip every separator so only digits remain.
    $digits = preg_replace('/\D+/', '', $raw);

    if ($digits === '') {
        return [
            'ok'    => false,
            'value' => null,
            'error' => 'Please enter a phone number using digits. Example: 0712345678.',
        ];
    }

    // Peel off the country code, whatever shape it arrived in, to leave the
    // 9-digit subscriber number (e.g. 712345678).
    if (str_starts_with($digits, '00254')) {
        $subscriber = substr($digits, 5);
    } elseif (str_starts_with($digits, '254')) {
        $subscriber = substr($digits, 3);
    } elseif (str_starts_with($digits, '0')) {
        $subscriber = substr($digits, 1);
    } else {
        $subscriber = $digits;
    }

    // Kenyan mobile numbers are 9 subscriber digits beginning 7 (Safaricom /
    // Airtel) or 1 (the newer 011x ranges).
    if (!preg_match('/^[71][0-9]{8}$/', $subscriber)) {
        return [
            'ok'    => false,
            'value' => null,
            'error' => 'Enter a valid Kenyan phone number: 10 digits starting 07 or 01 (e.g. 0712345678), or the +254 form.',
        ];
    }

    return ['ok' => true, 'value' => '0' . $subscriber, 'error' => null];
}

/**
 * Pretty-print a stored phone number for display: 0712345678 -> 0712 345 678.
 * Anything unexpected is returned unchanged so no data is ever hidden.
 */
function format_phone_display(?string $phone): string
{
    $phone = (string)($phone ?? '');
    if (preg_match('/^0([71][0-9]{2})([0-9]{3})([0-9]{3})$/', $phone, $m)) {
        return '0' . $m[1] . ' ' . $m[2] . ' ' . $m[3];
    }
    return $phone;
}

/* ------------------------------- Activity log --------------------------- */

/**
 * Truncate a string to fit a database column.
 *
 * Prefers the multibyte-safe mb_substr() so a name like "Njeri Mũthoni" is not
 * cut through the middle of a character, but falls back to substr() if the
 * mbstring extension is not enabled.
 */
function str_cap(string $value, int $limit): string
{
    return function_exists('mb_substr')
        ? mb_substr($value, 0, $limit)
        : substr($value, 0, $limit);
}

/**
 * First letter of a name, upper-cased, for the dashboard's initial disc.
 * Returns '?' for an empty name so the circle is never blank.
 */
function name_initial(?string $name): string
{
    $name = trim((string)($name ?? ''));
    if ($name === '') {
        return '?';
    }
    return function_exists('mb_strtoupper')
        ? mb_strtoupper(mb_substr($name, 0, 1))
        : strtoupper(substr($name, 0, 1));
}

/**
 * Record one action in the activity_log table (the audit trail shown on the
 * dashboard).
 *
 * The who/what is denormalised on purpose: username, full name and role are
 * copied into the row so the history still reads correctly even if the user
 * account is renamed or deleted later.
 *
 * Logging must never be the reason a save fails, so every error here is
 * swallowed. If the activity_log table has not been created yet the app simply
 * carries on without an audit trail.
 *
 * @param string      $action      Short verb: 'Signed in', 'Added', 'Updated', 'Deleted'...
 * @param string|null $entity      What was touched: 'Student', 'Marks', 'Session'...
 * @param string|null $entityLabel Human detail: 'Mary Wanjiku (GV001)'
 * @param array|null  $actor       Override the signed-in user (used for failed
 *                                 sign-ins, where there is no session yet).
 *                                 Keys: user_id, username, full_name, role.
 */
function log_activity(string $action, ?string $entity = null, ?string $entityLabel = null, ?array $actor = null): void
{
    try {
        if (!function_exists('db')) {
            return;
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $userId   = $actor['user_id']   ?? ($_SESSION['user_id']   ?? null);
        $username = $actor['username']  ?? ($_SESSION['username']  ?? 'unknown');
        $fullName = $actor['full_name'] ?? ($_SESSION['full_name'] ?? 'Unknown user');
        $role     = $actor['role']      ?? ($_SESSION['role']      ?? null);
        $ip       = (string)($_SERVER['REMOTE_ADDR'] ?? '');

        $stmt = db()->prepare(
            'INSERT INTO activity_log
                (user_id, username, full_name, role, action, entity, entity_label, ip_address)
             VALUES
                (:user_id, :username, :full_name, :role, :action, :entity, :entity_label, :ip_address)'
        );
        $stmt->execute([
            ':user_id'      => $userId !== null ? (int)$userId : null,
            ':username'     => str_cap((string)$username, 60),
            ':full_name'    => str_cap((string)$fullName, 120),
            ':role'         => $role,
            ':action'       => str_cap($action, 40),
            ':entity'       => $entity !== null ? str_cap($entity, 40) : null,
            ':entity_label' => $entityLabel !== null ? str_cap($entityLabel, 160) : null,
            ':ip_address'   => $ip !== '' ? str_cap($ip, 45) : null,
        ]);
    } catch (Throwable $e) {
        // Deliberately silent: an audit-trail failure must not block the user.
    }
}

/**
 * Turn one activity_log row into a readable sentence fragment, e.g.
 * "Added Student - Mary Wanjiku (GV001)" or "Signed in".
 */
function activity_sentence(array $row): string
{
    $text = (string)($row['action'] ?? '');
    if (!empty($row['entity'])) {
        $text .= ' ' . $row['entity'];
    }
    if (!empty($row['entity_label'])) {
        $text .= ' - ' . $row['entity_label'];
    }
    return $text;
}

/**
 * Human-friendly elapsed time: "just now", "4 minutes ago", "2 days ago".
 * Accepts anything strtotime() understands (PostgreSQL TIMESTAMPTZ included).
 */
function time_ago(?string $timestamp): string
{
    if (empty($timestamp)) {
        return '';
    }
    $then = strtotime($timestamp);
    if ($then === false) {
        return '';
    }

    $seconds = time() - $then;
    if ($seconds < 0)    { return 'just now'; }   // small clock skew
    if ($seconds < 60)   { return 'just now'; }

    $minutes = (int)floor($seconds / 60);
    if ($minutes < 60)   { return $minutes . ' minute' . ($minutes === 1 ? '' : 's') . ' ago'; }

    $hours = (int)floor($minutes / 60);
    if ($hours < 24)     { return $hours . ' hour' . ($hours === 1 ? '' : 's') . ' ago'; }

    $days = (int)floor($hours / 24);
    if ($days < 30)      { return $days . ' day' . ($days === 1 ? '' : 's') . ' ago'; }

    $months = (int)floor($days / 30);
    if ($months < 12)    { return $months . ' month' . ($months === 1 ? '' : 's') . ' ago'; }

    $years = (int)floor($months / 12);
    return $years . ' year' . ($years === 1 ? '' : 's') . ' ago';
}

/**
 * Format a database timestamp for display, e.g. format_when($ts, 'j M, H:i').
 * Returns an em dash rather than 1 Jan 1970 if the value cannot be parsed.
 */
function format_when(?string $timestamp, string $format = 'D j M Y, H:i'): string
{
    if (empty($timestamp)) {
        return '—';
    }
    $ts = strtotime($timestamp);
    return $ts === false ? '—' : date($format, $ts);
}

/* ------------------------------- Misc helpers --------------------------- */

/**
 * Trimmed string from POST (or GET) with a default.
 */
function input(string $key, string $default = ''): string
{
    $val = $_POST[$key] ?? $_GET[$key] ?? $default;
    return is_string($val) ? trim($val) : $default;
}

/**
 * Redirect helper (adds an optional flash first).
 */
function redirect(string $to): void
{
    header('Location: ' . $to);
    exit;
}
