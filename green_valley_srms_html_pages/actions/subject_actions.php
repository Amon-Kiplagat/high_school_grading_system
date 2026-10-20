<?php
/**
 * actions/subject_actions.php
 * ---------------------------------------------------------------------------
 * Create / update / delete subjects (admin only). Assigns a teacher via
 * subjects.teacher_id -> teachers.teacher_id.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo    = db();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function subject_code_exists(PDO $pdo, string $code, ?int $excludeId = null): bool
{
    $sql = 'SELECT 1 FROM subjects WHERE subject_code = :c';
    $p   = [':c' => $code];
    if ($excludeId !== null) { $sql .= ' AND subject_id <> :id'; $p[':id'] = $excludeId; }
    $st = $pdo->prepare($sql); $st->execute($p);
    return (bool)$st->fetchColumn();
}

function subject_input(): array
{
    $teacherId = input('teacher_id');
    return [
        'subject_code' => input('subject_code'),
        'subject_name' => input('subject_name'),
        'teacher_id'   => $teacherId !== '' ? (int)$teacherId : null,
    ];
}

try {
    if ($action === 'create' || $action === 'update') {
        verify_csrf();
        $data = subject_input();

        if ($data['subject_code'] === '' || $data['subject_name'] === '') {
            set_flash('error', 'Subject code and name are required.');
            redirect('../subjects.php');
        }

        if ($action === 'create') {
            if (subject_code_exists($pdo, $data['subject_code'])) {
                set_flash('error', 'Subject code "' . $data['subject_code'] . '" already exists.');
                redirect('../subjects.php');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO subjects (subject_code, subject_name, teacher_id)
                 VALUES (:subject_code, :subject_name, :teacher_id)'
            );
            $stmt->execute($data);
            log_activity('Added', 'Subject', $data['subject_name'] . ' (' . $data['subject_code'] . ')');
            set_flash('success', 'Subject added successfully.');
            redirect('../subjects.php');
        }

        $id = (int)($_POST['subject_id'] ?? 0);
        if ($id <= 0) { set_flash('error', 'Invalid subject.'); redirect('../subjects.php'); }
        if (subject_code_exists($pdo, $data['subject_code'], $id)) {
            set_flash('error', 'Another subject already uses code "' . $data['subject_code'] . '".');
            redirect('../subjects.php');
        }
        $data['subject_id'] = $id;
        $stmt = $pdo->prepare(
            'UPDATE subjects SET subject_code = :subject_code, subject_name = :subject_name,
                    teacher_id = :teacher_id
             WHERE subject_id = :subject_id'
        );
        $stmt->execute($data);
        log_activity('Updated', 'Subject', $data['subject_name'] . ' (' . $data['subject_code'] . ')');
        set_flash('success', 'Subject updated successfully.');
        redirect('../subjects.php');
    }

    if ($action === 'delete') {
        verify_csrf();
        $id = (int)($_GET['subject_id'] ?? 0);

        // Capture the name before it disappears, for the audit trail.
        $look = $pdo->prepare('SELECT subject_name, subject_code FROM subjects WHERE subject_id = :id');
        $look->execute([':id' => $id]);
        $gone = $look->fetch();

        $stmt = $pdo->prepare('DELETE FROM subjects WHERE subject_id = :id');
        $stmt->execute([':id' => $id]);
        log_activity('Deleted', 'Subject', $gone
            ? $gone['subject_name'] . ' (' . $gone['subject_code'] . ')'
            : 'record #' . $id);
        set_flash('success', 'Subject deleted.');
        redirect('../subjects.php');
    }

    set_flash('error', 'Unknown action.');
    redirect('../subjects.php');

} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        set_flash('error', 'That subject code is already in use.');
    } else {
        set_flash('error', 'Database error: ' . $e->getMessage());
    }
    redirect('../subjects.php');
}
