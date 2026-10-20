<?php
/**
 * dashboard.php
 * ---------------------------------------------------------------------------
 * Landing page after login. Shows live counts pulled from PostgreSQL, the
 * activity trail (who was last in the system and what they did), and the most
 * recent marks entered. Replaces dashboard.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();

/** Small helper to fetch a single COUNT(*) value. */
$count = static function (string $table) use ($pdo): int {
    // Table name is a hard-coded literal (never user input), so it is safe here.
    return (int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
};

$totalStudents = $count('students');
$totalTeachers = $count('teachers');
$totalClasses  = $count('classes');
$totalSubjects = $count('subjects');
$totalExams    = $count('exams');

// Most recent marks entered (real data, joined across tables).
$recent = $pdo->query(
    "SELECT s.full_name AS student, sub.subject_name AS subject,
            e.exam_name, e.term, e.year, r.marks, r.grade, r.created_at
       FROM results r
       JOIN students s   ON s.student_id = r.student_id
       JOIN subjects sub ON sub.subject_id = r.subject_id
       JOIN exams e      ON e.exam_id = r.exam_id
      ORDER BY r.created_at DESC
      LIMIT 5"
)->fetchAll();

/* ---------------------------------------------------------------------------
 * Activity trail: who was last in the system and what they did.
 *
 * Wrapped in a try/catch because the activity_log table is newer than the rest
 * of the schema - if the migration has not been run yet the dashboard should
 * still load and simply explain what to do.
 * ------------------------------------------------------------------------ */
$activity        = [];
$lastAccess      = null;
$activityMissing = false;

try {
    $activity = $pdo->query(
        "SELECT username, full_name, role, action, entity, entity_label,
                ip_address, created_at
           FROM activity_log
          ORDER BY created_at DESC, log_id DESC
          LIMIT 8"
    )->fetchAll();

    // The newest row is the "last person to access the system".
    $lastAccess = $activity[0] ?? null;
} catch (PDOException $e) {
    $activityMissing = true;
}

$page_title = 'Dashboard';
$page_subtitle = 'Record counts, who was last in the system, and the latest marks entered';
require_once __DIR__ . '/includes/header.php';
?>

<div class="cards">
    <div class="stat"><h3>Total Students</h3><p><?= e($totalStudents) ?></p></div>
    <div class="stat"><h3>Total Teachers</h3><p><?= e($totalTeachers) ?></p></div>
    <div class="stat"><h3>Total Classes</h3><p><?= e($totalClasses) ?></p></div>
    <div class="stat"><h3>Total Subjects</h3><p><?= e($totalSubjects) ?></p></div>
    <div class="stat"><h3>Total Examinations</h3><p><?= e($totalExams) ?></p></div>
</div>

<div class="card">
    <h2>System Activity</h2>

    <?php if ($activityMissing): ?>
        <p>The activity log table has not been created yet. Run
            <code>sql/migration_2026_10_activity_log_and_phone.sql</code>
            against the <strong>green_valley</strong> database to switch it on.</p>

    <?php elseif ($lastAccess): ?>
        <!-- The headline: who touched the system most recently, and what they did. -->
        <div class="last-access">
            <span class="last-access__avatar" aria-hidden="true"><?=
                e(name_initial($lastAccess['full_name']))
            ?></span>
            <div class="last-access__body">
                <p class="last-access__label">Last person to access the system</p>
                <p class="last-access__who">
                    <strong><?= e($lastAccess['full_name']) ?></strong>
                    <span class="role-tag role-tag--<?= e($lastAccess['role'] ?? 'none') ?>"><?=
                        e($lastAccess['role'] ?? 'unknown')
                    ?></span>
                </p>
                <p class="last-access__what"><?= e(activity_sentence($lastAccess)) ?></p>
            </div>
            <div class="last-access__when">
                <strong><?= e(time_ago($lastAccess['created_at'])) ?></strong>
                <span><?= e(format_when($lastAccess['created_at'])) ?></span>
            </div>
        </div>

        <table>
            <tr><th>User</th><th>Role</th><th>Action</th><th>Details</th><th>When</th></tr>
            <?php foreach ($activity as $a): ?>
                <tr>
                    <td><?= e($a['full_name']) ?><br><small class="muted-note">@<?= e($a['username']) ?></small></td>
                    <td><span class="role-tag role-tag--<?= e($a['role'] ?? 'none') ?>"><?= e($a['role'] ?? '—') ?></span></td>
                    <td>
                        <span class="act act--<?= e(strtolower(str_replace(['-', ' '], '', $a['action']))) ?>">
                            <?= e($a['action']) ?>
                        </span>
                        <?php if (!empty($a['entity'])): ?>
                            <span class="muted-note"><?= e($a['entity']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($a['entity_label'] ?? '—') ?></td>
                    <td><?= e(time_ago($a['created_at'])) ?><br>
                        <small class="muted-note"><?= e(format_when($a['created_at'], 'j M, H:i')) ?></small></td>
                </tr>
            <?php endforeach; ?>
        </table>

    <?php else: ?>
        <p>No activity recorded yet. Sign-ins and every change made to records will appear here.</p>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Recently Entered Marks</h2>
    <?php if ($recent): ?>
        <table>
            <tr><th>Student</th><th>Subject</th><th>Examination</th><th>Marks</th><th>Grade</th></tr>
            <?php foreach ($recent as $row): ?>
                <tr>
                    <td><?= e($row['student']) ?></td>
                    <td><?= e($row['subject']) ?></td>
                    <td><?= e($row['exam_name'] . ' - ' . $row['term'] . ' ' . $row['year']) ?></td>
                    <td><?= e(rtrim(rtrim((string)$row['marks'], '0'), '.')) ?></td>
                    <td><span class="grade"><?= e($row['grade']) ?></span></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No marks have been entered yet. Go to <a href="results.php">Results</a> to add some.</p>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
