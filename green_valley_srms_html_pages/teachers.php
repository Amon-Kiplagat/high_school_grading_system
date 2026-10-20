<?php
/**
 * teachers.php
 * ---------------------------------------------------------------------------
 * Teacher management: add, view, search, edit, delete. Adding/editing/deleting
 * teachers is restricted to administrators; everyone signed in can view.
 * The "Subjects" column is derived live from the subjects table. Replaces
 * teachers.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();

// If editing, load the teacher.
$editing = null;
if (isset($_GET['edit']) && is_admin()) {
    $stmt = $pdo->prepare('SELECT * FROM teachers WHERE teacher_id = :id');
    $stmt->execute([':id' => (int)$_GET['edit']]);
    $editing = $stmt->fetch();
    if (!$editing) {
        set_flash('error', 'Teacher not found.');
        redirect('teachers.php');
    }
}

$search = input('q');

// List teachers with the subjects each one teaches (string_agg).
$sql = "SELECT t.teacher_id, t.staff_no, t.full_name, t.gender, t.phone, t.email,
               COALESCE(string_agg(s.subject_name, ', ' ORDER BY s.subject_name), '') AS subjects
          FROM teachers t
          LEFT JOIN subjects s ON s.teacher_id = t.teacher_id";
$params = [];
if ($search !== '') {
    $sql .= " WHERE t.full_name ILIKE :q OR t.staff_no ILIKE :q OR t.email ILIKE :q";
    $params[':q'] = '%' . $search . '%';
}
$sql .= " GROUP BY t.teacher_id ORDER BY t.full_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

$page_title = 'Teachers';
$page_subtitle = 'Manage teaching staff and the subjects they teach';
require_once __DIR__ . '/includes/header.php';
?>

<?php if (is_admin()): ?>
<div class="card">
    <h2><?= $editing ? 'Edit Teacher' : 'Add Teacher' ?></h2>
    <form action="actions/teacher_actions.php" method="post" data-validate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?>
            <input type="hidden" name="teacher_id" value="<?= e($editing['teacher_id']) ?>">
        <?php endif; ?>

        <div><label>Teacher ID / Staff No</label>
            <input type="text" name="staff_no" value="<?= e($editing['staff_no'] ?? '') ?>" required></div>
        <div><label>Full Name</label>
            <input type="text" name="full_name" value="<?= e($editing['full_name'] ?? '') ?>" required></div>
        <div><label>Gender</label>
            <select name="gender">
                <?php foreach (['Male', 'Female'] as $g): ?>
                    <option value="<?= $g ?>" <?= (($editing['gender'] ?? '') === $g) ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select></div>
        <div><label>Phone</label>
            <input type="tel" name="phone" value="<?= e($editing['phone'] ?? '') ?>"
                   inputmode="numeric"
                   autocomplete="tel"
                   maxlength="16"
                   pattern="[0-9+][0-9 ().+-]{7,18}"
                   placeholder="0712345678"
                   title="Digits only: 10 digits starting 07 or 01, or the +254 form."
                   data-phone>
            <small class="field-hint">Digits only &mdash; e.g. 0712345678 or +254712345678.</small></div>
        <div><label>Email</label>
            <input type="email" name="email" value="<?= e($editing['email'] ?? '') ?>"></div>
        <div style="align-self:end;">
            <button type="submit"><?= $editing ? 'Update Teacher' : 'Save Teacher' ?></button>
            <?php if ($editing): ?><a href="teachers.php" class="btn-sm" style="margin-left:8px;">Cancel</a><?php endif; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card">
    <h2>Teacher List</h2>
    <form action="teachers.php" method="get" class="searchbar">
        <div><label>Search (name, staff no. or email)</label>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="e.g. Mwangi or T001"></div>
        <div><button type="submit">Search</button>
            <?php if ($search !== ''): ?><a href="teachers.php" class="btn-sm">Clear</a><?php endif; ?></div>
    </form>

    <table>
        <tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Subjects</th><th>Action</th></tr>
        <?php if ($teachers): ?>
            <?php foreach ($teachers as $t): ?>
                <tr>
                    <td><?= e($t['staff_no']) ?></td>
                    <td><?= e($t['full_name']) ?></td>
                    <td><?= e(format_phone_display($t['phone'] ?? '')) ?></td>
                    <td><?= e($t['email'] ?? '') ?></td>
                    <td><?= e($t['subjects'] !== '' ? $t['subjects'] : '—') ?></td>
                    <td class="actions">
                        <?php if (is_admin()): ?>
                            <a href="teachers.php?edit=<?= e($t['teacher_id']) ?>">Edit</a>
                            <a class="btn-danger"
                               href="actions/teacher_actions.php?action=delete&teacher_id=<?= e($t['teacher_id']) ?>&csrf_token=<?= e(csrf_token()) ?>"
                               data-confirm="Delete <?= e($t['full_name']) ?>?">Delete</a>
                        <?php else: ?>
                            <span>—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="6">No teachers found.</td></tr>
        <?php endif; ?>
    </table>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
