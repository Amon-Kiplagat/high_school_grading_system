# Green Valley Secondary School — Student Records Management System (SRMS)

A student records management system for Green Valley Secondary School, Murang'a County.
The static HTML pages have been converted into a fully functional web application using:

- **HTML** — page structure (your original layout, preserved)
- **CSS** — styling (`css/style.css`), including the responsive mobile layout
- **JavaScript** — client-side validation and the live grade preview (`js/script.js`),
  plus the Progressive Web App layer that makes the site installable on a phone and
  usable offline (`js/pwa.js` + `service-worker.js`)
- **PHP** — the backend / API layer (all database access happens here)
- **PostgreSQL** — the database
- **Python** — an optional, server-side exam-analytics tool (`tools/analytics.py`) that
  reads the existing tables and produces PDF + CSV reports

The browser never talks to PostgreSQL directly. Every page and form goes through PHP,
which uses **PDO prepared statements**. Database credentials live in a `.env` file on the
server and are never exposed to HTML or JavaScript.

---

## 1. File structure and where everything goes

The whole application already lives in this folder (the one you selected). When you run a
PHP server, point its **document root at this folder**. The layout is:

```
green_valley_srms_html_pages/   (this folder = your web root)
├── .env                     # your real DB credentials (NOT committed / NOT web-served content)
├── .env.example             # template to copy from
│
│   # --- old static mockups, kept only as forwarders ---
├── login.html               # each *.html now redirects to its working *.php page
├── dashboard.html           #   (plain redirect stubs — no data in them)
├── students.html            #   ...
│
├── config/
│   └── database.php         # PDO + PostgreSQL connection (reads .env)
│
├── includes/
│   ├── auth.php             # sessions, login state, role checks
│   ├── functions.php        # helpers: escaping, grade calc, CSRF, flash messages
│   ├── header.php           # shared page head + topbar (opens .main)
│   ├── sidebar.php          # shared navigation menu
│   └── footer.php           # shared page footer (loads script.js)
│
├── actions/                 # form handlers (POST targets) — one per entity
│   ├── student_actions.php
│   ├── teacher_actions.php
│   ├── class_actions.php
│   ├── subject_actions.php
│   ├── exam_actions.php
│   └── result_actions.php
│
├── css/
│   └── style.css            # all styling
│
├── js/
│   ├── script.js            # client-side validation + grade preview
│   └── pwa.js               # PWA: service-worker registration, mobile drawer, install
│
├── sql/
│   ├── schema.sql           # creates the 7 tables, constraints and indexes
│   └── seed.sql             # optional sample data (incl. login accounts)
│
│   # --- Progressive Web App (mobile + offline) ---
├── manifest.webmanifest     # installable-app metadata (name, icons, colours)
├── service-worker.js        # offline layer — caches STATIC files only, never the DB
├── offline.html             # shown when a page is opened with no connection
├── icons/                   # app icons used by the manifest and browsers
│   ├── icon-192.png
│   ├── icon-512.png
│   ├── icon-maskable-512.png
│   ├── apple-touch-icon.png
│   └── favicon-32.png
│
│   # --- Python analytics (server-side, run from a terminal) ---
├── tools/
│   ├── analytics.py         # exam analytics → PDF + CSV (reads existing tables)
│   └── requirements.txt     # Python dependencies (psycopg2 + reportlab)
├── exports/                 # generated reports land here (created automatically)
│
├── index.php                # entry point → redirects to dashboard or login
├── login.php                # login page  (replaces login.html)
├── logout.php               # ends the session
├── dashboard.php            # dashboard  (replaces dashboard.html)
├── students.php             # students    (replaces students.html)
├── teachers.php             # teachers    (replaces teachers.html)
├── classes.php              # classes     (replaces classes.html)
├── subjects.php             # subjects    (replaces subjects.html)
├── exams.php                # exams       (replaces exams.html)
├── results.php              # marks entry (new — the results interface)
├── reports.php              # reports     (replaces reports.html)
└── create_admin.php         # one-off utility to create/reset a login (DELETE after use)
```

Each old `*.html` file has been replaced by the matching `*.php` file. The `.html` files
that remain are tiny redirects that forward to the working `.php` pages, so old links keep
working; the working `.php` pages are the single source of truth. The forms
now submit to the handlers in `actions/`, which validate the input and talk to PostgreSQL.

> **Important:** always open the app through the running PHP server (e.g.
> `http://localhost:8000/`), **not** by double-clicking an `.html` or `.php` file in your
> file manager. PHP only runs when served by a server; opening files directly shows the old
> static version or raw code.

---

## 2. Set up the PostgreSQL database

You already run PostgreSQL 18 locally with a database called `green_valley` (user `mole`).
The commands below create the tables and load the sample data.

**a. Create the database** (skip if `green_valley` already exists):

```bash
createdb -U mole green_valley
```

or from inside `psql`:

```sql
CREATE DATABASE green_valley;
```

**b. Create the tables, constraints and indexes:**

```bash
psql -U mole -d green_valley -f sql/schema.sql
```

**c. (Optional) Load sample data, including login accounts:**

```bash
psql -U mole -d green_valley -f sql/seed.sql
```

> `schema.sql` drops and recreates the tables each time it runs, so it is safe to re-run
> during development — but it **erases existing data**. Do not run it on a database that
> already holds real records you want to keep.

### What the SQL creates

`schema.sql` builds eight tables with the relationships your spec requires:

| Table          | Key columns | Relationships |
|----------------|-------------|---------------|
| `teachers`     | `teacher_id` (PK), `staff_no` (unique) | — |
| `users`        | `user_id` (PK), `username` (unique) | `teacher_id → teachers` (SET NULL) |
| `classes`      | `class_id` (PK), `class_name` (unique) | `teacher_id → teachers` (SET NULL) |
| `students`     | `student_id` (PK), `admission_no` (unique) | `class_id → classes` (SET NULL) |
| `subjects`     | `subject_id` (PK), `subject_code` (unique) | `teacher_id → teachers` (SET NULL) |
| `exams`        | `exam_id` (PK) | unique on (`exam_name`, `term`, `year`) |
| `results`      | `result_id` (PK) | `student_id`, `subject_id`, `exam_id` (all CASCADE) |
| `activity_log` | `log_id` (PK) | `user_id → users` (SET NULL) |

Duplicate-prevention is enforced by the database itself, not just by PHP:

- `students.admission_no` is **UNIQUE** — no two students share an admission number.
- `results` has a **UNIQUE (student_id, subject_id, exam_id)** constraint — a student
  cannot have two marks for the same subject in the same exam.
- `exams` is **UNIQUE (exam_name, term, year)**.
- `teachers.staff_no`, `subjects.subject_code`, `classes.class_name`, `users.username`
  are all UNIQUE.

Phone numbers are constrained too. Both `students.guardian_phone` and `teachers.phone`
carry a `CHECK` that only accepts **digits** in the canonical Kenyan local form — ten
digits beginning `07` or `01`, e.g. `0712345678`. See section 12 for the full rule.

Indexes are added on the foreign-key and search columns for performance.

Reports are **generated on the fly** by SQL joins across `students`, `subjects`, `exams`
and `results` — no report data is duplicated or stored anywhere.

### Upgrading an existing database

`schema.sql` **drops and recreates** every table, so running it would delete records you
already have. To add the newer pieces — the `activity_log` table and the phone-number
constraints — to a database that already holds real data, run the migration instead:

```bash
psql -h localhost -U mole -d green_valley -f sql/migration_2026_10_activity_log_and_phone.sql
```

It is safe to run more than once. It creates `activity_log` only if it is missing, tidies
any existing phone numbers into the canonical form (stripping spaces and dashes,
converting `+254…` to `0…`), then adds the `CHECK` constraints as `NOT VALID` so an
unusual legacy number cannot make the migration fail. The comments at the bottom of that
file show how to list any leftovers and finish validating once they are fixed.

---

## 3. Configure the connection

Credentials are read from a `.env` file so they never appear in HTML, JavaScript, or the
PHP source. Copy the template and fill in your real password:

```bash
cp .env.example .env
```

Then edit `.env`:

```ini
DB_HOST=localhost
DB_PORT=5432
DB_NAME=green_valley
DB_USER=mole
DB_PASS=your_real_password_here
APP_ENV=development
```

`config/database.php` loads this file and creates a single PDO connection configured for:
prepared statements only (no emulation), exceptions on error, and associative-array
fetches. Set `APP_ENV=production` on a live server to hide detailed error messages.

> **Keep `.env` private.** It holds your database password. Do not put it in a public
> repository, and make sure your web server does not serve dot-files.

---

## 4. Run it locally

The simplest way is PHP's built-in web server. Open a terminal **in this folder** (the one
you selected) and run:

```bash
php -S localhost:8000
```

Then open <http://localhost:8000/> in your browser. You will be redirected to the login
page. (You need PHP 8.0+ with the `pdo_pgsql` extension enabled — check with
`php -m | grep pgsql`.)

Inside a JetBrains IDE (PhpStorm / IntelliJ IDEA Ultimate) you can instead use
**Run → Edit Configurations → + → PHP Built-in Web Server**, with host `localhost`, port
`8000`, and **document root set to this folder**.

If you prefer Apache/XAMPP, place this folder in your web root and browse to it.

---

## 5. Logging in

If you loaded `seed.sql`, two accounts exist:

| Role    | Username  | Password     | Can do |
|---------|-----------|--------------|--------|
| Admin   | `admin`   | `admin123`   | Everything |
| Teacher | `teacher` | `teacher123` | View all records, manage students, enter/edit marks |

**Change these passwords immediately** in a real deployment.

### Creating or resetting an account

`create_admin.php` is a small utility for making a login without touching SQL by hand.
Passwords are stored as bcrypt hashes (`password_hash()`), never in plain text.

From the command line:

```bash
php create_admin.php <username> <password> "Full Name" <admin|teacher>
```

Or in a browser, visit `create_admin.php?setup=1` once to create the default `admin`
account. **Delete `create_admin.php` from the server as soon as you are done** — leaving
it in place is a security risk.

---

## 6. Who can do what (roles)

The system has two roles, checked on every page and every action:

- **Admin** — full access: add/edit/delete students, teachers, classes, subjects, exams;
  enter and edit marks; view all reports; delete records.
- **Teacher** — can view all records, manage students, and enter/edit marks. Managing
  teachers, classes, subjects and exams, and *all* delete operations, are restricted to
  admins.

Anyone who is not logged in is redirected to the login page — no page can be reached by
typing its URL directly without a session.

---

## 7. Security summary

- **PDO prepared statements everywhere** — protects against SQL injection. Values are
  always bound, never concatenated into query strings.
- **Passwords hashed** with `password_hash()` / verified with `password_verify()`
  (bcrypt). Plain-text passwords are never stored. Hashes are upgraded automatically on
  login if the algorithm changes (`password_needs_rehash()`).
- **PHP sessions** with hardened cookies (HttpOnly, SameSite=Lax, Secure over HTTPS).
  The session ID is regenerated on login to prevent fixation.
- **CSRF tokens** on every state-changing form and on delete links, checked with
  `hash_equals()`.
- **Input validation** on both the client (JavaScript) and the server (PHP). Client-side
  checks are for convenience only; the PHP checks are authoritative.
- **Output escaping** — all database values are printed through `htmlspecialchars()`
  (the `e()` helper) to prevent cross-site scripting.
- **Role-based authorization** — `require_login()` and `require_role('admin')` guard the
  pages and actions.
- **Credentials in `.env` only** — never in HTML, JavaScript, or committed source.

---

## 8. Design

Your original look and navigation are preserved. The inline styles from the old HTML
pages were moved verbatim into `css/style.css` (the navy sidebar, stat cards, tables and
form grid are unchanged); only small additive rules were introduced — for the active menu
item, flash/alert messages, the grade badge, search bars, and print styling for reports.
The static sample rows in the old tables are now replaced by live PostgreSQL data.

---

## 9. Install it on a phone + use it offline (PWA)

The app is now a **Progressive Web App (PWA)**: on top of the normal website, it can be
installed to a phone's home screen and keeps working when the connection drops. This is
pure front-end JavaScript — it never touches the database or your credentials.

**What was added**

- `manifest.webmanifest` — tells the browser the app's name, colours and icons so it can
  be installed like a native app. Linked from `includes/header.php`.
- `service-worker.js` — a small background script the browser runs. It caches the **static
  files only** (CSS, JavaScript, icons, the offline page) and serves pages network-first,
  falling back to a cached copy and then to `offline.html` when there is no signal. It
  never connects to PostgreSQL.
- `offline.html` — a friendly "you're offline" page.
- `js/pwa.js` — registers the service worker, runs the mobile menu drawer (the ☰ button in
  the top bar), and shows an **Install app** button when the browser offers one.

**Installing on a phone**

1. Serve the app somewhere the phone can reach it over **HTTPS**, or use it on
   **`localhost`** (service workers are disabled on plain `http://` to other devices — see
   the note below).
2. Open the site in Chrome (Android) or Safari (iPhone).
3. Android/Chrome: tap the **Install app** button, or browser menu → *Install app* /
   *Add to Home screen*. iPhone/Safari: **Share → Add to Home Screen**.
4. The app opens in its own window with the Green Valley icon and no address bar.

**What works offline**

Opening the app with no connection shows `offline.html`, and any page you *already visited
in that session* can still be re-opened from the cache. Live data (adding or editing
records) always needs the server, because PHP + PostgreSQL run there — so the app tells you
clearly when you're offline instead of showing stale or broken forms.

**Shared-device privacy.** On sign-out, `js/pwa.js` asks the service worker to wipe its
cached pages, so student records don't linger in the cache for the next person on a shared
school computer or phone.

> **The `localhost` / HTTPS rule.** Browsers only allow service workers on `https://` or on
> `http://localhost`. So the install/offline features work when you run
> `php -S localhost:8000` on the same machine, or once the app is deployed behind HTTPS. If
> you open the app from another phone via a plain `http://<your-ip>:8000` address, it still
> works as a normal website, but install/offline stay disabled until you add HTTPS. The app
> is fully functional either way — the PWA layer is a progressive enhancement.

---

## 10. Exam analytics reports (Python)

`tools/analytics.py` is an optional command-line tool that turns the marks already in the
database into a polished **PDF** report plus a **CSV**. It is written in Python and run from
a terminal by staff — never from the browser. It **reads** the existing `students`,
`subjects`, `classes`, `exams` and `results` tables and **stores nothing** back; every
figure is computed live, so no report data is duplicated in the database.

It reuses the **same `.env`** the PHP app uses, so the database password stays in one place
and is never copied into the script.

**Install the dependencies once:**

```bash
pip install -r tools/requirements.txt
```

(or on Arch, if you prefer system packages: `sudo pacman -S python-psycopg2 python-reportlab`)

**Run it from the project root:**

```bash
python3 tools/analytics.py                 # all examinations, top 10 students
python3 tools/analytics.py --list-exams    # list examinations with their ids
python3 tools/analytics.py --exam-id 3     # limit to one examination
python3 tools/analytics.py --top 20        # show the top 20 students
python3 tools/analytics.py --format pdf    # pdf | csv | both  (default: both)
python3 tools/analytics.py --self-test     # sample data, no database (preview the layout)
```

Reports are written to the `exports/` folder, e.g. `exports/analytics_all_20261002.pdf`.

**What's in the report**

- Overview counts (students, subjects, exams, marks recorded)
- A grade-distribution bar chart (KCSE 12-point A…E)
- Per-exam summary: entries, mean, lowest, highest, mean grade
- Subject performance and class performance, ranked by mean mark
- The top students by average mark

Grades use exactly the same bands as the app (`includes/functions.php`), so the Python
report and the on-screen report cards always agree.

---

## 11. Troubleshooting

- **"could not find driver"** — the `pdo_pgsql` PHP extension is not enabled. Enable it in
  `php.ini` and restart.
- **Connection refused / authentication failed** — check the values in `.env` and that
  PostgreSQL is running and accepting connections for user `mole`.
- **Blank page** — set `APP_ENV=development` in `.env` to see the error, and check your
  PHP error log.
- **Login always fails** — make sure `seed.sql` ran (so the `users` table has accounts),
  or create one with `create_admin.php`.
- **The "Install app" button never appears / offline doesn't work** — you are probably on a
  plain `http://` address to another device. Service workers need `https://` or
  `http://localhost` (see section 9). Also check the browser console for a service-worker
  error, and note iPhone installs via **Share → Add to Home Screen** rather than a button.
- **`python3 tools/analytics.py` says "No PostgreSQL driver found"** — install the
  dependencies first with `pip install -r tools/requirements.txt` (see section 10).
