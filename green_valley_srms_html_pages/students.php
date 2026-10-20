<?php
/**
 * students.php
 * ---------------------------------------------------------------------------
 * Student management: add, view, search, edit, delete. Joins the classes
 * table so each student's class is shown/selected. Replaces students.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();

// Class list for the dropdown.
$classes = $pdo->query('SELECT class_id, class_name FROM classes ORDER BY class_name')->fetchAll();

// If editing, load the student being edited.
$editing = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM students WHERE student_id = :id');
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        set_flash('error', 'Student not found.');
        redirect('students.php');
    }
}

// Search term (safe: used only as a bound ILIKE parameter).
$search = input('q');

$sql = "SELECT s.student_id, s.admission_no, s.full_name, s.gender,
               s.date_of_birth, s.guardian_name, s.guardian_phone,
               c.class_name
          FROM students s
          LEFT JOIN classes c ON c.class_id = s.class_id";
$params = [];
if ($search !== '') {
    $sql .= " WHERE s.admission_no ILIKE :q OR s.full_name ILIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$sql .= " ORDER BY s.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

$page_title = 'Students';
$page_subtitle = 'Add, search and manage student records';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h2><?= $editing ? 'Edit Student' : 'Add Student' ?></h2>
    <form action="actions/student_actions.php" method="post" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="student_id" value="<?= e($editing['student_id']) ?>">
        <?php endif; ?>

        <div>
            <label>Admission Number</label>
            <input type="text" name="admission_no"
                   value="<?= e($editing['admission_no'] ?? '') ?>" required>
        </div>
        <div>
            <label>Full Name</label>
            <input type="text" name="full_name"
                   value="<?= e($editing['full_name'] ?? '') ?>" required>
        </div>
        <div>
            <label>Gender</label>
            <select name="gender">
                <?php foreach (['Male', 'Female'] as $g): ?>
                    <option value="<?= $g ?>" <?= (($editing['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Date of Birth</label>
            <input type="date" name="date_of_birth" value="<?= e($editing['date_of_birth'] ?? '') ?>">
        </div>
        <div>
            <label>Class</label>
            <select name="class_id">
                <option value="">-- Select Class --</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= e($c['class_id']) ?>"
                        <?= (($editing['class_id'] ?? '') == $c['class_id']) ? 'selected' : '' ?>>
                        <?= e($c['class_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label>Parent/Guardian</label>
            <input type="text" name="guardian_name" value="<?= e($editing['guardian_name'] ?? '') ?>">
        </div>
        <div>
            <label>Phone Number</label>
            <input type="tel" name="guardian_phone"
                   value="<?= e($editing['guardian_phone'] ?? '') ?>"
                   inputmode="numeric"
                   autocomplete="tel"
                   maxlength="16"
                   pattern="[0-9+][0-9 ().+-]{7,18}"
                   placeholder="0712345678"
                   title="Digits only: 10 digits starting 07 or 01, or the +254 form."
                   data-phone>
            <small class="field-hint">Digits only &mdash; e.g. 0712345678 or +254712345678.</small>
        </div>
        <div style="align-self:end;">
            <button type="submit"><?= $editing ? 'Update Student' : 'Save Student' ?></button>
            <?php if ($editing): ?>
                <a href="students.php" class="btn-sm" style="margin-left:8px;">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<div class="card">
    <h2>Student List</h2>
    <form action="students.php" method="get" class="searchbar">
        <div>
            <label>Search (name or admission no.)</label>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="e.g. Mary or GV001">
        </div>
        <div><button type="submit">Search</button>
            <?php if ($search !== ''): ?><a href="students.php" class="btn-sm">Clear</a><?php endif; ?>
        </div>
    </form>

    <table>
        <tr><th>Adm No.</th><th>Name</th><th>Gender</th><th>Class</th><th>Parent Phone</th><th>Action</th></tr>
        <?php if ($students): ?>
            <?php foreach ($students as $s): ?>
                <tr>
                    <td><?= e($s['admission_no']) ?></td>
                    <td><?= e($s['full_name']) ?></td>
                    <td><?= e($s['gender']) ?></td>
                    <td><?= e($s['class_name'] ?? '—') ?></td>
                    <td><?= e(format_phone_display($s['guardian_phone'] ?? '')) ?></td>
                    <td class="actions">
                        <a href="students.php?edit=<?= e($s['student_id']) ?>">Edit</a>
                        <?php if (is_admin()): ?>
                            <a class="btn-danger"
                               href="actions/student_actions.php?action=delete&student_id=<?= e($s['student_id']) ?>&csrf_token=<?= e(csrf_token()) ?>"
                               data-confirm="Delete <?= e($s['full_name']) ?>? This cannot be undone.">Delete</a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6">No students found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
