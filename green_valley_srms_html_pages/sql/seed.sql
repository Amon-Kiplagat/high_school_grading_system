-- ============================================================================
--  Green Valley SRMS - sample/seed data
--  Run AFTER schema.sql:
--      psql -U mole -d green_valley -f seed.sql
--
--  Login accounts created by this file:
--      username: admin    password: admin123     role: admin
--      username: teacher  password: teacher123   role: teacher
--
--  IMPORTANT: change these passwords after first login (or create fresh users
--  with create_admin.php, which hashes with PHP's password_hash()).
--  The password_hash values below are real bcrypt hashes ($2y$) produced the
--  same way PHP's password_hash(PASSWORD_DEFAULT) would produce them.
-- ============================================================================

-- Clear existing rows and restart identity counters (safe to re-run).
TRUNCATE results, exams, subjects, students, classes, users, teachers
    RESTART IDENTITY CASCADE;

-- ---- Teachers -------------------------------------------------------------
INSERT INTO teachers (staff_no, full_name, gender, phone, email) VALUES
    ('T001', 'John Mwangi',   'Male',   '0700123456', 'john@greenvalley.ac.ke'),
    ('T002', 'Jane Wambui',   'Female', '0700223344', 'jane@greenvalley.ac.ke'),
    ('T003', 'Peter Otieno',  'Male',   '0700556677', 'peter@greenvalley.ac.ke'),
    ('T004', 'Grace Njeri',   'Female', '0700889900', 'grace@greenvalley.ac.ke');

-- ---- Users (login accounts) ----------------------------------------------
-- admin/admin123 and teacher/teacher123 (teacher linked to John Mwangi = id 1)
INSERT INTO users (username, password_hash, full_name, role, teacher_id) VALUES
    ('admin',
     '$2y$10$CF2EfVprqnaGhwtjXW/kZuXLR92XGl8l3e3sMBVWa2luQJZTsC7eK',
     'System Administrator', 'admin', NULL),
    ('teacher',
     '$2y$10$VQDyuAm6OBC7q9C2sqIbyeoTkEgZQOpAAVQGr8kFfL4GmpvWVwDEO',
     'John Mwangi', 'teacher', 1);

-- ---- Classes (teacher_id references teachers) -----------------------------
INSERT INTO classes (class_name, teacher_id, room) VALUES
    ('Form 1 East',  2, 'Rm 1'),
    ('Form 2 West',  1, 'Rm 5'),
    ('Form 3 North', 3, 'Rm 9'),
    ('Form 4 South', 4, 'Rm 12');

-- ---- Students (class_id references classes) -------------------------------
INSERT INTO students
    (admission_no, full_name, gender, date_of_birth, class_id, guardian_name, guardian_phone) VALUES
    ('GV001', 'Mary Wanjiku', 'Female', '2010-03-14', 2, 'Samuel Wanjiku', '0712345678'),
    ('GV002', 'Peter Kamau',  'Male',   '2009-07-22', 3, 'Alice Kamau',    '0723456789'),
    ('GV003', 'Faith Achieng','Female', '2011-01-09', 1, 'Joseph Achieng', '0734567890'),
    ('GV004', 'Brian Kiptoo', 'Male',   '2008-11-30', 4, 'Nancy Kiptoo',   '0745678901');

-- ---- Subjects (teacher_id references teachers) ----------------------------
INSERT INTO subjects (subject_code, subject_name, teacher_id) VALUES
    ('MAT', 'Mathematics', 1),
    ('ENG', 'English',     2),
    ('KIS', 'Kiswahili',   2),
    ('SCI', 'Science',     3),
    ('SST', 'Social Studies', 4);

-- ---- Exams (unique on name+term+year) ------------------------------------
INSERT INTO exams (exam_name, term, year) VALUES
    ('Term 1 CAT', 'Term 1', 2026),
    ('Mid-Term',   'Term 1', 2026),
    ('End-Term',   'Term 1', 2026);

-- ---- Results (student_id, subject_id, exam_id; grade matches PHP logic) ---
-- Grades follow the KCSE 12-point scale implemented in includes/functions.php.
INSERT INTO results (student_id, subject_id, exam_id, marks, grade) VALUES
    (1, 1, 2, 78, 'A-'),   -- Mary,  Mathematics, Mid-Term
    (1, 2, 2, 72, 'B+'),   -- Mary,  English,     Mid-Term
    (1, 3, 2, 75, 'A-'),   -- Mary,  Kiswahili,   Mid-Term
    (2, 1, 2, 64, 'B-'),   -- Peter, Mathematics, Mid-Term
    (2, 4, 2, 58, 'C+');   -- Peter, Science,     Mid-Term

-- ============================================================================
-- Done. You can now log in at login.php with admin/admin123.
-- ============================================================================
