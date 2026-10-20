#!/usr/bin/env python3
"""
================================================================================
 Green Valley SRMS - Exam Analytics & Report Generator  (tools/analytics.py)
================================================================================
A server-side reporting tool written in Python. It READS the existing
PostgreSQL tables (students, subjects, classes, exams, results) and produces a
formatted PDF report plus a CSV, covering:

    * Overview counts
    * Per-exam summary (entries, mean, lowest, highest, mean grade)
    * Grade distribution (KCSE 12-point A..E) with a bar chart
    * Subject performance (ranked by mean mark)
    * Class performance (ranked by mean mark)
    * Top students (by average mark)

DESIGN RULES honoured (see README / project brief):
    * It does NOT create or duplicate any report data in the database. Every
      figure is computed live from the existing tables with SQL + Python.
    * Database credentials are read from the SAME .env the PHP app uses. They
      are never hard-coded and never leave the server. This script is run from
      a terminal by staff, not from the browser.

--------------------------------------------------------------------------------
USAGE
    pip install -r tools/requirements.txt         # one time (psycopg2 + reportlab)

    python3 tools/analytics.py                     # all examinations, top 10
    python3 tools/analytics.py --list-exams        # show exam ids to filter by
    python3 tools/analytics.py --exam-id 3         # one examination only
    python3 tools/analytics.py --top 20 --out reports_out
    python3 tools/analytics.py --format pdf        # pdf | csv | both (default both)
    python3 tools/analytics.py --self-test         # render sample data, no DB

Run it from the project root so it finds .env (or pass --env /path/to/.env).
================================================================================
"""
from __future__ import annotations

import argparse
import csv
import datetime as _dt
import os
import sys

# Canonical KCSE 12-point grade order (best -> worst).
GRADE_ORDER = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D+', 'D', 'D-', 'E']

# Brand palette (matches css/style.css).
HEX_PINE = '#1f5138'
HEX_PINE_DEEP = '#163b28'
HEX_GOLD = '#c98a2b'
HEX_PAPER = '#f2f4ef'
HEX_SURF2 = '#f7f9f5'
HEX_LINE = '#e3e7df'
HEX_INK = '#1b2a24'


# --------------------------------------------------------------------------- #
#  Grading  (mirrors includes/functions.php calculate_grade exactly)
# --------------------------------------------------------------------------- #
def grade_for(marks) -> str:
    """Return the KCSE grade for a mark. Identical bands to the PHP app."""
    if marks is None:
        return '-'
    m = float(marks)
    m = max(0.0, min(100.0, m))
    bands = [
        (80, 'A'), (75, 'A-'), (70, 'B+'), (65, 'B'), (60, 'B-'),
        (55, 'C+'), (50, 'C'), (45, 'C-'), (40, 'D+'), (35, 'D'), (30, 'D-'),
    ]
    for threshold, letter in bands:
        if m >= threshold:
            return letter
    return 'E'


def fmt(x, dp: int = 1) -> str:
    """Format a number (or None) for display."""
    if x is None:
        return '-'
    return f'{float(x):.{dp}f}'


# --------------------------------------------------------------------------- #
#  .env loading  (same file the PHP app reads; credentials stay server-side)
# --------------------------------------------------------------------------- #
def load_env(path: str) -> dict:
    """Minimal .env reader: KEY=VALUE lines, ignores blanks/# comments,
    strips surrounding quotes and unquoted trailing comments."""
    cfg = {}
    if not os.path.isfile(path):
        raise SystemExit(
            f'Could not find environment file: {path}\n'
            'Run this from the project root, or pass --env /path/to/.env'
        )
    with open(path, 'r', encoding='utf-8') as fh:
        for raw in fh:
            line = raw.strip()
            if not line or line.startswith('#') or '=' not in line:
                continue
            key, val = line.split('=', 1)
            key = key.strip()
            val = val.strip()
            if len(val) >= 2 and val[0] in '"\'' and val[-1] == val[0]:
                val = val[1:-1]                      # quoted value: take as-is
            else:
                hash_at = val.find(' #')             # strip unquoted inline comment
                if hash_at != -1:
                    val = val[:hash_at].strip()
            cfg[key] = val
    return cfg


def connect(cfg: dict):
    """Open a PostgreSQL connection using psycopg (v3) or psycopg2 - whichever
    is installed. Only reads data; never writes."""
    params = dict(
        host=cfg.get('DB_HOST', 'localhost'),
        port=cfg.get('DB_PORT', '5432'),
        dbname=cfg.get('DB_NAME', 'green_valley'),
        user=cfg.get('DB_USER', ''),
        password=cfg.get('DB_PASS', ''),
    )
    try:
        import psycopg          # psycopg v3
        return psycopg.connect(**params)
    except ImportError:
        pass
    try:
        import psycopg2         # psycopg v2
        return psycopg2.connect(**params)
    except ImportError:
        raise SystemExit(
            'No PostgreSQL driver found. Install one with:\n'
            '    pip install -r tools/requirements.txt\n'
            '(that installs psycopg2-binary)'
        )


def q(cur, sql, params=None):
    cur.execute(sql, params or [])
    cols = [d[0] for d in cur.description]
    return [dict(zip(cols, row)) for row in cur.fetchall()]


# --------------------------------------------------------------------------- #
#  Data gathering  (all live queries over the existing tables)
# --------------------------------------------------------------------------- #
def list_exams(conn) -> None:
    with conn.cursor() as cur:
        rows = q(cur, """
            SELECT e.exam_id, e.exam_name, e.term, e.year,
                   COUNT(r.result_id) AS n
              FROM exams e
              LEFT JOIN results r ON r.exam_id = e.exam_id
             GROUP BY e.exam_id, e.exam_name, e.term, e.year
             ORDER BY e.year DESC, e.term, e.exam_name
        """)
    if not rows:
        print('No examinations found.')
        return
    print(f'{"ID":>3}  {"Examination":<34}  {"Results":>7}')
    print('-' * 50)
    for r in rows:
        label = f'{r["exam_name"]} - {r["term"]} {r["year"]}'
        print(f'{r["exam_id"]:>3}  {label:<34}  {r["n"]:>7}')


def fetch_report(conn, exam_id, top_n) -> dict:
    where = ''
    params = []
    if exam_id:
        where = ' WHERE r.exam_id = %s'
        params = [exam_id]

    with conn.cursor() as cur:
        # Scope label + overview counts
        scope_label = 'All examinations'
        if exam_id:
            ex = q(cur, 'SELECT exam_name, term, year FROM exams WHERE exam_id = %s', [exam_id])
            if not ex:
                raise SystemExit(f'No examination with id {exam_id}. Try --list-exams.')
            scope_label = f'{ex[0]["exam_name"]} - {ex[0]["term"]} {ex[0]["year"]}'

        overview = q(cur, """
            SELECT (SELECT COUNT(*) FROM students) AS students,
                   (SELECT COUNT(*) FROM subjects) AS subjects,
                   (SELECT COUNT(*) FROM exams)    AS exams,
                   (SELECT COUNT(*) FROM results)  AS results
        """)[0]

        exam_summary = q(cur, """
            SELECT e.exam_name, e.term, e.year,
                   COUNT(r.result_id) AS n,
                   AVG(r.marks) AS mean, MIN(r.marks) AS lo, MAX(r.marks) AS hi
              FROM exams e
              LEFT JOIN results r ON r.exam_id = e.exam_id
             GROUP BY e.exam_id, e.exam_name, e.term, e.year
             ORDER BY e.year DESC, e.term, e.exam_name
        """)

        marks_rows = q(cur, f'SELECT r.marks FROM results r{where}', params)

        subjects = q(cur, f"""
            SELECT sub.subject_name,
                   COUNT(r.result_id) AS n,
                   AVG(r.marks) AS mean, MIN(r.marks) AS lo, MAX(r.marks) AS hi
              FROM results r
              JOIN subjects sub ON sub.subject_id = r.subject_id
              {where}
             GROUP BY sub.subject_name
             ORDER BY mean DESC NULLS LAST
        """, params)

        classes = q(cur, f"""
            SELECT COALESCE(c.class_name, 'Unassigned') AS class_name,
                   COUNT(r.result_id) AS n, AVG(r.marks) AS mean
              FROM results r
              JOIN students s ON s.student_id = r.student_id
              LEFT JOIN classes c ON c.class_id = s.class_id
              {where}
             GROUP BY class_name
             ORDER BY mean DESC NULLS LAST
        """, params)

        top = q(cur, f"""
            SELECT s.admission_no, s.full_name,
                   COALESCE(c.class_name, '-') AS class_name,
                   COUNT(r.result_id) AS subjects, AVG(r.marks) AS mean
              FROM results r
              JOIN students s ON s.student_id = r.student_id
              LEFT JOIN classes c ON c.class_id = s.class_id
              {where}
             GROUP BY s.student_id, s.admission_no, s.full_name, class_name
            HAVING COUNT(r.result_id) > 0
             ORDER BY mean DESC, s.full_name
             LIMIT %s
        """, params + [top_n])

    # Grade distribution computed in Python from marks (same bands as the app).
    dist = {g: 0 for g in GRADE_ORDER}
    for row in marks_rows:
        dist[grade_for(row['marks'])] += 1

    # Attach a derived mean-grade to each aggregate row.
    for r in exam_summary:
        r['grade'] = grade_for(r['mean'])
    for r in subjects:
        r['grade'] = grade_for(r['mean'])
    for r in classes:
        r['grade'] = grade_for(r['mean'])
    for r in top:
        r['grade'] = grade_for(r['mean'])

    return {
        'scope_label': scope_label,
        'generated': _dt.datetime.now().strftime('%Y-%m-%d %H:%M'),
        'overview': overview,
        'exam_summary': exam_summary,
        'grade_dist': dist,
        'subjects': subjects,
        'classes': classes,
        'top': top,
        'top_n': top_n,
    }


# --------------------------------------------------------------------------- #
#  CSV output  (all sections stacked, opens cleanly in a spreadsheet)
# --------------------------------------------------------------------------- #
def write_csv(path: str, rep: dict) -> None:
    with open(path, 'w', newline='', encoding='utf-8') as fh:
        w = csv.writer(fh)
        w.writerow(['Green Valley Secondary School - Exam Analytics'])
        w.writerow(['Scope', rep['scope_label']])
        w.writerow(['Generated', rep['generated']])
        w.writerow([])

        ov = rep['overview']
        w.writerow(['OVERVIEW'])
        w.writerow(['Students', 'Subjects', 'Examinations', 'Marks recorded'])
        w.writerow([ov['students'], ov['subjects'], ov['exams'], ov['results']])
        w.writerow([])

        w.writerow(['PER-EXAM SUMMARY'])
        w.writerow(['Examination', 'Entries', 'Mean', 'Lowest', 'Highest', 'Mean grade'])
        for r in rep['exam_summary']:
            w.writerow([f'{r["exam_name"]} - {r["term"]} {r["year"]}',
                        r['n'], fmt(r['mean']), fmt(r['lo']), fmt(r['hi']), r['grade']])
        w.writerow([])

        w.writerow(['GRADE DISTRIBUTION'])
        w.writerow(['Grade'] + GRADE_ORDER)
        w.writerow(['Count'] + [rep['grade_dist'][g] for g in GRADE_ORDER])
        w.writerow([])

        w.writerow(['SUBJECT PERFORMANCE'])
        w.writerow(['Subject', 'Entries', 'Mean', 'Lowest', 'Highest', 'Mean grade'])
        for r in rep['subjects']:
            w.writerow([r['subject_name'], r['n'], fmt(r['mean']),
                        fmt(r['lo']), fmt(r['hi']), r['grade']])
        w.writerow([])

        w.writerow(['CLASS PERFORMANCE'])
        w.writerow(['Class', 'Entries', 'Mean', 'Mean grade'])
        for r in rep['classes']:
            w.writerow([r['class_name'], r['n'], fmt(r['mean']), r['grade']])
        w.writerow([])

        w.writerow([f'TOP {rep["top_n"]} STUDENTS'])
        w.writerow(['#', 'Adm. No', 'Name', 'Class', 'Subjects', 'Average', 'Mean grade'])
        for i, r in enumerate(rep['top'], 1):
            w.writerow([i, r['admission_no'], r['full_name'], r['class_name'],
                        r['subjects'], fmt(r['mean']), r['grade']])


# --------------------------------------------------------------------------- #
#  PDF output  (reportlab; brand-styled)
# --------------------------------------------------------------------------- #
def write_pdf(path: str, rep: dict) -> None:
    try:
        from reportlab.lib import colors
        from reportlab.lib.pagesizes import A4
        from reportlab.lib.units import mm
        from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
        from reportlab.platypus import (SimpleDocTemplate, Paragraph, Spacer,
                                        Table, TableStyle)
        from reportlab.platypus.flowables import HRFlowable
        from reportlab.graphics.shapes import Drawing
        from reportlab.graphics.charts.barcharts import VerticalBarChart
    except ImportError:
        raise SystemExit(
            'reportlab is not installed. Install it with:\n'
            '    pip install -r tools/requirements.txt'
        )

    pine = colors.HexColor(HEX_PINE)
    pine_deep = colors.HexColor(HEX_PINE_DEEP)
    gold = colors.HexColor(HEX_GOLD)
    surf2 = colors.HexColor(HEX_SURF2)
    line = colors.HexColor(HEX_LINE)
    ink = colors.HexColor(HEX_INK)

    ss = getSampleStyleSheet()
    h_title = ParagraphStyle('t', parent=ss['Title'], textColor=pine, fontSize=20,
                             spaceAfter=2, alignment=0)
    h_sub = ParagraphStyle('s', parent=ss['Normal'], textColor=colors.HexColor('#5c6b63'),
                           fontSize=9.5, spaceAfter=2)
    h_sec = ParagraphStyle('h', parent=ss['Heading2'], textColor=pine_deep, fontSize=13,
                           spaceBefore=14, spaceAfter=6)
    body = ParagraphStyle('b', parent=ss['Normal'], textColor=ink, fontSize=9.5)

    def styled_table(header, rows, widths=None, numeric_cols=()):
        data = [header] + (rows if rows else [['(no data)'] + [''] * (len(header) - 1)])
        t = Table(data, colWidths=widths, repeatRows=1)
        style = [
            ('BACKGROUND', (0, 0), (-1, 0), pine),
            ('TEXTCOLOR', (0, 0), (-1, 0), colors.white),
            ('FONTNAME', (0, 0), (-1, 0), 'Helvetica-Bold'),
            ('FONTSIZE', (0, 0), (-1, -1), 8.5),
            ('TOPPADDING', (0, 0), (-1, -1), 5),
            ('BOTTOMPADDING', (0, 0), (-1, -1), 5),
            ('LEFTPADDING', (0, 0), (-1, -1), 7),
            ('RIGHTPADDING', (0, 0), (-1, -1), 7),
            ('TEXTCOLOR', (0, 1), (-1, -1), ink),
            ('ROWBACKGROUNDS', (0, 1), (-1, -1), [colors.white, surf2]),
            ('LINEBELOW', (0, 0), (-1, -1), 0.4, line),
            ('LINEAFTER', (0, 0), (-2, -1), 0.4, line),
            ('BOX', (0, 0), (-1, -1), 0.6, line),
            ('VALIGN', (0, 0), (-1, -1), 'MIDDLE'),
        ]
        for c in numeric_cols:
            style.append(('ALIGN', (c, 0), (c, -1), 'RIGHT'))
        t.setStyle(TableStyle(style))
        return t

    def grade_chart(dist):
        d = Drawing(460, 175)
        chart = VerticalBarChart()
        chart.x, chart.y = 20, 18
        chart.width, chart.height = 420, 135
        chart.data = [[dist[g] for g in GRADE_ORDER]]
        chart.categoryAxis.categoryNames = GRADE_ORDER
        chart.categoryAxis.labels.fontSize = 8
        chart.valueAxis.valueMin = 0
        top = max(dist.values()) if any(dist.values()) else 1
        chart.valueAxis.valueMax = top + max(1, round(top * 0.15))
        chart.valueAxis.labels.fontSize = 7
        chart.bars[0].fillColor = pine
        chart.bars[0].strokeColor = pine_deep
        chart.barWidth = 10
        chart.groupSpacing = 6
        chart.barLabelFormat = '%d'
        chart.barLabels.fontSize = 7
        chart.barLabels.nudge = 6
        chart.barLabels.fillColor = ink
        d.add(chart)
        return d

    story = []
    story.append(Paragraph('Green Valley Secondary School', h_title))
    story.append(Paragraph('Exam Analytics Report &nbsp;&middot;&nbsp; Murang’a County', h_sub))
    story.append(HRFlowable(width='100%', thickness=2, color=gold,
                            spaceBefore=4, spaceAfter=8))
    story.append(Paragraph(f'<b>Scope:</b> {rep["scope_label"]}', body))
    story.append(Paragraph(f'<b>Generated:</b> {rep["generated"]}', body))

    ov = rep['overview']
    story.append(Paragraph('Overview', h_sec))
    story.append(styled_table(
        ['Students', 'Subjects', 'Examinations', 'Marks recorded'],
        [[ov['students'], ov['subjects'], ov['exams'], ov['results']]],
        widths=[110, 110, 120, 120], numeric_cols=(0, 1, 2, 3)))

    story.append(Paragraph('Grade distribution', h_sec))
    story.append(grade_chart(rep['grade_dist']))

    story.append(Paragraph('Per-exam summary', h_sec))
    story.append(styled_table(
        ['Examination', 'Entries', 'Mean', 'Lowest', 'Highest', 'Grade'],
        [[f'{r["exam_name"]} - {r["term"]} {r["year"]}', r['n'], fmt(r['mean']),
          fmt(r['lo']), fmt(r['hi']), r['grade']] for r in rep['exam_summary']],
        widths=[200, 55, 55, 55, 55, 50], numeric_cols=(1, 2, 3, 4)))

    story.append(Paragraph('Subject performance', h_sec))
    story.append(styled_table(
        ['Subject', 'Entries', 'Mean', 'Lowest', 'Highest', 'Grade'],
        [[r['subject_name'], r['n'], fmt(r['mean']), fmt(r['lo']), fmt(r['hi']),
          r['grade']] for r in rep['subjects']],
        widths=[200, 55, 55, 55, 55, 50], numeric_cols=(1, 2, 3, 4)))

    story.append(Paragraph('Class performance', h_sec))
    story.append(styled_table(
        ['Class', 'Entries', 'Mean', 'Grade'],
        [[r['class_name'], r['n'], fmt(r['mean']), r['grade']] for r in rep['classes']],
        widths=[240, 70, 70, 60], numeric_cols=(1, 2)))

    story.append(Paragraph(f'Top {rep["top_n"]} students', h_sec))
    story.append(styled_table(
        ['#', 'Adm. No', 'Name', 'Class', 'Subj.', 'Average', 'Grade'],
        [[i, r['admission_no'], r['full_name'], r['class_name'], r['subjects'],
          fmt(r['mean']), r['grade']] for i, r in enumerate(rep['top'], 1)],
        widths=[24, 70, 150, 110, 45, 55, 46], numeric_cols=(0, 4, 5)))

    story.append(Spacer(1, 16))
    story.append(HRFlowable(width='100%', thickness=0.6, color=line,
                            spaceBefore=4, spaceAfter=4))
    story.append(Paragraph(
        'Generated by tools/analytics.py from live records. No report data is '
        'stored in the database.', h_sub))

    SimpleDocTemplate(
        path, pagesize=A4,
        leftMargin=18 * mm, rightMargin=18 * mm,
        topMargin=16 * mm, bottomMargin=16 * mm,
        title='Green Valley SRMS - Exam Analytics',
        author='Green Valley SRMS',
    ).build(story)


# --------------------------------------------------------------------------- #
#  Sample data for --self-test (renders without a database)
# --------------------------------------------------------------------------- #
def sample_report() -> dict:
    return {
        'scope_label': 'End-Term - Term 3 2026 (sample)',
        'generated': _dt.datetime.now().strftime('%Y-%m-%d %H:%M'),
        'overview': {'students': 120, 'subjects': 9, 'exams': 4, 'results': 980},
        'exam_summary': [
            {'exam_name': 'End-Term', 'term': 'Term 3', 'year': 2026, 'n': 300,
             'mean': 61.4, 'lo': 18, 'hi': 97, 'grade': grade_for(61.4)},
            {'exam_name': 'Mid-Term', 'term': 'Term 3', 'year': 2026, 'n': 290,
             'mean': 58.2, 'lo': 12, 'hi': 94, 'grade': grade_for(58.2)},
        ],
        'grade_dist': {'A': 22, 'A-': 31, 'B+': 44, 'B': 58, 'B-': 70, 'C+': 66,
                       'C': 52, 'C-': 40, 'D+': 28, 'D': 18, 'D-': 9, 'E': 4},
        'subjects': [
            {'subject_name': 'Mathematics', 'n': 120, 'mean': 64.2, 'lo': 20, 'hi': 97,
             'grade': grade_for(64.2)},
            {'subject_name': 'English', 'n': 120, 'mean': 59.8, 'lo': 25, 'hi': 91,
             'grade': grade_for(59.8)},
            {'subject_name': 'Biology', 'n': 118, 'mean': 55.1, 'lo': 18, 'hi': 88,
             'grade': grade_for(55.1)},
        ],
        'classes': [
            {'class_name': 'Form 4 East', 'n': 240, 'mean': 63.5, 'grade': grade_for(63.5)},
            {'class_name': 'Form 4 West', 'n': 230, 'mean': 59.1, 'grade': grade_for(59.1)},
        ],
        'top': [
            {'admission_no': 'GV041', 'full_name': 'Aisha Mwangi', 'class_name': 'Form 4 East',
             'subjects': 8, 'mean': 88.6, 'grade': grade_for(88.6)},
            {'admission_no': 'GV017', 'full_name': 'Brian Otieno', 'class_name': 'Form 4 West',
             'subjects': 8, 'mean': 85.2, 'grade': grade_for(85.2)},
            {'admission_no': 'GV102', 'full_name': 'Cynthia Wairimu', 'class_name': 'Form 4 East',
             'subjects': 8, 'mean': 83.9, 'grade': grade_for(83.9)},
        ],
        'top_n': 10,
    }


# --------------------------------------------------------------------------- #
#  Main
# --------------------------------------------------------------------------- #
def main(argv=None) -> int:
    ap = argparse.ArgumentParser(
        description='Generate exam analytics (PDF + CSV) from the SRMS database.')
    ap.add_argument('--env', default='.env', help='path to the .env file (default: .env)')
    ap.add_argument('--exam-id', type=int, default=None,
                    help='limit the report to one examination (see --list-exams)')
    ap.add_argument('--list-exams', action='store_true',
                    help='print the examinations and their ids, then exit')
    ap.add_argument('--top', type=int, default=10, help='number of top students (default 10)')
    ap.add_argument('--out', default='exports', help='output folder (default: exports)')
    ap.add_argument('--format', choices=['pdf', 'csv', 'both'], default='both',
                    help='which files to write (default: both)')
    ap.add_argument('--self-test', action='store_true',
                    help='render sample data without touching the database')
    args = ap.parse_args(argv)

    os.makedirs(args.out, exist_ok=True)
    scope = f'exam{args.exam_id}' if args.exam_id else 'all'
    stamp = _dt.date.today().strftime('%Y%m%d')
    base = os.path.join(args.out, f'analytics_{scope}_{stamp}')

    if args.self_test:
        rep = sample_report()
    else:
        cfg = load_env(args.env)
        conn = connect(cfg)
        try:
            if args.list_exams:
                list_exams(conn)
                return 0
            rep = fetch_report(conn, args.exam_id, args.top)
        finally:
            conn.close()

    written = []
    if args.format in ('pdf', 'both'):
        write_pdf(base + '.pdf', rep)
        written.append(base + '.pdf')
    if args.format in ('csv', 'both'):
        write_csv(base + '.csv', rep)
        written.append(base + '.csv')

    ov = rep['overview']
    print(f'Scope        : {rep["scope_label"]}')
    print(f'Students     : {ov["students"]}   Subjects: {ov["subjects"]}   '
          f'Exams: {ov["exams"]}   Marks: {ov["results"]}')
    print('Wrote        : ' + '\n               '.join(written))
    return 0


if __name__ == '__main__':
    sys.exit(main())
