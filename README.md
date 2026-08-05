# Golden Lotus Restaurant — Reservation System

> HSB2006 – Developing Business Applications (Class MET4) final project.
> Authentic Vietnamese dining — reserve your table in seconds.

## Project purpose

A database-driven web application that replaces Golden Lotus's paper-diary
phone-booking process. Customers browse table/time-slot availability for a
chosen date and party size, book a reservation online with automatic
double-booking prevention, and manage their own booking history. Admins run
the whole reservation book from a dashboard: approve/reject bookings, mark
completed/no-show, manage tables and time slots, manage user accounts, and
pull date-range statistics with CSV export. See `docs/requirements.md` for
the full business problem, scope, and requirements this system was built
against.

## Features

**Customer**
- Register / log in / log out, with server-side email-format and
  password-strength validation
- Edit profile and change password (requires the current password)
- Browse tables and time slots for a chosen date (today .. +30 days) and
  party size — results filtered to sufficient capacity and no conflicting
  booking, smallest sufficient table listed first
- Make a reservation (date, time slot, party size, optional notes), with
  automatic conflict detection enforced both in PHP and at the database level
- Dashboard: next upcoming reservation, quick "Book a Table" link, booking
  counts by status, recent history
- View and filter own reservation history by status
- Cancel a `pending`/`confirmed` booking while its date/time is still in
  the future

**Admin**
- Dashboard: today's bookings, pending count, cancellation rate, busiest
  time slot — all computed live from SQL on every page load — plus a
  pending-queue preview with one-click approve/reject
- Booking list with keyword search (customer name/email), filters
  (status, area, date range), sortable columns, and pagination — filters
  persist through actions and are bookmarkable via the URL query string
- Approve / reject `pending` bookings; mark a past `confirmed` booking
  `completed` or `no_show`
- Full CRUD for tables (code, capacity, area, active status) and time
  slots (start/end time, active status), with validation and a
  deactivate-instead-of-delete safeguard once a row has reservation history
- User management: search, filter by role, activate/deactivate accounts,
  change roles — an admin cannot deactivate or demote their own account
- Reports: date-range booking statistics (per-day, by status, by area,
  average party size, busiest slots ranked) rendered as HTML/CSS bar
  charts, plus CSV export of the filtered result set

## Technology stack

- PHP 8.x (procedural / lightweight OOP, no framework)
- MySQL 8 / MariaDB (via XAMPP)
- PDO with prepared statements for 100% of database access (native
  prepares, `PDO::ATTR_EMULATE_PREPARES => false`)
- HTML5, CSS3, Bootstrap 5 (CDN), vanilla JavaScript
- No Node.js, no Firebase, no ORM

## Prerequisites

- [XAMPP](https://www.apachefriends.org/) with Apache and MySQL/MariaDB
- PHP 8.x (developed and verified against PHP 8.0.30; no version-specific
  syntax beyond PHP 8.0 is used — see "Known limitations")
- A modern browser (current stable Chrome, Firefox, or Edge)

## Verified local environment

The environment below has been verified working end-to-end (PDO
connection, `password_verify()`, the database-level double-booking
constraint, and the full Phase P7 admin/customer feature set all confirmed
live against a real Apache+MySQL run — see
`docs/evidence/double-booking-proof.md`):

- XAMPP installed at `C:\xampp` (Apache + MySQL/MariaDB)
- PHP 8.0.30 (`C:\xampp\php\php.exe`)
- MySQL/MariaDB via XAMPP, database `golden_lotus`
- Project served at `http://localhost/golden-lotus`
- `BASE_URL` in `config.php` set to `/golden-lotus`

## Installation (local, XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** + **MySQL**
   from the XAMPP control panel.
2. Clone this repository into your XAMPP `htdocs` folder, e.g.
   `C:\xampp\htdocs\golden-lotus` (or `/Applications/XAMPP/htdocs/golden-lotus`
   on macOS/Linux).
3. Copy `config.sample.php` to `config.php` and adjust the DB credentials if your
   XAMPP MySQL setup differs from the defaults (`root` / empty password).
   `config.php` is `.gitignore`d — never commit real credentials.
4. Import the database — see next section.
5. Visit `http://localhost/<folder-name>/` in your browser (adjust `BASE_URL` in
   `config.php` to match your folder name if it differs from `golden-lotus`).

## Database import

1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a new database named `golden_lotus` (or matching `DB_NAME` in
   `config.php`).
3. Import `database/schema.sql` first (creates all four tables, foreign
   keys, and the double-booking unique constraint).
4. Import `database/seed.sql` second (adds the 20 tables, 7 time slots, 1
   admin + 6 customer test accounts, and ~57 demo reservations spanning the
   14 days before import through 7 days after — computed relative to
   `CURDATE()` at import time, so the dataset stays current no matter when
   you re-import it).

## Test accounts

All seeded accounts share the same demo password: **`Password123!`**
(stored only as a real bcrypt hash in `database/seed.sql`, never
plaintext — see "Known limitations" below for why every seeded account's
hash looks byte-identical, and why that's expected).

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@goldenlotus.test | Password123! |
| Customer | customer1@goldenlotus.test | Password123! |
| Customer | customer2@goldenlotus.test | Password123! |
| Customer | customer3@goldenlotus.test | Password123! |
| Customer | customer4@goldenlotus.test | Password123! |
| Customer | customer5@goldenlotus.test | Password123! |
| Customer | customer6@goldenlotus.test | Password123! |

## Known limitations

- **PHP 8.0.30 end-of-life:** the development environment runs PHP 8.0.30,
  which has reached its official end-of-life. The codebase only targets
  PHP 8.x language syntax with no version-specific features beyond 8.0
  (verified by inspection — no enums, no readonly properties, no other
  8.1+-only syntax anywhere in the codebase), so it is expected to run
  unmodified on a currently supported PHP 8.2+ release.
- **Identical seed password hashes:** every account in `database/seed.sql`
  shares the exact same bcrypt hash string. This is a property of how the
  static seed SQL was written (the same real `password_hash()` output was
  reused across all 7 `INSERT` rows for convenience), not a defect in the
  hashing logic itself — `password_hash()` salts randomly on every call,
  so two accounts registered for real through `auth/register.php` with the
  identical password get two different-looking hashes, as expected.
  Full explanation: `docs/security-review.md` §3 and
  `docs/viva-preparation.md` Q6.
- No online payment/deposit collection, no automated email/SMS
  notifications, no multi-branch support, no waitlist, no table-combining
  for oversized parties, no full audit-log history beyond a booking's
  current status field — all deliberately out of scope; see
  `docs/requirements.md` §3 and §10 for the complete list and rationale.
- One known business-logic gap under active review: `can_transition()`
  (`includes/reservation.php`) permits a `confirmed → completed/no_show`
  transition regardless of whether the booking's date/time has actually
  passed — the UI only exposes the relevant buttons once it has, but a
  forged direct request could act early. Tracked as TC-28 in
  `docs/test-plan.md`; see that file's defect table for current status.

## Third-party assets and licences

- [Bootstrap 5.3.3](https://getbootstrap.com/) — MIT License, loaded via
  `cdn.jsdelivr.net`, no local copy vendored.
- No other third-party libraries, icon sets, fonts, or images are used.

## Repository & project management

- GitHub repository:
  https://github.com/met23080025-ui/FINAL-EXAMINATION-HSB2006-DEVELOPING-BUSINESS-APPLICATIONS
- GitHub Project board: *(link — add once created; see
  `docs/remaining-work.md` item 7)*

## Documentation index

| Document | Purpose |
|---|---|
| `CLAUDE.md` | Full project brief, locked business rules, 15-day roadmap |
| `docs/requirements.md` | Business problem, scope, functional/non-functional requirements, user stories |
| `docs/data-dictionary.md` | Full schema reference with design rationale |
| `docs/design-process.md` | UI/UX design system — colours, type, spacing, conventions |
| `docs/diagrams/` | Use case, activity, and sequence diagrams (Mermaid source) |
| `docs/evidence/double-booking-proof.md` | Live proof the double-booking constraint fires, plus reproduction steps |
| `docs/security-review.md` | Every security control, referenced to its implementing file |
| `docs/test-plan.md` | 43 test cases with steps/expected results, and the defect log |
| `docs/screenshot-checklist.md` | Every screenshot needed for the report, with setup/annotation notes |
| `docs/viva-preparation.md` | 20 likely examiner questions with answers and file pointers |
| `docs/remaining-work.md` | Ordered checklist of everything left before submission |
| `docs/development-log.md` | Phase-by-phase development history and AI-usage record |
| `docs/report-content.md` | Draft content for the final report, section by section |

## Team

See `docs/requirements.md` §11 for the team contribution table (to be
completed — see `docs/remaining-work.md` item 6).

## Development status

This project follows a 15-day phased roadmap documented in `CLAUDE.md`.
Phases P0–P7 (project setup through admin CRUD/reporting) are complete;
Phase P8 (testing/security writeup) and P9 (packaging/submission) remain —
see `docs/remaining-work.md` for the exact checklist and time estimates.
