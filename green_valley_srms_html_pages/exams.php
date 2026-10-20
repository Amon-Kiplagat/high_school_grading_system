<?php
/**
 * exams.php
 * ---------------------------------------------------------------------------
 * Examination management: add, view, edit, delete. Stores exam name, term and
 * year (unique together). Admin-only management. Replaces exams.html.
 * (Marks entry lives on results.php.)
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();

$editing = null;
if (isset($_GET['edit']) && is_admin()) {
    $stmt = $pdo->prepare('SELECT * FROM exams WHERE exam_id = :id');
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editing = $stmt->fetch();
    if (!$editing) { set_flash('error', 'Examination not found.'); redirect('exams.php'); }
}

$exams = $pdo->query(
    "SELECT e.exam_id, e.exam_name, e.term, e.year,
            (SELECT COUNT(*) FROM results r WHERE r.exam_id = e.exam_id) AS result_count
       FROM exams e
      ORDER BY e.year DESC, e.term, e.exam_name"
)->fetchAll();

$terms  = ['Term 1', 'Term 2', 'Term 3'];
$page_title = 'Exams';
$page_subtitle = 'Set up examinations by term and year';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (is_admin()): ?>
<div class="card">
    <h2><?= $editing ? 'Edit Examination' : 'Add Examination' ?></h2>
    <form action="actions/exam_actions.php" method="post" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="exam_id" value="<?= e($editing['exam_id']) ?>"><?php endif; ?>

        <div><label>Exam Name</label>
            <input type="text" name="exam_name" placeholder="e.g. End-Term"
                   value="<?= e($editing['exam_name'] ?? '') ?>" required></div>
        <div><label>Term</label>
            <select name="term">
                <?php foreach ($terms as $t): ?>
                    <option value="<?= $t ?>" <?= (($editing['term'] ?? '') === $t) ? 'selected' : '' ?>><?= $t ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><label>Year</label>
            <input type="number" name="year" min="2000" max="2100"
                   value="<?= e($editing['year'] ?? date('Y')) ?>" required></div>
        <div style="align-self:end;">
            <button type="submit"><?= $editing ? 'Update Exam' : 'Save Exam' ?></button>
            <?php if ($editing): ?><a href="exams.php" class="btn-sm" style="margin-left:8px;">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Examination List</h2>
    <table>
        <tr><th>Exam Name</th><th>Term</th><th>Year</th><th>Results Entered</th><th>Action</th></tr>
        <?php if ($exams): ?>
            <?php foreach ($exams as $ex): ?>
                <tr>
                    <td><?= e($ex['exam_name']) ?></td>
                    <td><?= e($ex['term']) ?></td>
                    <td><?= e($ex['year']) ?></td>
                    <td><?= e($ex['result_count']) ?></td>
                    <td class="actions">
                        <?php if (is_admin()): ?>
                            <a href="exams.php?edit=<?= e($ex['exam_id']) ?>">Edit</a>
                            <a class="btn-danger"
                               href="actions/exam_actions.php?action=delete&exam_id=<?= e($ex['exam_id']) ?>&csrf_token=<?= e(csrf_token()) ?>"
                               data-confirm="Delete this exam and all its marks?">Delete</a>
                        <?php else: ?><span>—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5">No examinations found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
