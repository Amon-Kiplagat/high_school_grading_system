-- ============================================================================
--  Green Valley SRMS - migration: activity log + phone number rules
--  Date: 2026-10-04
--
--  WHY THIS FILE EXISTS
--    schema.sql DROPs and recreates every table, so running it would delete
--    the records you already have. This migration only ADDS the new pieces and
--    is safe to run on a live database. It is also safe to run twice.
--
--  HOW TO RUN
--    psql -h localhost -U mole -d green_valley -f sql/migration_2026_10_activity_log_and_phone.sql
--
--  WHAT IT DOES
--    1. Creates the activity_log table (the dashboard's audit trail).
--    2. Tidies existing phone numbers into the canonical 07.../01... form.
--    3. Adds CHECK constraints so only digits can ever be stored from now on.
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1. ACTIVITY LOG
--    IF NOT EXISTS means re-running this file is harmless.
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
    log_id       BIGSERIAL PRIMARY KEY,
    user_id      INTEGER REFERENCES users(user_id) ON DELETE SET NULL,
    username     VARCHAR(60)  NOT NULL,
    full_name    VARCHAR(120) NOT NULL,
    role         VARCHAR(20),
    action       VARCHAR(40)  NOT NULL,
    entity       VARCHAR(40),
    entity_label VARCHAR(160),
    ip_address   VARCHAR(45),
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_activity_recent ON activity_log(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_activity_user   ON activity_log(user_id);

-- ----------------------------------------------------------------------------
-- 2. CLEAN UP EXISTING PHONE NUMBERS
--    Strips spaces, dashes, brackets and dots, then rewrites the +254 / 254 /
--    bare-9-digit variants into the single 10-digit local form (0712345678).
--    Rows that are already correct are left untouched.
-- ----------------------------------------------------------------------------

-- Students: remove every non-digit character.
UPDATE students
   SET guardian_phone = regexp_replace(guardian_phone, '\D', '', 'g')
 WHERE guardian_phone IS NOT NULL
   AND guardian_phone ~ '\D';

-- Students: 254712345678 / 00254712345678 -> 0712345678
UPDATE students
   SET guardian_phone = '0' || right(guardian_phone, 9)
 WHERE guardian_phone ~ '^(00)?254[71][0-9]{8}$';

-- Students: 712345678 -> 0712345678
UPDATE students
   SET guardian_phone = '0' || guardian_phone
 WHERE guardian_phone ~ '^[71][0-9]{8}$';

-- Teachers: the same three steps.
UPDATE teachers
   SET phone = regexp_replace(phone, '\D', '', 'g')
 WHERE phone IS NOT NULL
   AND phone ~ '\D';

UPDATE teachers
   SET phone = '0' || right(phone, 9)
 WHERE phone ~ '^(00)?254[71][0-9]{8}$';

UPDATE teachers
   SET phone = '0' || phone
 WHERE phone ~ '^[71][0-9]{8}$';

-- Blank strings become NULL so the constraint treats them as "not supplied".
UPDATE students SET guardian_phone = NULL WHERE guardian_phone = '';
UPDATE teachers SET phone          = NULL WHERE phone          = '';

-- ----------------------------------------------------------------------------
-- 3. ADD THE CONSTRAINTS
--    NOT VALID means: enforce this on every INSERT and UPDATE from now on, but
--    do not fail the migration if some old row is still odd (an unusual
--    landline, say). Nothing bad can be saved again; step 4 tells you how to
--    find and finish off any leftovers.
--
--    Wrapped in DO blocks so re-running the file does not error with
--    "constraint already exists".
-- ----------------------------------------------------------------------------
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'chk_students_guardian_phone'
    ) THEN
        ALTER TABLE students
            ADD CONSTRAINT chk_students_guardian_phone
            CHECK (guardian_phone IS NULL OR guardian_phone ~ '^0[71][0-9]{8}$')
            NOT VALID;
    END IF;
END
$$;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'chk_teachers_phone'
    ) THEN
        ALTER TABLE teachers
            ADD CONSTRAINT chk_teachers_phone
            CHECK (phone IS NULL OR phone ~ '^0[71][0-9]{8}$')
            NOT VALID;
    END IF;
END
$$;

COMMIT;

-- ============================================================================
-- 4. OPTIONAL FOLLOW-UP
--
--    List any leftover rows the clean-up could not interpret:
--
--      SELECT admission_no, full_name, guardian_phone FROM students
--       WHERE guardian_phone IS NOT NULL AND guardian_phone !~ '^0[71][0-9]{8}$';
--
--      SELECT staff_no, full_name, phone FROM teachers
--       WHERE phone IS NOT NULL AND phone !~ '^0[71][0-9]{8}$';
--
--    Fix those by hand (or in the app), then mark the constraints fully valid:
--
--      ALTER TABLE students VALIDATE CONSTRAINT chk_students_guardian_phone;
--      ALTER TABLE teachers VALIDATE CONSTRAINT chk_teachers_phone;
-- ============================================================================
