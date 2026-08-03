# Requirements — Golden Lotus Restaurant Reservation System

HSB2006 – Developing Business Applications, Class MET4. This document is the
Phase P1 deliverable and feeds directly into `docs/report-content.md` sections
3–5. All requirements below are consistent with the locked business rules in
`CLAUDE.md` (opening hours 11:00–22:00, seven fixed 90-minute time slots, 20
tables across 4 areas, the booking status lifecycle, and the double-booking
constraint). No feature outside that locked scope is introduced here.

---

## 1. Business problem

Golden Lotus Restaurant currently takes table reservations only by phone, logged
by whichever staff member answers the call into a shared paper diary at the host
stand. This causes four recurring problems:

1. **Double-bookings** — two staff members can independently accept a booking for
   the same table and time slot because there is no single, real-time source of
   truth.
2. **No customer-facing record** — customers have no way to see the status of
   their booking or their booking history; they must call back to confirm or
   cancel.
3. **Staff time cost** — every booking, cancellation, and status change requires
   a phone call and a manual diary entry, taking staff time away from service.
4. **No data for planning** — because bookings are only ever recorded on paper,
   management has no historical data on occupancy, busiest time slots, or
   cancellation rates to plan staffing or table layout.

## 2. Project objectives

1. Eliminate double-bookings by enforcing table/date/time-slot conflict checks
   both in the application layer and as a database constraint, so no two
   non-cancelled bookings can ever occupy the same table in the same slot.
2. Let a customer create a reservation online, from browsing availability to
   confirmation, without any phone call or staff involvement.
3. Give admin staff a single dashboard and booking list where the full daily
   queue of pending reservations can be reviewed and approved/rejected without
   consulting a paper diary.
4. Produce accurate, exportable (CSV) booking statistics over any date range, so
   management has historical data for staffing and capacity planning — data that
   does not exist today.
5. Meet the exam's security requirements end-to-end: 100% of database queries
   use parameterised PDO statements, all passwords are hashed, and all state-
   changing forms are CSRF-protected.

## 3. Scope

### In scope
- Customer account registration, login, logout, profile edit, password change
- Browsing table/time-slot availability for a selected date and party size
- Creating a reservation with automatic conflict detection (status `pending`)
- Viewing and filtering own reservation history; cancelling own `pending` or
  `confirmed` bookings
- Admin dashboard with key booking metrics
- Admin booking list with search, filter, sort, and pagination
- Admin approve/reject of pending bookings; marking `completed`/`no_show`
- Admin CRUD for tables and for time slots
- Admin user management (view, lock/unlock, change role)
- Admin reporting over a date range with CSV export

### Out of scope
- Online payment or deposit collection
- Food ordering or delivery
- Loyalty/rewards programme
- Multi-branch / multi-restaurant support
- Automated email/SMS notifications or reminders
- Native mobile app
- Live chat / customer support messaging
- Multi-language interface (English only)
- POS or accounting system integration
- A waitlist feature for fully booked slots
- Table-combining for parties larger than any single table's capacity

## 4. Assumptions and limitations

**Assumptions**
- Golden Lotus operates from a single physical location.
- All dates/times are in the restaurant's local timezone; no timezone
  conversion is required.
- One table seats exactly one party per time slot — tables are never combined
  or split.
- Admin accounts are provisioned in advance (seeded/created by an existing
  admin); there is no public admin self-registration.
- Customers use a modern browser with JavaScript enabled for client-side
  validation, but server-side validation is authoritative regardless.
- The system runs on XAMPP (PHP 8.x + MySQL 8/MariaDB) for development and
  demo purposes; production hosting is not required for the exam.

**Limitations**
- No automated reminder notifications (email/SMS) before a reservation.
- No support for parties whose size exceeds the largest table's capacity (12,
  the VIP room table V03).
- Admins cannot cancel a booking on a customer's behalf — only approve, reject,
  or mark completed/no_show; only the customer can cancel their own booking.
- No detailed audit/change-history log beyond the booking's current status
  field (no "who changed what and when" trail).
- Concurrency protection relies on a database-level constraint and has not been
  load-tested beyond the exam demo dataset.

## 5. Actors

### Customer
A member of the public who wants to dine at Golden Lotus. Goal: find a
suitable table for a chosen date, time, and party size, and book it in under a
couple of minutes without calling the restaurant. Wants to see the current
status of a booking (pending/confirmed/etc.) and be able to cancel it if plans
change, all without staff involvement.

### Admin
Restaurant staff/manager responsible for running the reservation book day to
day. Goal: see the day's bookings at a glance, clear the pending queue quickly
(approve or reject), keep the table list, time slots, and user accounts
accurate, and pull historical statistics to support planning — replacing the
paper diary entirely.

## 6. Functional requirements

| ID | Actor | Requirement |
|----|-------|-------------|
| FR-01 | Customer | The system shall allow a visitor to register a customer account, validating email format and enforcing a minimum password strength, before creating the account with a hashed password. |
| FR-02 | Customer | The system shall allow a registered customer to log in and log out; a successful login shall regenerate the session ID and a locked account shall be refused login. |
| FR-03 | Customer | The system shall let a logged-in customer browse tables and time slots for a chosen date (today through 30 days ahead), showing only tables whose capacity is sufficient for the requested party size and that have no non-cancelled booking in that date/slot. |
| FR-04 | Customer | The system shall let a customer create a reservation (date, time slot, party size, optional notes) against an available table, checking for conflicts at submission time and creating the booking with status `pending`. |
| FR-05 | Customer | The system shall let a customer view their own reservation history, filterable by booking status. |
| FR-06 | Customer | The system shall let a customer cancel their own booking, but only while its status is `pending` or `confirmed`; the booking status shall become `cancelled`. |
| FR-07 | Customer | The system shall let a logged-in customer edit their profile details and change their password, requiring the correct current password before a change is accepted. |
| FR-08 | Admin | The system shall show an admin dashboard summarising today's bookings, the count of pending bookings, the cancellation rate, and the busiest time slot. |
| FR-09 | Admin | The system shall let an admin search the booking list by customer name, filter by date and/or status, sort by time or created date, and page through results. |
| FR-10 | Admin | The system shall let an admin approve (`confirmed`) or reject (`rejected`) a booking that is currently `pending`; no other status may be approved/rejected directly. |
| FR-11 | Admin | The system shall let an admin mark a `confirmed` booking as `completed` or `no_show` once its reserved date/time slot has passed. |
| FR-12 | Admin | The system shall provide full CRUD for tables: table number, capacity, area, and active status. |
| FR-13 | Admin | The system shall provide full CRUD for time slots: start time, end time, and active status. |
| FR-14 | Admin | The system shall let an admin view all user accounts, lock/unlock a customer account, and change a user's role. |
| FR-15 | Admin | The system shall let an admin generate booking statistics for a selected date range and export the results as a CSV file. |

## 7. Non-functional requirements

| ID | Category | Requirement |
|----|----------|-------------|
| NFR-01 | Performance | The table-availability search (FR-03) shall return results within 2 seconds under the seeded demo dataset (~20 tables, ~60 reservations). |
| NFR-02 | Security | 100% of database queries shall use PDO prepared statements with bound parameters; all passwords shall be stored using `password_hash()` (never plaintext); every state-changing form (POST) shall carry and verify a CSRF token. |
| NFR-03 | Usability | Every page shall use a consistent Bootstrap 5 layout and navigation, remain usable down to a 375px-wide viewport, and surface validation errors and success/failure notifications inline on the same page (no silent failures). |
| NFR-04 | Compatibility | The application shall run correctly on the current stable release of Chrome, Firefox, and Edge, and on a standard XAMPP stack (PHP 8.x + MySQL 8/MariaDB) with no additional server software. |
| NFR-05 | Maintainability | All PHP files shall separate database/query logic from HTML presentation, use English snake_case names for variables/functions, and carry a Vietnamese comment on every function and non-obvious block, so any team member can explain and modify the code at the viva. |
| NFR-06 | Data integrity | The double-booking constraint (one table, one non-cancelled reservation per date/time-slot) shall be enforced both in PHP application logic and as a database-level constraint, so it holds even if the PHP check is bypassed. |

## 8. User stories

| ID | Story |
|----|-------|
| US-01 | As a visitor, I want to register a customer account with my email and a password, so that I can log in and make reservations. |
| US-02 | As a customer, I want to log in with my email and password, so that I can access my reservations. |
| US-03 | As a customer, I want to log out, so that my account is not left open on a shared device. |
| US-04 | As a customer, I want to browse tables and time slots available on a chosen date for my party size, so that I can find a table that fits my group. |
| US-05 | As a customer, I want to submit a reservation for an available table, date, time slot, and party size, so that I have a booked table without calling the restaurant. |
| US-06 | As a customer, I want to view my reservation history and filter it by status, so that I can track what's upcoming, confirmed, or past. |
| US-07 | As a customer, I want to cancel a pending or confirmed booking, so that I free the table if my plans change. |
| US-08 | As a customer, I want to edit my profile and change my password, so that my account details stay accurate and secure. |
| US-09 | As an admin, I want a dashboard showing today's bookings, pending count, cancellation rate, and busiest time slot, so that I can understand the day's workload at a glance. |
| US-10 | As an admin, I want to search, filter, sort, and page through the booking list, so that I can quickly find a specific reservation among many. |
| US-11 | As an admin, I want to approve or reject a pending booking, so that only confirmed reservations hold a table. |
| US-12 | As an admin, I want to mark a past confirmed booking as completed or no-show, so that the booking record reflects what actually happened. |
| US-13 | As an admin, I want to create, edit, and deactivate tables, so that the table list always matches the restaurant's real seating. |
| US-14 | As an admin, I want to create, edit, and deactivate time slots, so that the service schedule can be adjusted without code changes. |
| US-15 | As an admin, I want to view users and lock/unlock accounts or change roles, so that I can manage access and handle account issues. |
| US-16 | As an admin, I want to view booking statistics for a date range and export them as CSV, so that I can use the data for staffing and capacity planning. |

## 9. Acceptance criteria (Given/When/Then)

**US-01 — Register**
- Given a visitor on the registration page, When they submit a unique, valid
  email, a password meeting the strength rule, and a matching confirmation,
  Then a new customer account is created with a hashed password and they are
  redirected to the login page with a success message.
- Given an email that is already registered, When the form is submitted, Then
  the system shows an error and does not create a duplicate account.
- Given a password that fails the strength rule, When the form is submitted,
  Then a validation error is shown and no account is created.

**US-02 — Login**
- Given an active, registered customer account, When correct email and
  password are submitted, Then the user is logged in, the session ID is
  regenerated, and they are redirected to the customer dashboard.
- Given incorrect credentials, When submitted, Then an error is shown and no
  session is created.
- Given an account locked by an admin, When correct credentials are submitted,
  Then login is refused with a clear message.

**US-03 — Logout**
- Given a logged-in user, When they select "Log out", Then their session is
  destroyed and they are redirected to the homepage.

**US-04 — Browse availability**
- Given a logged-in customer selects a date between today and 30 days ahead, a
  time slot, and a party size, When the search runs, Then only tables with
  `capacity >= party_size` and no non-cancelled/non-rejected booking for that
  date/slot are listed, ordered to prefer the smallest sufficient table.
- Given a date in the past or more than 30 days ahead, When selected, Then the
  system rejects the date with a validation message and returns no results.

**US-05 — Create reservation**
- Given a valid date, time slot, party size, and an available table, When the
  customer submits the reservation (with optional notes), Then a booking is
  created with status `pending` and appears in the customer's history.
- Given two customers attempt to book the same table for the same date/slot at
  nearly the same time, When both submit, Then only the first is accepted and
  the second receives a conflict error — enforced by both the PHP check and the
  database constraint.

**US-06 — View history / filter**
- Given a customer with existing bookings, When they open "My bookings" and
  choose a status filter, Then only bookings with that status are shown.

**US-07 — Cancel booking**
- Given a booking owned by the logged-in customer with status `pending` or
  `confirmed`, When they choose "Cancel", Then its status becomes `cancelled`
  and the table/slot becomes available again for other customers.
- Given a booking with status `completed`, `no_show`, `rejected`, or already
  `cancelled`, When cancellation is attempted, Then the system refuses the
  action.

**US-08 — Edit profile / change password**
- Given a logged-in customer, When they update profile fields and save, Then
  the changes persist.
- Given a password change request with the correct current password and a new
  password meeting the strength rule, When submitted, Then the new password is
  hashed and saved; given an incorrect current password, Then the change is
  rejected.

**US-09 — Admin dashboard**
- Given an admin is logged in, When the dashboard loads, Then it shows the
  count of today's bookings, the count of `pending` bookings, the cancellation
  rate (cancelled ÷ total for a defined period), and the time slot with the
  most bookings.

**US-10 — Search/filter/sort/paginate**
- Given the admin booking list, When a date, status, and/or customer-name
  filter is applied together with a sort order, Then the list updates to match
  all active filters and sort order, split across pages of a fixed size.

**US-11 — Approve/reject**
- Given a booking with status `pending`, When the admin clicks "Approve", Then
  its status becomes `confirmed`; When the admin clicks "Reject", Then its
  status becomes `rejected`.
- Given a booking not in `pending` status, When approve/reject is attempted,
  Then the action is refused.

**US-12 — Mark completed/no-show**
- Given a `confirmed` booking whose date/time slot end has already passed,
  When the admin marks it `completed` or `no_show`, Then the status updates
  accordingly.
- Given a booking whose reserved slot has not yet passed, When this action is
  attempted, Then it is refused.

**US-13 — CRUD tables**
- Given the table management screen, When the admin adds or edits a table's
  number, capacity, area, or active flag, Then the change is saved and
  immediately reflected in the availability search; an inactive table never
  appears as available.

**US-14 — CRUD time slots**
- Given the time slot management screen, When the admin adds, edits, or
  deactivates a slot, Then the change applies to future availability searches
  immediately; existing bookings keep their originally booked slot.

**US-15 — Manage users**
- Given the user management screen, When the admin locks a customer account,
  Then that account can no longer log in until unlocked.
- Given a role change submitted by the admin, When saved, Then the user's
  permissions update accordingly; an admin cannot lock or demote their own
  account.

**US-16 — Reports/CSV**
- Given the reports screen, When the admin selects a start and end date and
  applies it, Then booking counts by status/area/time slot for that range are
  displayed.
- Given the same range, When the admin clicks "Export CSV", Then a CSV file
  containing that data downloads.

## 10. Deferred / out of scope

Ideas considered but deliberately excluded to stay within the locked rubric
scope (see Section 3):
- Table-combining logic for oversized parties
- Automated email/SMS confirmation and reminder messages
- A waitlist for fully booked slots
- Admin-initiated cancellation on a customer's behalf
- Multi-branch support and a branch-selection step
- Payment/deposit collection at booking time
- Full audit-log/history table beyond the current status field

## 11. Team contribution table

*(names and student IDs to be filled in by the team; responsibilities are
locked to keep grading traceable to individual commits/issues)*

| # | Full name | Student ID | Primary responsibility |
|---|-----------|------------|------------------------|
| 1 |           |            | Requirements & documentation |
| 2 |           |            | Database design & backend CRUD |
| 3 |           |            | Reservation workflow & admin panel |
| 4 |           |            | UI/UX design & frontend |
| 5 |           |            | Testing, security review & packaging |

## 12. GitHub Project board

- Repository: https://github.com/met23080025-ui/FINAL-EXAMINATION-HSB2006-DEVELOPING-BUSINESS-APPLICATIONS
- Project board: *(create with columns To do / In progress / Done; add a link
  here once created)*
- Every user story (US-01 … US-16) must be converted into one issue on the
  board, assigned to a team member.

## 13. Traceability matrix

Maps each user story to the functional requirement it implements and the
marking-scheme criterion it primarily provides evidence for.

| US | FR | Primary criterion | Secondary criterion |
|----|----|--------------------|----------------------|
| US-01 | FR-01 | 4 — Database & Backend (auth) | 5 — Security (password hashing, validation) |
| US-02 | FR-02 | 4 — Database & Backend (auth) | 5 — Security (session regeneration, lockout check) |
| US-03 | FR-02 | 4 — Database & Backend (auth) | — |
| US-04 | FR-03 | 4 — Database & Backend (search/filter) | 3 — UI/UX (availability screen) |
| US-05 | FR-04 | 4 — Database & Backend (core business workflow) | 5 — Security/integrity (conflict check) |
| US-06 | FR-05 | 4 — Database & Backend (filter) | 3 — UI/UX |
| US-07 | FR-06 | 4 — Database & Backend (workflow) | — |
| US-08 | FR-07 | 4 — Database & Backend (CRUD) | 5 — Security (password change) |
| US-09 | FR-08 | 4 — Database & Backend (reporting) | 3 — UI/UX (dashboard) |
| US-10 | FR-09 | 4 — Database & Backend (search/filter/sort/pagination) | 3 — UI/UX |
| US-11 | FR-10 | 4 — Database & Backend (core business workflow) | — |
| US-12 | FR-11 | 4 — Database & Backend (core business workflow) | — |
| US-13 | FR-12 | 4 — Database & Backend (CRUD) | — |
| US-14 | FR-13 | 4 — Database & Backend (CRUD) | — |
| US-15 | FR-14 | 4 — Database & Backend (CRUD, roles) | 5 — Security (access control) |
| US-16 | FR-15 | 4 — Database & Backend (reporting) | — |

All 16 user stories also depend on Criterion 1 (this requirements document +
the Project board/issues) and Criterion 2 (the diagrams in Phase P2 must model
this same set of requirements) for full marks — those are structural
dependencies rather than per-story ones, so they are not repeated per row.
