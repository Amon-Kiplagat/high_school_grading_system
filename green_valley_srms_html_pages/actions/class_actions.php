<?php
/**
 * actions/class_actions.php
 * ---------------------------------------------------------------------------
 * Create / update / delete classes (admin only). Assigns a class teacher via
 * classes.teacher_id -> teachers.teacher_id.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo    = db();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function class_input(): array
{
    $teacherId = input('teacher_id');
    return [
        'class_name' => input('class_name'),
        'teacher_id' => $teacherId !== '' ? (int)$teacherId : null,
        'room'       => input('room') ?: null,
    ];
}

try {
    if ($action === 'create' || $action === 'update') {
        verify_csrf();
        $data = class_input();

        if ($data['class_name'] === '') {
            set_flash('error', 'Class name is required.');
            redirect('../classes.php');
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO classes (class_name, teacher_id, room)
                 VALUES (:class_name, :teacher_id, :room)'
            );
            $stmt->execute($data);
            log_activity('Added', 'Class', $data['class_name']);
            set_flash('success', 'Class added successfully.');
            redirect('../classes.php');
        }

        $id = (int)($_POST['class_id'] ?? 0);
        if ($id <= 0) { set_flash('error', 'Invalid class.'); redirect('../classes.php'); }
        $data['class_id'] = $id;
        $stmt = $pdo->prepare(
            'UPDATE classes SET class_name = :class_name, teacher_id = :teacher_id, room = :room
             WHERE class_id = :class_id'
        );
        $stmt->execute($data);
        log_activity('Updated', 'Class', $data['class_name']);
        set_flash('success', 'Class updated successfully.');
        redirect('../classes.php');
    }

    if ($action === 'delete') {
        verify_csrf();
        $id = (int)($_GET['class_id'] ?? 0);

        // Capture the name before it disappears, for the audit trail.
        $look = $pdo->prepare('SELECT class_name FROM classes WHERE class_id = :id');
        $look->execute([':id' => $id]);
        $gone = $look->fetchColumn();

        // students.class_id uses ON DELETE SET NULL, so students are kept.
        $stmt = $pdo->prepare('DELETE FROM classes WHERE class_id = :id');
        $stmt->execute([':id' => $id]);
        log_activity('Deleted', 'Class', $gone !== false ? (string)$gone : 'record #' . $id);
        set_flash('success', 'Class deleted.');
        redirect('../classes.php');
    }

    set_flash('error', 'Unknown action.');
    redirect('../classes.php');

} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        set_flash('error', 'A class with that name already exists.');
    } else {
        set_flash('error', 'Database error: ' . $e->getMessage());
    }
    redirect('../classes.php');
}
