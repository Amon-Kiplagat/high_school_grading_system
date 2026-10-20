<?php
/**
 * includes/sidebar.php
 * ---------------------------------------------------------------------------
 * Left navigation (redesigned). Links are unchanged; they still point at the
 * .php pages and the active page is highlighted via $navClass(). Nav is now
 * grouped (Overview / Records / Assessment) with icons, and the signed-in
 * user + logout sit in the sidebar footer.
 * ---------------------------------------------------------------------------
 */

$current = basename($_SERVER['PHP_SELF'] ?? '');

/** Helper: mark a nav link active when it matches the current page. */
$navClass = static function (string $file) use ($current): string {
    return $file === $current ? 'active' : '';
};

/** Inline SVG icon for a nav item (stroked, inherits currentColor). */
$navIcon = static function (string $key): string {
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'students'  => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'teachers'  => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1 2.7 3 6 3s6-2 6-3v-5"/>',
        'classes'   => '<path d="M3 21h18"/><path d="M5 21V8l7-4 7 4v13"/><path d="M10 21v-6h4v6"/>',
        'subjects'  => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
        'exams'     => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V2.8h6V4"/><path d="M9 11h6M9 15h4"/>',
        'results'   => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5Z"/>',
        'reports'   => '<path d="M3 3v18h18"/><path d="M7 15v3M12 10v8M17 6v12"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
    ];
    $inner = $paths[$key] ?? '';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $inner . '</svg>';
};

/** Nav structure: group label => [ file => [label, icon-key] ]. */
$navGroups = [
    'Overview' => [
        'dashboard.php' => ['Dashboard', 'dashboard'],
    ],
    'Records' => [
        'students.php' => ['Students', 'students'],
        'teachers.php' => ['Teachers', 'teachers'],
        'classes.php'  => ['Classes',  'classes'],
        'subjects.php' => ['Subjects', 'subjects'],
    ],
    'Assessment' => [
        'exams.php'   => ['Exams',   'exams'],
        'results.php' => ['Results', 'results'],
        'reports.php' => ['Reports', 'reports'],
    ],
];

$loggedIn = function_exists('is_logged_in') && is_logged_in();
$userName = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
$userRole = function_exists('current_role') ? (string)(current_role() ?? '') : '';
?>
<aside class="sidebar" id="sidebar">
    <div class="brand">
        <div class="brand-crest" aria-hidden="true">GV</div>
        <div class="brand-name">Green Valley<span>Records System</span></div>
    </div>

    <nav class="nav" aria-label="Main navigation">
        <?php foreach ($navGroups as $label => $links): ?>
            <div class="nav-group">
                <div class="nav-label"><?= e($label) ?></div>
                <?php foreach ($links as $file => [$text, $icon]): ?>
                    <a class="<?= $navClass($file) ?>" href="<?= e($file) ?>"<?= $navClass($file) ? ' aria-current="page"' : '' ?>>
                        <?= $navIcon($icon) ?><span><?= e($text) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <?php if ($loggedIn): ?>
        <div class="side-foot">
            <div class="side-user">
                <b><?= e($userName) ?></b>
                <?php if ($userRole !== ''): ?><small><?= e(ucfirst($userRole)) ?></small><?php endif; ?>
            </div>
            <a class="logout-btn" href="logout.php"><?= $navIcon('logout') ?><span>Sign out</span></a>
        </div>
    <?php else: ?>
        <div class="side-foot">
            <a class="logout-btn" href="login.php"><?= $navIcon('logout') ?><span>Sign in</span></a>
        </div>
    <?php endif; ?>
</aside>
