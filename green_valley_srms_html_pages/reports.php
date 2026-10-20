<?php
/**
 * reports.php
 * ---------------------------------------------------------------------------
 * Academic reports generated live from the students, subjects, exams and
 * results tables (no duplicated report data is stored). Pick a student and an
 * examination to produce a complete, printable report with subject marks,
 * grades, totals, average and overall grade. Replaces reports.html.
 * ---------------------------------------------------------------------------
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_login();

$pdo = db();

$students = $pdo->query('SELECT student_id, admission_no, full_name FROM students ORDER BY full_name')->fetchAll();
$exams    = $pdo->query('SELECT exam_id, exam_name, term, year FROM exams ORDER BY year DESC, term')->fetchAll();

$studentId = (int)input('student_id');
$examId    = (int)input('exam_id');

$student = null;
$rows    = [];
$summary = ['count' => 0, 'total' => 0.0, 'average' => 0.0, 'grade' => '', 'position' => null, 'class_size' => null];
$examLabel = '';

if ($studentId > 0) {
    // Student header info including class name.
    $stmt = $pdo->prepare(
        "SELECT s.admission_no, s.full_name, s.gender, c.class_name
           FROM students s
           LEFT JOIN classes c ON c.class_id = s.class_id
          WHERE s.student_id = :id"
    );
    $stmt->execute([':id' => $studentId]);
    $student = $stmt->fetch();

    // Marks by subject for the chosen exam (or all exams if none selected).
    $sql = "SELECT sub.subject_name, e.exam_name, e.term, e.year, r.marks, r.grade
              FROM results r
              JOIN subjects sub ON sub.subject_id = r.subject_id
              JOIN exams e      ON e.exam_id = r.exam_id
             WHERE r.student_id = :sid";
    $params = [':sid' => $studentId];
    if ($examId > 0) {
        $sql .= " AND r.exam_id = :eid";
        $params[':eid'] = $examId;
    }
    $sql .= " ORDER BY e.year DESC, e.term, sub.subject_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Summary figures (computed, not stored).
    $summary['count'] = count($rows);
    foreach ($rows as $r) {
        $summary['total'] += (float)$r['marks'];
    }
    if ($summary['count'] > 0) {
        $summary['average'] = $summary['total'] / $summary['count'];
        $summary['grade']   = calculate_grade($summary['average']);
    }

    // Position in class for a specific exam (ranked by average marks).
    if ($examId > 0 && $student) {
        $posStmt = $pdo->prepare(
            "WITH exam_avg AS (
                 SELECT s.student_id, AVG(r.marks) AS avg_marks
                   FROM students s
                   JOIN results r ON r.student_id = s.student_id
                  WHERE r.exam_id = :eid
                    AND s.class_id = (SELECT class_id FROM students WHERE student_id = :sid)
                  GROUP BY s.student_id
             ),
             ranked AS (
                 SELECT student_id,
                        RANK() OVER (ORDER BY avg_marks DESC) AS position,
                        COUNT(*) OVER () AS class_size
                   FROM exam_avg
             )
             SELECT position, class_size FROM ranked WHERE student_id = :sid"
        );
        $posStmt->execute([':eid' => $examId, ':sid' => $studentId]);
        if ($pos = $posStmt->fetch()) {
            $summary['position']   = (int)$pos['position'];
            $summary['class_size'] = (int)$pos['class_size'];
        }
    }

    if ($examId > 0) {
        foreach ($exams as $ex) {
            if ((int)$ex['exam_id'] === $examId) {
                $examLabel = $ex['exam_name'] . ' - ' . $ex['term'] . ' ' . $ex['year'];
            }
        }
    } else {
        $examLabel = 'All Examinations';
    }
}

/** Format a numeric mark without trailing zeros. */
function fmt_marks($m): string
{
    return rtrim(rtrim(number_format((float)$m, 2, '.', ''), '0'), '.');
}

$page_title = 'Reports';
$page_subtitle = 'Generate a printable report card for a student';
require_once __DIR__ . '/includes/header.php';
?>

<div class="card no-print">
    <h2>Generate Report</h2>
    <form action="reports.php" method="get" class="inline"
          style="display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:15px;">
        <div><label>Student</label>
            <select name="student_id" required>
                <option value="">-- Select Student --</option>
                <?php foreach ($students as $s): ?>
                    <option value="<?= e($s['student_id']) ?>" <?= ($studentId == $s['student_id']) ? 'selected' : '' ?>>
                        <?= e($s['admission_no'] . ' - ' . $s['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select></div>
        <div><label>Examination</label>
            <select name="exam_id">
                <option value="0">-- All examinations --</option>
                <?php foreach ($exams as $ex): ?>
                    <option value="<?= e($ex['exam_id']) ?>" <?= ($examId == $ex['exam_id']) ? 'selected' : '' ?>>
                        <?= e($ex['exam_name'] . ' - ' . $ex['term'] . ' ' . $ex['year']) ?>
                    </option>
                <?php endforeach; ?>
            </select></div>
        <div style="align-self:end;"><button type="submit">Generate Report</button></div>
    </form>
</div>

<?php if ($studentId > 0 && $student): ?>
<div class="card">
    <h2>Student Report</h2>
    <p><strong>Student:</strong> <?= e($student['full_name']) ?></p>
    <p><strong>Admission Number:</strong> <?= e($student['admission_no']) ?></p>
    <p><strong>Class:</strong> <?= e($student['class_name'] ?? '—') ?></p>
    <p><strong>Examination:</strong> <?= e($examLabel) ?></p>

    <?php if ($rows): ?>
        <table>
            <tr>
                <th>Subject</th>
                <?php if ($examId === 0): ?><th>Examination</th><?php endif; ?>
                <th>Marks</th><th>Grade</th>
            </tr>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['subject_name']) ?></td>
                    <?php if ($examId === 0): ?>
                        <td><?= e($r['exam_name'] . ' - ' . $r['term'] . ' ' . $r['year']) ?></td>
                    <?php endif; ?>
                    <td><?= e(fmt_marks($r['marks'])) ?></td>
                    <td><span class="grade"><?= e($r['grade']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            <tr>
                <th <?= $examId === 0 ? 'colspan="2"' : '' ?>>Total</th>
                <th><?= e(fmt_marks($summary['total'])) ?></th>
                <th>&nbsp;</th>
            </tr>
        </table>

        <p style="margin-top:14px;">
            <strong>Subjects:</strong> <?= e($summary['count']) ?> &nbsp;|&nbsp;
            <strong>Average:</strong> <?= e(fmt_marks($summary['average'])) ?> &nbsp;|&nbsp;
            <strong>Mean Grade:</strong> <span class="grade"><?= e($summary['grade']) ?></span>
            <?php if ($summary['position'] !== null): ?>
                &nbsp;|&nbsp; <strong>Position in class:</strong>
                <?= e($summary['position']) ?> of <?= e($summary['class_size']) ?>
            <?php endif; ?>
        </p>

        <button class="no-print" onclick="window.print()" style="margin-top:14px;">Print Report</button>
    <?php else: ?>
        <p>No marks found for this student<?= $examId > 0 ? ' in the selected examination' : '' ?>.</p>
    <?php endif; ?>
</div>
<?php elseif ($studentId > 0 && !$student): ?>
    <div class="card"><p>Student not found.</p></div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
