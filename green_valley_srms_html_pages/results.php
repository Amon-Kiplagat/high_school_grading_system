<?php
/**
 * results.php
 * ---------------------------------------------------------------------------
 * Marks / results interface. Authorised users (admin or teacher) select a
 * student, subject and examination, enter marks, and the grade is calculated
 * automatically. Duplicate (student, subject, exam) combinations are blocked.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();   // both admins and teachers may record marks

$pdo = db();

// Dropdown data.
$students = $pdo->query('SELECT student_id, admission_no, full_name FROM students ORDER BY full_name')->fetchAll();
$subjects = $pdo->query('SELECT subject_id, subject_name FROM subjects ORDER BY subject_name')->fetchAll();
$exams    = $pdo->query('SELECT exam_id, exam_name, term, year FROM exams ORDER BY year DESC, term')->fetchAll();

// Edit mode: load an existing result.
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM results WHERE result_id = :id');
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editing = $stmt->fetch();
    if (!$editing) { set_flash('error', 'Result not found.'); redirect('results.php'); }
}

// Optional filter by exam for the list.
$filterExam = (int)input('exam_id_filter');

$sql = "SELECT r.result_id, r.marks, r.grade,
               s.full_name AS student, s.admission_no,
               sub.subject_name AS subject,
               e.exam_name, e.term, e.year
          FROM results r
          JOIN students s   ON s.student_id = r.student_id
          JOIN subjects sub ON sub.subject_id = r.subject_id
          JOIN exams e      ON e.exam_id = r.exam_id";
$params = [];
if ($filterExam > 0) {
    $sql .= " WHERE r.exam_id = :ex";
    $params[':ex'] = $filterExam;
}
$sql .= " ORDER BY e.year DESC, e.term, s.full_name, sub.subject_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

$page_title = 'Results';
$page_subtitle = 'Enter and edit marks; grades are calculated automatically';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h2><?= $editing ? 'Edit Marks' : 'Enter Marks' ?></h2>
    <form action="actions/result_actions.php" method="post" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="result_id" value="<?= e($editing['result_id']) ?>"><?php endif; ?>

        <div><label>Student</label>
            <select name="student_id" required>
                <option value="">-- Select Student --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= e($s['student_id']) ?>"
                        <?= (($editing['student_id'] ?? '') == $s['student_id']) ? 'selected' : '' ?>>
                        <?= e($s['admission_no'] . ' - ' . $s['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select></div>

        <div><label>Examination</label>
            <select name="exam_id" required>
                <option value="">-- Select Exam --</option>
                <?php foreach ($exams as $ex): ?>
                    <option value="<?= e($ex['exam_id']) ?>"
                        <?= (($editing['exam_id'] ?? '') == $ex['exam_id']) ? 'selected' : '' ?>>
                        <?= e($ex['exam_name'] . ' - ' . $ex['term'] . ' ' . $ex['year']) ?>
                    </option>
                <?php endforeach; ?>
            </select></div>

        <div><label>Subject</label>
            <select name="subject_id" required>
                <option value="">-- Select Subject --</option>
                <?php foreach ($subjects as $sub): ?>
                    <option value="<?= e($sub['subject_id']) ?>"
                        <?= (($editing['subject_id'] ?? '') == $sub['subject_id']) ? 'selected' : '' ?>>
                        <?= e($sub['subject_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select></div>

        <div><label>Marks (0-100)</label>
            <input type="number" name="marks" min="0" max="100" step="0.01"
                   value="<?= e($editing['marks'] ?? '') ?>" required></div>

        <div><label>Grade (auto)</label>
            <input type="text" id="grade-preview" value="<?= e($editing['grade'] ?? '') ?>" readonly></div>

        <div style="align-self:end;">
            <button type="submit"><?= $editing ? 'Update Marks' : 'Save Marks' ?></button>
            <?php if ($editing): ?><a href="results.php" class="btn-sm" style="margin-left:8px;">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h2>Recorded Marks</h2>
    <form action="results.php" method="get" class="searchbar">
        <div><label>Filter by examination</label>
            <select name="exam_id_filter">
                <option value="0">-- All examinations --</option>
                <?php foreach ($exams as $ex): ?>
                    <option value="<?= e($ex['exam_id']) ?>" <?= ($filterExam == $ex['exam_id']) ? 'selected' : '' ?>>
                        <?= e($ex['exam_name'] . ' - ' . $ex['term'] . ' ' . $ex['year']) ?>
                    </option>
                <?php endforeach; ?>
            </select></div>
        <div><button type="submit">Filter</button>
            <?php if ($filterExam > 0): ?><a href="results.php" class="btn-sm">Clear</a><?php endif; ?></div>
    </form>

    <table>
        <tr><th>Student</th><th>Exam</th><th>Subject</th><th>Marks</th><th>Grade</th><th>Action</th></tr>
        <?php if ($results): ?>
            <?php foreach ($results as $r): ?>
                <tr>
                    <td><?= e($r['admission_no'] . ' - ' . $r['student']) ?></td>
                    <td><?= e($r['exam_name'] . ' - ' . $r['term'] . ' ' . $r['year']) ?></td>
                    <td><?= e($r['subject']) ?></td>
                    <td><?= e(rtrim(rtrim((string)$r['marks'], '0'), '.')) ?></td>
                    <td><span class="grade"><?= e($r['grade']) ?></span></td>
                    <td class="actions">
                        <a href="results.php?edit=<?= e($r['result_id']) ?>">Edit</a>
                        <?php if (is_admin()): ?>
                            <a class="btn-danger"
                               href="actions/result_actions.php?action=delete&result_id=<?= e($r['result_id']) ?>&csrf_token=<?= e(csrf_token()) ?>"
                               data-confirm="Delete this mark?">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6">No marks recorded yet.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
