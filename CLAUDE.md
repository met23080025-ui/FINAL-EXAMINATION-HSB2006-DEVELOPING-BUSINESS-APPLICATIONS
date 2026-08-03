# CLAUDE.md — Golden Lotus Restaurant Reservation System (HSB2006 Final Project)

This file is the persistent context for every future Claude Code session in this repo.
Read it fully before doing any work. Do not skip phases. Do not add features beyond
the locked scope below.

## Course & submission identity

- Course: HSB2006 – Developing Business Applications, class MET4 (Hanoi School of
  Business and Management, VNU)
- Graded project, 100 points, due 15 days from project start
- Repo owner / student ID: **23080025**, class **MET4**, GitHub account
  **met23080025-ui**
- Repository: https://github.com/met23080025-ui/FINAL-EXAMINATION-HSB2006-DEVELOPING-BUSINESS-APPLICATIONS
- Final submission filenames (`<FullName>` still to be filled in — Phase P9):
  - `HSB2006_MET4_23080025_<FullName>_Report.pdf`
  - `HSB2006_MET4_23080025_<FullName>_Source.zip`
  - `HSB2006_MET4_23080025_<FullName>_Database.sql`

**Timeline note**: My Claude Pro plan expires 2026-08-05. Phases P1-P7 must be
completed by then. Prioritise working code over polish.

## Team

*(fill in `docs/requirements.md` and here as soon as known)*

| # | Full name | Student ID | Primary responsibility |
|---|-----------|------------|------------------------|
| 1 |           |            | Requirements & documentation |
| 2 |           |            | Database design & backend CRUD |
| 3 |           |            | Reservation workflow & admin panel |
| 4 |           |            | UI/UX design & frontend |
| 5 |           |            | Testing, security review & packaging |

Every member must commit under their own GitHub account and be assigned issues on
the Project board — individual contribution affects individual marks.

## Business context

Golden Lotus Restaurant (Vietnamese: Nhà hàng Sen Vàng) — fictional mid-range
Vietnamese restaurant in Hanoi. Homepage tagline: "Authentic Vietnamese dining —
reserve your table in seconds." Two roles: **customer** and **admin**. Interface
language: English. Code comments: Vietnamese (see Coding Rules).

## Locked business parameters (confirmed by user — do not re-derive or change without asking)

- **Opening hours**: daily 11:00–22:00, no closing day.
- **Time slots**: 90 minutes each, 7 fixed slots/day, stored in a `time_slots` table
  (id, start_time, end_time, is_active) so admin can toggle them via CRUD:
  1. 11:00–12:30  2. 12:30–14:00  3. 14:00–15:30  4. 15:30–17:00
  5. 17:00–18:30  6. 18:30–20:00  7. 20:00–21:30
- **Booking window rules**: no booking in the past (validate against server time);
  bookings allowed up to 30 days in advance.
- **Double-booking constraint**: one table holds at most one non-cancelled
  reservation per (date, time_slot). Must be enforced in PHP AND via a DB-level
  constraint (e.g. unique index on (table_id, booking_date, time_slot_id) scoped to
  non-cancelled/non-rejected statuses, or an equivalent trigger — decide in Phase P3).
- **Tables**: 20 total across 4 areas (store `area` as ENUM or lookup table — pick
  one in Phase P3 and document the choice in the data dictionary):

  | Area | Table numbers | Count | Capacity |
  |------|---------------|-------|----------|
  | Indoor Main | T01–T08 | 8 | T01–T04 seat 2, T05–T08 seat 4 |
  | Terrace | T09–T13 | 5 | T09–T11 seat 4, T12–T13 seat 6 |
  | Garden | T14–T17 | 4 | T14–T15 seat 6, T16–T17 seat 8 |
  | VIP Room | V01–V03 | 3 | V01–V02 seat 8, V03 seats 12 |

  Table search must filter `capacity >= party_size` and prefer the smallest
  sufficient table (so large tables stay free for large parties).
- **Test accounts** (seeded in `database/seed.sql`, documented in README):
  - Admin: `admin@goldenlotus.test`
  - Customer 1–6: `customer1@goldenlotus.test` … `customer6@goldenlotus.test`
  - Shared password: `Password123!` (stored only as a bcrypt hash, `$2y$`
    prefix, never plaintext)
- **Seed volume**: ~40–60 reservations spanning the past 14 days through the next 7
  days, realistic mix of all statuses, so dashboard/reports show meaningful demo data.
- **No payments/currency** — reservation only, no billing.

## Mandatory tech stack (do not change)

PHP 8.x (procedural or lightweight OOP, no frameworks) · MySQL 8/MariaDB via XAMPP ·
PDO with prepared statements for 100% of queries · HTML5 + CSS3 + Bootstrap 5 (CDN) +
vanilla JS · No Node.js, no Firebase, no ORM.

## Verified local environment

Confirmed working during Phase P3 verification (2026-08-03) — PDO connection,
`password_verify()`, and the database-level double-booking constraint all
proven live; see `docs/evidence/double-booking-proof.md`:

- XAMPP installed at `C:\xampp` (Apache + MySQL/MariaDB running)
- PHP 8.0.30 at `C:\xampp\php\php.exe`
- MySQL/MariaDB via XAMPP, database `golden_lotus`
- Project served at `http://localhost/golden-lotus`
- `BASE_URL` in `config.php` set to `/golden-lotus`

## Marking scheme (guides every decision)

| # | Criterion | Pts |
|---|-----------|-----|
| 1 | Project Management & Requirements | 10 |
| 2 | System Diagrams (Use Case, Activity, Sequence) | 15 |
| 3 | UI/UX & Frontend Design | 15 |
| 4 | Database & Backend Development | 45 |
| 5 | Testing, Security, Packaging, Demo | 15 |

**Golden rule**: a feature that cannot be demonstrated running earns zero
implementation marks, even if described in the report. Prefer fewer features that
work perfectly over many half-finished ones.

## Feature scope (locked — do not add more)

**Customer**: register (validate email + password strength) · login/logout ·
browse tables/slots for a date · make a reservation (date, slot, party size, notes
→ conflict check → status `pending`) · view own reservation history (filterable by
status) · cancel own booking (only while `pending`/`confirmed`) · edit profile /
change password.

**Admin**: dashboard (today's bookings, pending count, cancellation rate, busiest
slot) · booking list with search + filter (date, status, customer name) + sort
(time, created date) + pagination · approve/reject bookings · mark
completed/no_show after reserved time · CRUD tables · CRUD time slots · user
management (view, lock/unlock, change role) · reports (date-range stats, CSV export).

## Status lifecycle

`pending → confirmed → completed | no_show`
`pending | confirmed → cancelled | rejected`

## The core business workflow (most important part of the grading)

Customer logs in → selects date + slot + party size → system filters tables with
sufficient capacity and no conflicting booking → customer picks table, confirms →
booking created (`pending`) → admin approves (`confirmed`) or rejects (`rejected`)
→ customer sees updated status → after reserved time, admin marks
`completed`/`no_show` → customer may cancel any time before the reserved time
(while `pending`/`confirmed`).

## 15-day roadmap (do not skip ahead; confirm each phase before starting the next)

| Phase | Days | Deliverable |
|---|---|---|
| P0 | 1 | Folder structure, README, .gitignore, config.sample.php, git init |
| P1 | 1–2 | docs/requirements.md full content, GitHub Project board + issues |
| P2 | 3–4 | Mermaid diagrams (use case, activity, sequence) + data dictionary |
| P3 | 4–5 | database/schema.sql + database/seed.sql |
| P4 | 5 | docs/design-process.md (mandatory design workflow doc) |
| P4b | 5–6 | PDO connection, header/footer, flash messages, validation helpers, role middleware, style.css |
| P5 | 6–7 | Register, login, logout, password hashing, session protection, RBAC |
| P6 | 7–10 | Full reservation flow + conflict prevention + admin approval |
| P7 | 10–12 | CRUD (tables/slots/users) + search/filter/sort/pagination + stats + CSV export |
| P8 | 12–13 | Security review + docs/test-plan.md (25+ cases) + known unresolved defects |
| P9 | 14–15 | README complete, DB export, zip package, docs/report-content.md complete, AI declaration, demo checklist |

## Mandatory coding rules

**Security**: every query is a PDO prepared statement with bound parameters, never
string-concatenated SQL · `password_hash()`/`password_verify()`, never plaintext ·
all rendered data passes through `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')` ·
CSRF token on every POST form · `session_regenerate_id(true)` right after login ·
validate client-side (UX) AND server-side (security, never trust client alone) ·
never commit real credentials — only `config.sample.php` is tracked, `config.php`
is gitignored.

**Code quality**: comments in **Vietnamese** for every function/non-obvious block
(student must explain code at viva) · variable/function names in English,
snake_case · separate logic (queries in `includes/` or top of file) from
presentation (HTML at bottom) · never paste unexplained code — record source/licence
of any external snippet in README · for every new PHP file, explain: what it does,
where it's called from, how data flows through it.

**Git**: small frequent commits (`feat: ...` / `fix: ...` style) · commit at least
once per day — a single end-of-project bulk upload forfeits project-management
marks · never commit `config.php`, `node_modules/`, `.zip` files, personal data, or
large screenshots.

## Expected folder structure

See repo root — matches the structure created in Phase P0 (`includes/`, `public/`,
`auth/`, `customer/`, `admin/`, `database/`, `docs/` incl. `docs/diagrams/`).

## Report structure (docs/report-content.md — 12 sections, fixed order)

Cover page → Executive summary/business problem → Scope/users/assumptions →
Functional/non-functional requirements → User stories/acceptance criteria/Project
board link → Diagrams + data dictionary → Architecture/tech stack/DB schema →
Implementation evidence (screenshots) → Security controls → Test plan/results/
defects → Setup guide/test accounts/links → References/third-party/AI declaration.

Fill progressively each phase — do not leave until day 15. AI usage declaration
table (tool name, purpose, parts assisted, how output was verified) goes in section
12; using AI does not transfer responsibility for correctness/security/licensing/
originality.

## How Claude should work in this repo

- Start each new phase only after the user confirms the previous one is complete.
- At the end of each phase: summarise what was done, propose a commit message,
  remind to commit, and ask whether to proceed to the next phase.
- If a requirement is ambiguous, ask — don't decide unilaterally.
- When the user reports a bug: explain the root cause first, then fix it.
- Warn immediately if a request would drift from the rubric or introduce a
  security risk.
- Periodically remind the user to take annotated screenshots (customer + admin
  interfaces) for the report.
- After each phase, update `docs/report-content.md`'s corresponding section.
- Maintain three-way consistency: requirements ↔ diagrams ↔ code. If a feature
  changes mid-project, update requirements and diagrams too.
