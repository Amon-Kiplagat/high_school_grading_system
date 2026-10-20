<?php
/**
 * actions/exam_actions.php
 * ---------------------------------------------------------------------------
 * Create / update / delete examinations (admin only). An exam is unique on
 * (exam_name, term, year).
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo    = db();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

function exam_input(): array
{
    $year = (int)input('year');
    return [
        'exam_name' => input('exam_name'),
        'term'      => in_array(input('term'), ['Term 1', 'Term 2', 'Term 3'], true) ? input('term') : 'Term 1',
        'year'      => $year,
    ];
}

try {
    if ($action === 'create' || $action === 'update') {
        verify_csrf();
        $data = exam_input();

        if ($data['exam_name'] === '') {
            set_flash('error', 'Exam name is required.');
            redirect('../exams.php');
        }
        if ($data['year'] < 2000 || $data['year'] > 2100) {
            set_flash('error', 'Please enter a valid year (2000-2100).');
            redirect('../exams.php');
        }

        if ($action === 'create') {
            $stmt = $pdo->prepare(
                'INSERT INTO exams (exam_name, term, year) VALUES (:exam_name, :term, :year)'
            );
            $stmt->execute($data);
            log_activity('Added', 'Examination',
                $data['exam_name'] . ' - ' . $data['term'] . ' ' . $data['year']);
            set_flash('success', 'Examination added successfully.');
            redirect('../exams.php');
        }

        $id = (int)($_POST['exam_id'] ?? 0);
        if ($id <= 0) { set_flash('error', 'Invalid examination.'); redirect('../exams.php'); }
        $data['exam_id'] = $id;
        $stmt = $pdo->prepare(
            'UPDATE exams SET exam_name = :exam_name, term = :term, year = :year
             WHERE exam_id = :exam_id'
        );
        $stmt->execute($data);
        log_activity('Updated', 'Examination',
            $data['exam_name'] . ' - ' . $data['term'] . ' ' . $data['year']);
        set_flash('success', 'Examination updated successfully.');
        redirect('../exams.php');
    }

    if ($action === 'delete') {
        verify_csrf();
        $id = (int)($_GET['exam_id'] ?? 0);

        // Capture the details before they disappear, for the audit trail.
        $look = $pdo->prepare('SELECT exam_name, term, year FROM exams WHERE exam_id = :id');
        $look->execute([':id' => $id]);
        $gone = $look->fetch();

        // results.exam_id uses ON DELETE CASCADE - related marks are removed.
        $stmt = $pdo->prepare('DELETE FROM exams WHERE exam_id = :id');
        $stmt->execute([':id' => $id]);
        log_activity('Deleted', 'Examination', $gone
            ? $gone['exam_name'] . ' - ' . $gone['term'] . ' ' . $gone['year'] . ' (and its marks)'
            : 'record #' . $id);
        set_flash('success', 'Examination deleted.');
        redirect('../exams.php');
    }

    set_flash('error', 'Unknown action.');
    redirect('../exams.php');

} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        set_flash('error', 'An examination with that name, term and year already exists.');
    } else {
        set_flash('error', 'Database error: ' . $e->getMessage());
    }
    redirect('../exams.php');
}
