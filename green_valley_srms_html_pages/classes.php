<?php
/**
 * classes.php
 * ---------------------------------------------------------------------------
 * Class management: add, view, edit, delete, and assign a class teacher
 * (classes.teacher_id -> teachers.teacher_id). Admin-only management.
 * Replaces classes.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();

$teachers = $pdo->query('SELECT teacher_id, full_name, staff_no FROM teachers ORDER BY full_name')->fetchAll();

$editing = null;
if (isset($_GET['edit']) && is_admin()) {
    $stmt = $pdo->prepare('SELECT * FROM classes WHERE class_id = :id');
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editing = $stmt->fetch();
    if (!$editing) { set_flash('error', 'Class not found.'); redirect('classes.php'); }
}

// List classes with the assigned teacher and a live student count.
$classes = $pdo->query(
    "SELECT c.class_id, c.class_name, c.room,
            t.full_name AS teacher_name,
            (SELECT COUNT(*) FROM students s WHERE s.class_id = c.class_id) AS student_count
       FROM classes c
       LEFT JOIN teachers t ON t.teacher_id = c.teacher_id
      ORDER BY c.class_name"
)->fetchAll();

$page_title = 'Classes';
$page_subtitle = 'Manage classes and assign class teachers';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (is_admin()): ?>
<div class="card">
    <h2><?= $editing ? 'Edit Class' : 'Add Class' ?></h2>
    <form action="actions/class_actions.php" method="post" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="class_id" value="<?= e($editing['class_id']) ?>"><?php endif; ?>

        <div><label>Class Name</label>
            <input type="text" name="class_name" placeholder="e.g. Form 1 East"
                   value="<?= e($editing['class_name'] ?? '') ?>" required></div>
        <div><label>Class Teacher</label>
            <select name="teacher_id">
                <option value="">-- Unassigned --</option>
                <?php foreach ($teachers as $t): ?>
                    <option value="<?= e($t['teacher_id']) ?>"
                        <?= (($editing['teacher_id'] ?? '') == $t['teacher_id']) ? 'selected' : '' ?>>
                        <?= e($t['full_name'] . ' (' . $t['staff_no'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select></div>
        <div><label>Room</label>
            <input type="text" name="room" value="<?= e($editing['room'] ?? '') ?>"></div>
        <div style="align-self:end;">
            <button type="submit"><?= $editing ? 'Update Class' : 'Save Class' ?></button>
            <?php if ($editing): ?><a href="classes.php" class="btn-sm" style="margin-left:8px;">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Class List</h2>
    <table>
        <tr><th>Class Name</th><th>Class Teacher</th><th>Room</th><th>Students</th><th>Action</th></tr>
        <?php if ($classes): ?>
            <?php foreach ($classes as $c): ?>
                <tr>
                    <td><?= e($c['class_name']) ?></td>
                    <td><?= e($c['teacher_name'] ?? '—') ?></td>
                    <td><?= e($c['room'] ?? '') ?></td>
                    <td><?= e($c['student_count']) ?></td>
                    <td class="actions">
                        <?php if (is_admin()): ?>
                            <a href="classes.php?edit=<?= e($c['class_id']) ?>">Edit</a>
                            <a class="btn-danger"
                               href="actions/class_actions.php?action=delete&class_id=<?= e($c['class_id']) ?>&csrf_token=<?= e(csrf_token()) ?>"
                               data-confirm="Delete class <?= e($c['class_name']) ?>?">Delete</a>
                        <?php else: ?><span>—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5">No classes found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
