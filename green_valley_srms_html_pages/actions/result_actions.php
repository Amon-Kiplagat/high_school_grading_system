<?php
/**
 * actions/result_actions.php
 * ---------------------------------------------------------------------------
 * Create / update / delete marks. The grade is calculated automatically from
 * the marks. A student cannot have two marks for the same subject in the same
 * examination (checked here AND enforced by the uq_result constraint).
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_login();   // admins and teachers may record marks

$pdo    = db();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

/** Does a mark already exist for this student+subject+exam (excluding one id)? */
function result_exists(PDO $pdo, int $studentId, int $subjectId, int $examId, ?int $excludeId = null): bool
{
    $sql = 'SELECT 1 FROM results
             WHERE student_id = :st AND subject_id = :su AND exam_id = :ex';
    $p = [':st' => $studentId, ':su' => $subjectId, ':ex' => $examId];
    if ($excludeId !== null) { $sql .= ' AND result_id <> :id'; $p[':id'] = $excludeId; }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($p);
    return (bool)$stmt->fetchColumn();
}

/**
 * Build a readable label for the audit trail, e.g.
 * "Mary Wanjiku - Mathematics, Mid-Term Term 1 2026".
 */
function result_label(PDO $pdo, int $studentId, int $subjectId, int $examId): string
{
    $stmt = $pdo->prepare(
        'SELECT s.full_name, sub.subject_name, e.exam_name, e.term, e.year
           FROM students s, subjects sub, exams e
          WHERE s.student_id = :st AND sub.subject_id = :su AND e.exam_id = :ex'
    );
    $stmt->execute([':st' => $studentId, ':su' => $subjectId, ':ex' => $examId]);
    $r = $stmt->fetch();
    if (!$r) {
        return 'student #' . $studentId . ', subject #' . $subjectId;
    }
    return $r['full_name'] . ' - ' . $r['subject_name'] . ', '
         . $r['exam_name'] . ' ' . $r['term'] . ' ' . $r['year'];
}

try {
    if ($action === 'create' || $action === 'update') {
        verify_csrf();

        $studentId = (int)input('student_id');
        $subjectId = (int)input('subject_id');
        $examId    = (int)input('exam_id');
        $marksRaw  = input('marks');

        // ---- Validation ----
        if ($studentId <= 0 || $subjectId <= 0 || $examId <= 0 || $marksRaw === '') {
            set_flash('error', 'Please select a student, exam, subject and enter marks.');
            redirect('../results.php');
        }
        if (!is_numeric($marksRaw)) {
            set_flash('error', 'Marks must be a number.');
            redirect('../results.php');
        }
        $marks = (float)$marksRaw;
        if ($marks < 0 || $marks > 100) {
            set_flash('error', 'Marks must be between 0 and 100.');
            redirect('../results.php');
        }

        $grade = calculate_grade($marks);   // automatic grade

        if ($action === 'create') {
            if (result_exists($pdo, $studentId, $subjectId, $examId)) {
                set_flash('error', 'A mark for this student, subject and examination already exists. Edit it instead.');
                redirect('../results.php');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO results (student_id, subject_id, exam_id, marks, grade)
                 VALUES (:student_id, :subject_id, :exam_id, :marks, :grade)'
            );
            $stmt->execute([
                ':student_id' => $studentId, ':subject_id' => $subjectId,
                ':exam_id' => $examId, ':marks' => $marks, ':grade' => $grade,
            ]);
            log_activity('Entered', 'Marks',
                result_label($pdo, $studentId, $subjectId, $examId) . ' = ' . $marks . ' (' . $grade . ')');
            set_flash('success', 'Marks saved. Grade: ' . $grade . '.');
            redirect('../results.php');
        }

        // update
        $id = (int)($_POST['result_id'] ?? 0);
        if ($id <= 0) { set_flash('error', 'Invalid result.'); redirect('../results.php'); }
        if (result_exists($pdo, $studentId, $subjectId, $examId, $id)) {
            set_flash('error', 'Another mark for this student, subject and examination already exists.');
            redirect('../results.php');
        }
        $stmt = $pdo->prepare(
            'UPDATE results SET student_id = :student_id, subject_id = :subject_id,
                    exam_id = :exam_id, marks = :marks, grade = :grade
             WHERE result_id = :id'
        );
        $stmt->execute([
            ':student_id' => $studentId, ':subject_id' => $subjectId,
            ':exam_id' => $examId, ':marks' => $marks, ':grade' => $grade, ':id' => $id,
        ]);
        log_activity('Updated', 'Marks',
            result_label($pdo, $studentId, $subjectId, $examId) . ' = ' . $marks . ' (' . $grade . ')');
        set_flash('success', 'Marks updated. Grade: ' . $grade . '.');
        redirect('../results.php');
    }

    if ($action === 'delete') {
        require_role('admin');
        verify_csrf();
        $id = (int)($_GET['result_id'] ?? 0);

        // Capture what is being removed before it goes, for the audit trail.
        $look = $pdo->prepare(
            'SELECT s.full_name, sub.subject_name, e.exam_name, e.term, e.year, r.marks, r.grade
               FROM results r
               JOIN students s   ON s.student_id = r.student_id
               JOIN subjects sub ON sub.subject_id = r.subject_id
               JOIN exams e      ON e.exam_id = r.exam_id
              WHERE r.result_id = :id'
        );
        $look->execute([':id' => $id]);
        $gone = $look->fetch();

        $stmt = $pdo->prepare('DELETE FROM results WHERE result_id = :id');
        $stmt->execute([':id' => $id]);
        log_activity('Deleted', 'Marks', $gone
            ? $gone['full_name'] . ' - ' . $gone['subject_name'] . ', '
              . $gone['exam_name'] . ' ' . $gone['term'] . ' ' . $gone['year']
              . ' = ' . rtrim(rtrim((string)$gone['marks'], '0'), '.') . ' (' . $gone['grade'] . ')'
            : 'record #' . $id);
        set_flash('success', 'Mark deleted.');
        redirect('../results.php');
    }

    set_flash('error', 'Unknown action.');
    redirect('../results.php');

} catch (PDOException $e) {
    if ($e->getCode() === '23505') {
        set_flash('error', 'That student already has a mark for this subject and examination.');
    } else {
        set_flash('error', 'Database error: ' . $e->getMessage());
    }
    redirect('../results.php');
}
