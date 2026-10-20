<?php
/**
 * actions/student_actions.php
 * ---------------------------------------------------------------------------
 * Handles create / update / delete for students, then redirects back to
 * students.php with a success or error flash message (Post-Redirect-Get).
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();

$pdo    = db();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/** Check whether an admission number is already taken (optionally excluding one id). */
function admission_exists(PDO $pdo, string $admissionNo, ?int $excludeId = null): bool
{
    $sql    = 'SELECT 1 FROM students WHERE admission_no = :a';
    $params = [':a' => $admissionNo];
    if ($excludeId !== null) {
        $sql .= ' AND student_id <> :id';
        $params[':id'] = $excludeId;
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

/** Collect and normalise the student fields from POST. */
function student_input(): array
{
    $classId = input('class_id');
    $dob     = input('date_of_birth');
    return [
        'admission_no'   => input('admission_no'),
        'full_name'      => input('full_name'),
        'gender'         => in_array(input('gender'), ['Male', 'Female'], true) ? input('gender') : 'Male',
        'date_of_birth'  => $dob !== '' ? $dob : null,
        'class_id'       => $classId !== '' ? (int)$classId : null,
        'guardian_name'  => input('guardian_name') ?: null,
        // Set separately below, after normalize_phone() has validated it.
        'guardian_phone' => null,
    ];
}

try {
    if ($action === 'create' || $action === 'update') {
        verify_csrf();
        $data = student_input();

        // ---- Server-side validation ----
        if ($data['admission_no'] === '' || $data['full_name'] === '') {
            set_flash('error', 'Admission number and full name are required.');
            redirect('../students.php');
        }

        // Parent/guardian phone: digits only, stored in one canonical form.
        $phone = normalize_phone(input('guardian_phone'));
        if (!$phone['ok']) {
            set_flash('error', 'Parent/guardian phone: ' . $phone['error']);
            redirect('../students.php');
        }
        $data['guardian_phone'] = $phone['value'];

        if ($action === 'create') {
            if (admission_exists($pdo, $data['admission_no'])) {
                set_flash('error', 'Admission number "' . $data['admission_no'] . '" already exists.');
                redirect('../students.php');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO students
                    (admission_no, full_name, gender, date_of_birth, class_id, guardian_name, guardian_phone)
                 VALUES (:admission_no, :full_name, :gender, :date_of_birth, :class_id, :guardian_name, :guardian_phone)'
            );
            $stmt->execute($data);
            log_activity('Added', 'Student', $data['full_name'] . ' (' . $data['admission_no'] . ')');
            set_flash('success', 'Student added successfully.');
            redirect('../students.php');
        }

        // update
        $id = (int)($_POST['student_id'] ?? 0);
        if ($id <= 0) {
            set_flash('error', 'Invalid student.');
            redirect('../students.php');
        }
        if (admission_exists($pdo, $data['admission_no'], $id)) {
            set_flash('error', 'Another student already uses admission number "' . $data['admission_no'] . '".');
            redirect('../students.php');
        }
        $data['student_id'] = $id;
        $stmt = $pdo->prepare(
            'UPDATE students SET
                admission_no   = :admission_no,
                full_name      = :full_name,
                gender         = :gender,
                date_of_birth  = :date_of_birth,
                class_id       = :class_id,
                guardian_name  = :guardian_name,
                guardian_phone = :guardian_phone
             WHERE student_id = :student_id'
        );
        $stmt->execute($data);
        log_activity('Updated', 'Student', $data['full_name'] . ' (' . $data['admission_no'] . ')');
        set_flash('success', 'Student updated successfully.');
        redirect('../students.php');
    }

    if ($action === 'delete') {
        // Deleting is an admin-only action.
        require_role('admin');
        verify_csrf();
        $id = (int)($_GET['student_id'] ?? 0);

        // Read the name first so the audit trail can say who was removed.
        $look = $pdo->prepare('SELECT full_name, admission_no FROM students WHERE student_id = :id');
        $look->execute([':id' => $id]);
        $gone = $look->fetch();

        $stmt = $pdo->prepare('DELETE FROM students WHERE student_id = :id');
        $stmt->execute([':id' => $id]);
        log_activity('Deleted', 'Student', $gone
            ? $gone['full_name'] . ' (' . $gone['admission_no'] . ')'
            : 'record #' . $id);
        set_flash('success', 'Student deleted.');
        redirect('../students.php');
    }

    // Unknown action
    set_flash('error', 'Unknown action.');
    redirect('../students.php');

} catch (PDOException $e) {
    // 23505 = unique_violation (safety net if a duplicate slips past the check).
    // 23514 = check_violation  (safety net for the phone number format rule).
    if ($e->getCode() === '23505') {
        set_flash('error', 'That admission number is already in use.');
    } elseif ($e->getCode() === '23514') {
        set_flash('error', 'The parent/guardian phone must be digits only, e.g. 0712345678.');
    } else {
        set_flash('error', 'Database error: ' . $e->getMessage());
    }
    redirect('../students.php');
}
