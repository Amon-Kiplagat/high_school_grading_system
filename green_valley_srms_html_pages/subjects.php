<?php
/**
 * subjects.php
 * ---------------------------------------------------------------------------
 * Subject management: add, view, edit, delete, and assign a teacher
 * (subjects.teacher_id -> teachers.teacher_id). Admin-only management.
 * Replaces subjects.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();

$teachers = $pdo->query('SELECT teacher_id, full_name, staff_no FROM teachers ORDER BY full_name')->fetchAll();

$editing = null;
if (isset($_GET['edit']) && is_admin()) {
    $stmt = $pdo->prepare('SELECT * FROM subjects WHERE subject_id = :id');
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editing = $stmt->fetch();
    if (!$editing) { set_flash('error', 'Subject not found.'); redirect('subjects.php'); }
}

$subjects = $pdo->query(
    "SELECT sub.subject_id, sub.subject_code, sub.subject_name, t.full_name AS teacher_name
       FROM subjects sub
       LEFT JOIN teachers t ON t.teacher_id = sub.teacher_id
      ORDER BY sub.subject_name"
)->fetchAll();

$page_title = 'Subjects';
$page_subtitle = 'Manage subjects and assign teachers';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (is_admin()): ?>
<div class="card">
    <h2><?= $editing ? 'Edit Subject' : 'Add Subject' ?></h2>
    <form action="actions/subject_actions.php" method="post" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="subject_id" value="<?= e($editing['subject_id']) ?>"><?php endif; ?>

        <div><label>Subject Code</label>
            <input type="text" name="subject_code" value="<?= e($editing['subject_code'] ?? '') ?>" required></div>
        <div><label>Subject Name</label>
            <input type="text" name="subject_name" value="<?= e($editing['subject_name'] ?? '') ?>" required></div>
        <div><label>Teacher</label>
            <select name="teacher_id">
                <option value="">-- Unassigned --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= e($t['teacher_id']) ?>"
                        <?= (($editing['teacher_id'] ?? '') == $t['teacher_id']) ? 'selected' : '' ?>>
                        <?= e($t['full_name'] . ' (' . $t['staff_no'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select></div>
        <div style="align-self:end;">
            <button type="submit"><?= $editing ? 'Update Subject' : 'Save Subject' ?></button>
            <?php if ($editing): ?><a href="subjects.php" class="btn-sm" style="margin-left:8px;">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Subject List</h2>
    <table>
        <tr><th>Code</th><th>Subject</th><th>Teacher</th><th>Action</th></tr>
        <?php if ($subjects): ?>
            <?php foreach ($subjects as $s): ?>
                <tr>
                    <td><?= e($s['subject_code']) ?></td>
                    <td><?= e($s['subject_name']) ?></td>
                    <td><?= e($s['teacher_name'] ?? '—') ?></td>
                    <td class="actions">
                        <?php if (is_admin()): ?>
                            <a href="subjects.php?edit=<?= e($s['subject_id']) ?>">Edit</a>
                            <a class="btn-danger"
                               href="actions/subject_actions.php?action=delete&subject_id=<?= e($s['subject_id']) ?>&csrf_token=<?= e(csrf_token()) ?>"
                               data-confirm="Delete subject <?= e($s['subject_name']) ?>?">Delete</a>
                        <?php else: ?><span>—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="4">No subjects found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
