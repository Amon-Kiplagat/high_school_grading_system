<?php
/**
 * actions/teacher_actions.php
 * ---------------------------------------------------------------------------
 * Create / update / delete teachers (admin only). PRG pattern.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');   // all teacher management is admin-only

$pdo    = db();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/** Is this staff number already used (optionally excluding one id)? */
function staff_no_exists(PDO $pdo, string $staffNo, ?int $excludeId = null): bool
{
    $sql = 'SELECT 1 FROM teachers WHERE staff_no = :s';
    $p   = [':s' => $staffNo];
    if ($excludeId !== null) { $sql .= ' AND teacher_id <> :id'; $p[':id'] = $excludeId; }
    $st = $pdo->prepare($sql); $st->execute($p);
    return (bool)$st->fetchColumn();
}

function teacher_input(): array
{
    return [
        'staff_no'  => input('staff_no'),
        'full_name' => input('full_name'),
        'gender'    => in_array(input('gender'), ['Male', 'Female'], true) ? input('gender') : 'Male',
        // Set separately below, after normalize_phone() has validated it.
        'phone'     => null,
        'email'     => input('email') ?: null,
    ];
}

try {
    if ($action === 'create' || $action === 'update') {
        verify_csrf();
        $data = teacher_input();

        if ($data['staff_no'] === '' || $data['full_name'] === '') {
            set_flash('error', 'Staff number and full name are required.');
            redirect('../teachers.php');
        }
        if ($data['email'] !== null && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            set_flash('error', 'Please enter a valid email address.');
            redirect('../teachers.php');
        }

        // Phone: digits only, same rule as the parent/guardian number.
        $phone = normalize_phone(input('phone'));
        if (!$phone['ok']) {
            set_flash('error', 'Phone: ' . $phone['error']);
            redirect('../teachers.php');
        }
        $data['phone'] = $phone['value'];

        if ($action === 'create') {
            if (staff_no_exists($pdo, $data['staff_no'])) {
                set_flash('error', 'Staff number "' . $data['staff_no'] . '" already exists.');
                redirect('../teachers.php');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO teachers (staff_no, full_name, gender, phone, email)
                 VALUES (:staff_no, :full_name, :gender, :phone, :email)'
            );
            $stmt->execute($data);
            log_activity('Added', 'Teacher', $data['full_name'] . ' (' . $data['staff_no'] . ')');
            set_flash('success', 'Teacher added successfully.');
            redirect('../teachers.php');
        }

        $id = (int)($_POST['teacher_id'] ?? 0);
        if ($id <= 0) { set_flash('error', 'Invalid teacher.'); redirect('../teachers.php'); }
        if (staff_no_exists($pdo, $data['staff_no'], $id)) {
            set_flash('error', 'Another teacher already uses staff number "' . $data['staff_no'] . '".');
            redirect('../teachers.php');
        }
        $data['teacher_id'] = $id;
        $stmt = $pdo->prepare(
            'UPDATE teachers SET staff_no = :staff_no, full_name = :full_name,
                    gender = :gender, phone = :phone, email = :email
             WHERE teacher_id = :teacher_id'
        );
        $stmt->execute($data);
        log_activity('Updated', 'Teacher', $data['full_name'] . ' (' . $data['staff_no'] . ')');
        set_flash('success', 'Teacher updated successfully.');
        redirect('../teachers.php');
    }

    if ($action === 'delete') {
        verify_csrf();
        $id = (int)($_GET['teacher_id'] ?? 0);

        // Capture the name before it disappears, for the audit trail.
        $look = $pdo->prepare('SELECT full_name, staff_no FROM teachers WHERE teacher_id = :id');
        $look->execute([':id' => $id]);
        $gone = $look->fetch();

        // classes.teacher_id / subjects.teacher_id use ON DELETE SET NULL, so
        // deleting a teacher simply unassigns them - no orphan errors.
        $stmt = $pdo->prepare('DELETE FROM teachers WHERE teacher_id = :id');
        $stmt->execute([':id' => $id]);
        log_activity('Deleted', 'Teacher', $gone
            ? $gone['full_name'] . ' (' . $gone['staff_no'] . ')'
            : 'record #' . $id);
        set_flash('success', 'Teacher deleted.');
        redirect('../teachers.php');
    }

    set_flash('error', 'Unknown action.');
    redirect('../teachers.php');

} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        set_flash('error', 'That staff number or email is already in use.');
    } elseif ($e->getCode() === '23514') {
        set_flash('error', 'The phone number must be digits only, e.g. 0712345678.');
    } else {
        set_flash('error', 'Database error: ' . $e->getMessage());
    }
    redirect('../teachers.php');
}
