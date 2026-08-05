# Report Content — HSB2006 Final Project

> Paste each section's content into the lecturer's report template as it is completed.
> Filled in progressively across phases (see CLAUDE.md roadmap) — do not leave until day 15.

## 1. Cover page and project identification
- Course: HSB2006 – Developing Business Applications, Class MET4
- Project title: Golden Lotus Restaurant — Reservation System
- Repo owner: Student ID 23080025 (GitHub: met23080025-ui) — *(full name + any
  teammates' names/IDs to be added once the team table in
  `docs/requirements.md` §11 is filled in)*

## 2. Executive summary and business problem
See `docs/requirements.md` §1–2 (Business problem, Project objectives). Summary:
Golden Lotus currently takes reservations only by phone into a shared paper
diary, causing double-bookings, no customer-facing record, wasted staff time,
and no historical data for planning. This system replaces that with an online
reservation flow with enforced conflict prevention and admin reporting.

## 3. Project scope, users, assumptions, and limitations
See `docs/requirements.md` §3–5 (Scope, Assumptions and limitations, Actors).
Two actors: Customer and Admin. Full in-scope/out-of-scope feature lists and
assumptions/limitations are documented there and must not be paraphrased
differently here — copy verbatim when assembling the final report.

## 4. Functional and non-functional requirements
See `docs/requirements.md` §6–7: 15 functional requirements (FR-01…FR-15) and 6
non-functional requirements (NFR-01…NFR-06) covering performance, security,
usability, compatibility, maintainability, and data integrity.

## 5. User stories, acceptance criteria, and Project board link
See `docs/requirements.md` §8–9, §12–13: 16 user stories (US-01…US-16), each
with Given/When/Then acceptance criteria, plus a traceability matrix mapping
every story to its FR and the marking-scheme criterion it evidences.
Project board link: *(add once created — each user story becomes one issue)*

## 6. Use Case Diagram, data dictionary, Sequence Diagram, and Activity Diagram

### 6.1 Use Case Diagram
Source: `docs/diagrams/use-case.mmd`

```mermaid
flowchart LR
    Customer((Customer))
    Admin((Admin))

    subgraph System["Golden Lotus Reservation System"]
        direction TB
        UC1(["Register (FR-01)"])
        UC2(["Login (FR-02)"])
        UC3(["Logout (FR-02)"])
        UC4(["Browse Table & Slot Availability (FR-03)"])
        UC5(["Make Reservation (FR-04)"])
        UC6(["View Reservation History (FR-05)"])
        UC7(["Cancel Reservation (FR-06)"])
        UC8(["Edit Profile / Change Password (FR-07)"])
        UC9(["View Dashboard (FR-08)"])
        UC10(["Search / Filter / Sort / Paginate Bookings (FR-09)"])
        UC11(["Approve / Reject Booking (FR-10)"])
        UC12(["Mark Completed / No-show (FR-11)"])
        UC13(["Manage Tables - CRUD (FR-12)"])
        UC14(["Manage Time Slots - CRUD (FR-13)"])
        UC15(["Manage Users (FR-14)"])
        UC16(["Generate Reports & Export CSV (FR-15)"])
    end

    Customer --- UC1
    Customer --- UC2
    Customer --- UC3
    Customer --- UC4
    Customer --- UC5
    Customer --- UC6
    Customer --- UC7
    Customer --- UC8

    Admin --- UC9
    Admin --- UC10
    Admin --- UC11
    Admin --- UC12
    Admin --- UC13
    Admin --- UC14
    Admin --- UC15
    Admin --- UC16

    UC5 -.->|"<<include>>"| UC4
    UC11 -.->|"<<include>>"| UC10
    UC12 -.->|"<<include>>"| UC10
    UC7 -.->|"<<extend>>"| UC6
    UC16 -.->|"<<extend>>"| UC9
```

Two actors, Customer and Admin, each with their own set of use cases, one per
functional requirement (FR-01…FR-15; login/logout is FR-02 split into two use
cases to mirror US-02/US-03). "Make Reservation" `<<include>>`s "Browse
Availability" because a booking can never be created without first running
the conflict/capacity check; "Approve/Reject" and "Mark Completed/No-show"
both `<<include>>` the booking list because the admin always locates a
booking there first. "Cancel Reservation" `<<extend>>`s "View Reservation
History" (cancelling is an optional action available while browsing history,
not something that happens every time), and "Generate Reports & Export CSV"
`<<extend>>`s "View Dashboard" (the full date-range report is an optional
deeper dive beyond the always-shown summary tiles).

### 6.2 Data dictionary
See `docs/data-dictionary.md` for the full table (`users`, `tables`,
`time_slots`, `reservations`) — column names, data types, constraints, and
descriptions, including the design note on `area` as an ENUM and the plan for
the double-booking uniqueness constraint that Phase P3 implements.

### 6.3 Activity Diagram — reservation workflow
Source: `docs/diagrams/activity-booking.mmd`

```mermaid
flowchart TD
    Start(["Start"])
    A1["Customer opens booking page"]
    D1{"Logged in?"}
    A2["Redirect to Login page"]
    A3["Select date, time slot, party size"]
    D2{"Date valid? (today .. +30 days, not in the past)"}
    A4["Show validation error"]
    A5["Search tables: capacity is sufficient AND no non-cancelled booking for that date/slot"]
    D3{"Table available?"}
    A6["Show 'no table available' message"]
    A7["Customer selects table and confirms"]
    A8["System creates reservation, status = pending"]
    A9["Admin reviews pending queue"]
    D4{"Admin decision"}
    A10["Status = confirmed, customer notified"]
    A11["Status = rejected, customer notified"]
    D5{"Customer cancels before reserved date/time?"}
    A12["Status = cancelled"]
    D6{"Reserved date/time has passed?"}
    A13["Admin marks completed or no_show"]
    EndNode(["End"])

    Start --> A1 --> D1
    D1 -- No --> A2 --> EndNode
    D1 -- Yes --> A3 --> D2
    D2 -- No --> A4 --> A3
    D2 -- Yes --> A5 --> D3
    D3 -- No --> A6 --> A3
    D3 -- Yes --> A7 --> A8 --> A9 --> D4
    D4 -- Reject --> A11 --> EndNode
    D4 -- Approve --> A10 --> D5
    D5 -- Yes --> A12 --> EndNode
    D5 -- No --> D6
    D6 -- Not yet --> D5
    D6 -- Passed --> A13 --> EndNode
```

Traces the whole booking lifecycle end to end, with explicit decision points
for every branch the grader can ask about at the viva: not logged in
(redirect), an invalid date (past or beyond 30 days, loops back to the form),
no table matching the party size (loops back to the form), the admin's
approve/reject fork, the customer's cancel-before-the-reserved-time check,
and the final admin action (`completed`/`no_show`) once the slot has passed.

### 6.4 Sequence Diagram — booking submission through admin approval
Source: `docs/diagrams/sequence-booking.mmd`

```mermaid
sequenceDiagram
    actor Customer as Customer (Browser)
    participant Book as book.php
    participant Valid as helpers/validation
    participant DB as PDO / Database
    participant AdminPage as admin/bookings.php
    actor AdminUser as Admin

    Customer->>Book: POST reservation form (date, slot, party_size, notes, csrf_token)
    Book->>Book: verify CSRF token

    alt CSRF token invalid
        Book-->>Customer: flash error, reload form
    else CSRF token valid
        Book->>Valid: validate_reservation_input(date, slot, party_size)
        Valid-->>Book: validation result

        alt validation fails
            Book-->>Customer: flash validation error, redisplay form
        else validation passes
            Book->>DB: SELECT tables WHERE capacity >= party_size AND NOT EXISTS conflicting reservation
            DB-->>Book: list of available tables

            alt no table available
                Book-->>Customer: flash "no table available for this date/slot"
            else table available
                Book->>DB: INSERT INTO reservations (..., status = 'pending')

                alt uq_reservations_active_slot violated (concurrent duplicate booking)
                    DB-->>Book: constraint error on active_slot_key (table/date/slot already taken)
                    Book-->>Customer: flash "table was just booked, choose another"
                else insert succeeds
                    DB-->>Book: new reservation id
                    Book-->>Customer: flash "reservation submitted, pending approval"
                end
            end
        end
    end

    AdminUser->>AdminPage: GET pending bookings queue
    AdminPage->>DB: SELECT reservations WHERE status = 'pending'
    DB-->>AdminPage: pending rows
    AdminPage-->>AdminUser: render pending queue

    AdminUser->>AdminPage: POST approve(reservation_id) + csrf_token
    AdminPage->>AdminPage: verify CSRF token
    AdminPage->>DB: UPDATE reservations SET status='confirmed', actioned_by, actioned_at WHERE id=? AND status='pending'
    DB-->>AdminPage: rows affected
    AdminPage-->>AdminUser: flash "booking confirmed"

    Customer->>Book: GET my bookings
    Book->>DB: SELECT reservations WHERE user_id = ?
    DB-->>Book: rows including updated status
    Book-->>Customer: render booking as "confirmed"
```

Shows the object-level round trip the code in Phase P6 must implement:
`book.php` checks CSRF before anything else, then delegates validation to a
shared helper, then queries availability, then inserts with the `pending`
status. The nested `alt` block on the INSERT is the concurrency case the exam
specifically requires — a second, near-simultaneous booking for the same
table/date/slot is rejected by the database's own unique constraint, not just
the PHP check, which is the proof point for NFR-06. The lower half shows the
admin approving from the pending queue and the customer's next page load
reflecting the new `confirmed` status.

## 7. Application architecture, technology stack, and database schema

**Technology stack:** PHP 8.x (procedural/lightweight OOP, no framework),
MySQL 8/MariaDB via XAMPP, PDO with prepared statements for all queries,
HTML5 + CSS3 + Bootstrap 5 (CDN) + vanilla JavaScript. No Node.js, no
Firebase, no ORM.

**Database schema:** `database/schema.sql` creates the `golden_lotus`
database (`utf8mb4_unicode_ci`) with four tables — `users`, `tables`,
`time_slots`, `reservations` — exactly as specified in
`docs/data-dictionary.md`. Foreign keys use `ON DELETE RESTRICT` for
`user_id`/`table_id`/`time_slot_id` (records are deactivated via `is_active`,
never deleted, so history is preserved) and `ON DELETE SET NULL` for
`actioned_by`. Indexes: `reservations(reservation_date)`,
`reservations(status)`, and the existing `UNIQUE` on `users.email`.

**Double-booking constraint:** enforced by a `STORED` generated column,
`reservations.active_slot_key`, which evaluates to `NULL` when `status` is
`cancelled`/`rejected` and to `CONCAT(table_id,'_',reservation_date,'_',
time_slot_id)` otherwise, with `UNIQUE KEY uq_reservations_active_slot` on
that column. This makes the conflict check atomic at the database level
(no check-then-write race under concurrent bookings) while still letting a
cancelled/rejected slot be re-booked. Full rationale and the trigger-based
alternative that was considered and rejected: `docs/data-dictionary.md` and
the Vietnamese comment block above `CREATE TABLE reservations` in
`database/schema.sql`. Live proof that this constraint actually fires against
the seeded database (verbatim MySQL error, `information_schema` output, and
exact reproduction steps for the demo/viva): `docs/evidence/double-booking-proof.md`.

**Seed data:** `database/seed.sql` seeds 1 admin + 6 customer accounts (all
sharing the demo password `Password123!`, stored as a real bcrypt hash — see
README "Test accounts"), the 20 tables and 7 time slots locked in
`CLAUDE.md`, and 57 reservations spanning `CURDATE() - 14 days` through
`CURDATE() + 7 days` (computed relative to import time, not hard-coded, so
the demo data stays current no matter when the file is re-imported) with a
realistic status mix and a deliberate pending backlog on the next 2 days for
the admin queue demo.

**Application layer infrastructure (Phase P4b):** every page shares five
`includes/` files, required in this order: `db.php` (opens the PDO
connection — `ERRMODE_EXCEPTION`, `EMULATE_PREPARES` off, default fetch
mode `FETCH_ASSOC`), `helpers.php` (starts the session; `e()` for
`htmlspecialchars` output escaping; `set_flash()`/`get_flashes()` for
one-time Bootstrap alert messages; `csrf_token()`/`csrf_field()`/
`csrf_verify()`; `redirect()`; `is_valid_email()`/`is_strong_password()`
for registration validation), and `auth.php` (the role middleware:
`current_user()`/`is_logged_in()`/`is_admin()` read `$_SESSION['user']`,
and `require_login()`/`require_admin()` flash a message and redirect when
the check fails — pages that need protection call these before any HTML
output). `header.php`/`footer.php` render the shared Bootstrap 5 (CDN)
shell: a role-aware navbar (guest/customer/admin see different links),
the flash-message region, and the closing script tags
(`bootstrap.bundle.min.js` + `public/js/main.js`). `auth.php` only reads
session state — it does not depend on the login flow itself, which is
built in Phase P5 (`auth/login.php` will write `$_SESSION['user']` after
`password_verify()` succeeds). `index.php` was wired up to this shell as
the first real page, proving the include chain end-to-end.

**The core business workflow (Phase P6 — `includes/reservation.php`,
`customer/book.php`, `customer/my-reservations.php`, `admin/bookings.php`):**
all reservation logic lives in five functions in `includes/reservation.php`,
so every page that touches a reservation's availability or status goes
through the same code rather than re-implementing it:

- `get_available_tables()` — active tables with sufficient capacity and no
  conflicting `pending`/`confirmed` booking for the requested date/slot,
  ordered by capacity ascending (smallest sufficient table first, per the
  locked business rule — keeps large tables free for large parties).
- `is_slot_bookable()` — rejects past dates, dates past the 30-day window,
  and (for today) slots whose start time has already passed, always
  against server time (`DateTimeImmutable('now')`), never client input.
- `create_reservation()` — wraps the `INSERT` in a transaction and catches
  a `uq_reservations_active_slot` violation (`SQLSTATE[23000]`/MySQL 1062)
  as a normal "that table was just taken" result rather than letting a
  500 error surface; see `docs/evidence/double-booking-proof.md` §7 for
  the full UI-level reproduction and an important nuance discovered while
  writing it: `customer/book.php` also re-checks availability with a plain
  `SELECT` immediately before calling this function, so in practice that
  cheaper check catches almost every real double-submit before the
  `INSERT` is even attempted — the constraint-violation path this function
  handles is the last-resort net for the narrower race between that
  `SELECT` and the `INSERT`, which is what the CLI-based proof in that same
  document exercises directly.
- `can_transition()` — the single source of truth for the status lifecycle
  locked in `CLAUDE.md` (`pending → confirmed|rejected|cancelled`,
  `confirmed → completed|no_show|cancelled`, all other states terminal).
  Every status change, from either the customer (cancel) or admin
  (approve/reject/complete/no-show) side, is routed through
  `change_reservation_status()`, which calls this — no page updates
  `reservations.status` directly.
- `change_reservation_status()` — validates via `can_transition()`, locks
  the target row with `SELECT ... FOR UPDATE` inside a transaction (so two
  near-simultaneous status changes on the same reservation can't both read
  the same stale status), records `actioned_by`/`actioned_at`.

`customer/book.php` follows the search-then-select flow locked in
`docs/design-process.md` §4.3 (date/party size/slot chosen together before
searching; table chosen only from the results) via GET for the idempotent
search and POST for the state-changing confirm (PRG pattern per §7),
re-validating the submitted `table_id` against a fresh
`get_available_tables()` call rather than trusting the form. Both
`customer/my-reservations.php`'s cancel action and `admin/bookings.php`'s
approve/reject/complete/no-show actions enforce their eligibility rules
(ownership, status, and — for cancel — that the reservation is still in
the future) server-side, not merely by hiding the button in the UI.

## 8. Implementation evidence — annotated screenshots
*(fill progressively Phases P5-P7 — both customer and admin functions)*

**Phase P7 status (2026-08-05):** FR-09…FR-15 are implemented and smoke-tested
end-to-end (see `docs/test-plan.md` for formal cases to be logged in Phase
P8): `admin/bookings.php` (search/filter/sort/pagination on top of the P6
approval queue), `admin/tables.php` and `admin/timeslots.php` (full CRUD,
deactivate-instead-of-delete when reservations reference the row, overlap
validation for time slots), `admin/users.php` (search/role filter,
activate/deactivate, role change, self-protection enforced server-side),
`admin/dashboard.php` (four live-SQL summary tiles + pending-queue preview),
`admin/reports.php` (date-range stats, HTML/CSS bar charts, CSV export), and
`customer/dashboard.php` (next reservation, status counts, recent history).
Screenshots for this section still need to be captured manually per the
reminder below — nothing here substitutes for that.

## 9. Security controls and input-validation approach

**Authentication (Phase P5 — `auth/register.php`, `auth/login.php`,
`auth/logout.php`, `customer/profile.php`):**

- Passwords are hashed with `password_hash($password, PASSWORD_DEFAULT)`
  and checked with `password_verify()`; the plaintext value is never
  logged, echoed, or stored. Registration enforces a minimum-strength rule
  (`is_strong_password()` in `includes/helpers.php`: ≥8 characters, at
  least one letter and one digit) server-side, in addition to the
  client-side `minlength` hint.
- Login returns the same generic *"Invalid email or password."* message
  whether the email doesn't exist or the password is wrong, and always
  calls `password_verify()` against a dummy bcrypt hash when no matching
  user is found — both measures defend against user enumeration (an
  attacker probing which emails are registered), the second one closing
  the timing side-channel the first alone wouldn't.
- A successful login calls `session_regenerate_id(true)` immediately
  (session-fixation defence) before writing `$_SESSION['user']`. A locked
  account (`is_active = 0`) is refused login with a distinct, clear
  message once credentials are otherwise correct.
- **Open-redirect defence:** the homepage's "Book a Table" CTA sends
  signed-out visitors to `auth/login.php?redirect=/customer/book.php` so
  they land back where they meant to go after authenticating. That
  `redirect` value is attacker-controllable input, so it is never used
  directly — `safe_redirect_target()` (`includes/helpers.php`) only
  accepts it if it starts with a single `/` (rejects `//host` protocol-relative
  URLs), contains no `://` or scheme prefix, and contains no backslash or
  control characters; anything else silently falls back to the
  role-based dashboard. Manually verified with three payloads
  (`https://evil.example`, `//evil.example`, `/\evil.example`) — all three
  are dropped and the login instead lands on the normal dashboard; see the
  full Vietnamese rationale comment above the function.
- Every state-changing form (register, login, logout, profile update,
  password change) carries a CSRF token (`csrf_field()`/`csrf_verify()`,
  `hash_equals()` comparison) checked before any other processing.
- `customer/dashboard.php` calls `require_login()` and
  `admin/dashboard.php` calls `require_admin()` — manually verified that
  an unauthenticated request to either is redirected to login, and that a
  logged-in customer hitting `admin/dashboard.php` is redirected away
  rather than shown the page.
- Profile email edits re-check uniqueness excluding the user's own row
  (`WHERE email = ? AND id != ?`); password changes require the correct
  current password (`password_verify()`) before a new hash is written.

*(remaining sections — input validation for the booking workflow, admin
CRUD, and the full defect list — fill progressively through Phase P8.)*

Data-integrity control already captured ahead of P8: `docs/evidence/double-booking-proof.md`
documents the live database-level proof that the double-booking constraint
(the `uq_reservations_active_slot` unique index on the generated column
`active_slot_key`) rejects a direct duplicate-insert attempt with MySQL error
1062, run inside a rolled-back transaction against the real seeded database —
not just a design claim. Includes exact reproduction steps to re-run live
during the demo/viva.

## 10. Test plan, test cases, results, and unresolved defects
*(fill Phase P8)*

## 11. Installation/setup guide, test accounts, repository link, and live/local application link
*(fill Phase P9)*

## 12. References, third-party assets, and AI/tool-use declaration
*(fill Phase P9 — include the AI usage declaration table)*
