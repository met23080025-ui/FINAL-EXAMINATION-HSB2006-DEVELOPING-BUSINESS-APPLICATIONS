# Test Plan — Golden Lotus Restaurant Reservation System

Phase P8 deliverable (criterion 5 — Testing, Security, Packaging, Demo).
43 test cases across 10 modules — comfortably over the 25-case minimum,
covering every area named in the Phase P7 handover brief plus the standard
happy-path coverage a grader expects to see exercised.

**How to use this document:** run each case against a fresh import of
`database/schema.sql` + `database/seed.sql` (or note in "Actual result" if
run against a modified dataset — several cases mutate data, e.g. TC-33/34
delete a table). Fill in **Actual result** and **Pass/Fail** by hand as you
run each case. Any case that fails becomes an entry in the "Known
unresolved defects" list at the bottom (or gets fixed and re-tested before
submission — fixed-and-verified cases do not need to be listed as defects).

Test accounts (all password `Password123!`): `admin@goldenlotus.test`
(admin), `customer1@goldenlotus.test` … `customer6@goldenlotus.test`
(customer). See README "Test accounts" for the full list.

---

## Module: Registration (REG)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-01 | A visitor can register a new account with valid data | Not logged in; use an email not already in `users` | 1) Go to `auth/register.php`. 2) Fill full name, a new unique email, phone, password `Test1234`, confirm `Test1234`. 3) Submit. | Account created with `role='customer'`, `is_active=1`, a bcrypt hash (`$2y$` prefix) in `password_hash` — never the plaintext. Redirected to login with success flash. | | |
| TC-02 | Duplicate email is rejected | An email already in `users` (e.g. `customer1@goldenlotus.test`) | 1) Go to `auth/register.php`. 2) Fill the form using that existing email. 3) Submit. | Inline error "This email is already registered." under the email field; no second row inserted; top alert "Please fix the errors below." | | |
| TC-03 | Weak password is rejected | Not logged in | 1) Fill the form with a new email but password `abc` (too short, no digit). 2) Submit. | Inline error "Password must be at least 8 characters and include at least one letter and one number."; no account created. | | |
| TC-04 | Mismatched password confirmation is rejected | Not logged in | 1) Fill the form with password `Test1234` and confirm `Different99`. 2) Submit. | Inline error "Passwords do not match." under confirm-password field; no account created. | | |
| TC-05 | Invalid email format is rejected | Not logged in | 1) Fill the form with email `not-an-email`. 2) Submit. | Inline error "Please enter a valid email address."; no account created; `is_valid_email()` (`includes/helpers.php`) is what rejects it. | | |

## Module: Login / Logout (AUTH)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-06 | Valid credentials log the user in | A seeded active account | 1) Go to `auth/login.php`. 2) Enter `customer1@goldenlotus.test` / `Password123!`. 3) Submit. | Redirected to `customer/dashboard.php`; navbar shows customer links; session ID differs from the pre-login one (`session_regenerate_id(true)` fired). | | |
| TC-07 | Wrong password is refused with a generic message | Seeded active account | 1) Enter `customer1@goldenlotus.test` with a wrong password. 2) Submit. | Error "Invalid email or password." — not "wrong password" specifically; no session created. | | |
| TC-08 | Non-existent email gives the identical generic message | None | 1) Enter `doesnotexist@goldenlotus.test` with any password. 2) Submit. | Same exact text "Invalid email or password." as TC-07 (proves no user-enumeration difference between the two failure cases). | | |
| TC-09 | A locked (deactivated) account cannot log in | An admin has deactivated a customer account first (see TC-35 area / `admin/users.php`) | 1) Attempt login with that account's correct credentials. | Error "This account has been locked. Please contact the restaurant." — a distinct message from TC-07/08, shown only once credentials are otherwise correct. | | |
| TC-10 | Logout destroys the session | Logged in as any user | 1) Click "Logout" in the navbar. 2) Then press the browser Back button and reload. | Redirected to `index.php` with "You have been logged out." flash; navbar reverts to guest links; reloading a previously-protected page (e.g. `customer/dashboard.php`) redirects to login again. | | |

## Module: Role guards / access control (ACC)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-11 | An unauthenticated visitor is blocked from a customer-only page | Not logged in (no session cookie) | 1) Type `http://localhost/golden-lotus/customer/dashboard.php` directly into the address bar. | Redirected to `auth/login.php` with "Vui long dang nhap de tiep tuc." flash; dashboard content never rendered. | | |
| TC-12 | A logged-in customer is blocked from an admin page by direct URL | Logged in as `customer1@goldenlotus.test` | 1) Type `http://localhost/golden-lotus/admin/dashboard.php` directly into the address bar. | Redirected to `index.php` with "Ban khong co quyen truy cap trang nay." flash; no admin data rendered anywhere in the response. | | |
| TC-13 | An admin can reach every admin page | Logged in as `admin@goldenlotus.test` | 1) Visit `admin/dashboard.php`, `admin/bookings.php`, `admin/tables.php`, `admin/timeslots.php`, `admin/users.php`, `admin/reports.php` in turn. | Every page returns HTTP 200 and its real content (not a redirect). | | |

## Module: Open-redirect guard (REDIR)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-14 | A legitimate internal redirect target is honoured after login | Not logged in | 1) Visit `index.php`, click "Book a Table" (this links to `auth/login.php?redirect=/customer/book.php`). 2) Log in with valid customer credentials. | After login, the browser lands on `customer/book.php`, not the default `customer/dashboard.php` — proves `safe_redirect_target()` accepts a genuine same-app path. | | |
| TC-15 | An external URL passed as `redirect` is rejected | Not logged in | 1) Visit `auth/login.php?redirect=https://evil.example` directly. 2) Log in with valid customer credentials. | After login, the browser lands on `customer/dashboard.php` (the normal role-based default) — **not** `https://evil.example`. Repeat with `redirect=//evil.example` and `redirect=/\evil.example`; both must also fall back to the dashboard. | | |

## Module: Booking — happy path and edge cases (BOOK)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-16 | Happy path: search and create a reservation | Logged in as a customer | 1) Go to `customer/book.php`. 2) Pick a date within the next 30 days, a party size of 2, any active slot. 3) Search. 4) Select the top-listed (smallest sufficient) table. 5) Add an optional note. 6) Confirm Reservation. | Available-tables list shows only tables with `capacity >= 2` and no conflicting booking, smallest first; after confirming, green flash "Your reservation has been submitted and is pending approval."; new row visible on `customer/my-reservations.php` with status `Pending`. | | |
| TC-17 | Edge case: a past date is rejected | Logged in as a customer | 1) On `customer/book.php`, manually edit the `date` query-string parameter to yesterday's date and submit the search (bypassing the HTML5 `min` attribute). | Server-side rejection via `is_slot_bookable()`: error "You cannot book a date in the past." shown, no table list rendered. | | |
| TC-18 | Edge case: party size larger than every table's capacity | Logged in as a customer | 1) Search with party size `99` for any date/slot. | Empty-state message "No tables are available for this date, time slot, and party size. Try a different date, time, or slot." — no PHP error, no table listed (largest table seats 12). | | |
| TC-19 | Edge case: no table available because all sufficient tables are already booked | Logged in as a customer | 1) Pick a date/slot/party-size combination where the seed data (or a prior test) has already filled every table of sufficient capacity. | Same empty-state message as TC-18. | | |
| TC-20 | Edge case: double-booking race between two concurrent submissions | Two customer accounts logged in in two separate browser sessions (not two tabs — see `docs/evidence/double-booking-proof.md` §7 for why) | Follow the exact steps in `docs/evidence/double-booking-proof.md` §7: both sessions search the same date/slot/party-size, both select the same table, Window A confirms first, then Window B confirms on its stale page. | Window A: success flash, `pending` row created. Window B: red flash "Sorry, that table is no longer available for this date, time, and party size. Please choose another table."; exactly one row exists in `reservations` for that `(table, date, slot)` — verify with the SQL query in that document. | | |

## Module: Cancellation (CANCEL)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-21 | Customer cancels their own pending/confirmed future booking | Customer has a `pending` or `confirmed` booking with a future date/time | 1) Go to `customer/my-reservations.php`. 2) Click "Cancel" on that row. 3) Confirm the `confirm()` dialog. | Row's status becomes `Cancelled` (grey badge); flash "Your reservation has been cancelled."; the table/slot becomes available again in a fresh `book.php` search. | | |
| TC-22 | Cancel is refused on a completed, no-show, already-cancelled, rejected, or past-dated booking | Customer has at least one booking in a terminal status or with a past date | 1) On `customer/my-reservations.php`, note that no "Cancel" button renders for such a row. 2) Attempt a direct POST to `customer/my-reservations.php` with `form_action=cancel_reservation` and that reservation's ID (e.g. via curl/Postman) using a valid CSRF token from the page. | Server rejects it: "This reservation can no longer be cancelled because its date/time has already passed." (or the row simply isn't the caller's own, if attempted cross-account) — status unchanged in the database. | | |

## Module: Admin status transitions (STATUS)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-23 | Approve a pending booking | Admin logged in; at least one `pending` booking exists | 1) On `admin/bookings.php` (or the dashboard pending-queue preview), click "Approve" on a pending row. | Status becomes `Confirmed` (green badge); flash "Reservation status updated."; row's Approve/Reject buttons disappear, replaced by Complete/No-show controls once its time has passed. | | |
| TC-24 | Reject a pending booking | Admin logged in; a `pending` booking exists | 1) Click "Reject", confirm the `confirm()` dialog ("Reject this booking? The customer will be notified and cannot be re-approved afterwards."). | Status becomes `Rejected` (red badge); no further action buttons render for that row (terminal state). | | |
| TC-25 | Mark a past confirmed booking as Completed | A `confirmed` booking whose date+slot end time is in the past | 1) On `admin/bookings.php`, find that row (Complete/No-show buttons only render once the slot has passed). 2) Click "Mark Completed". | Status becomes `Completed` (blue-grey badge). | | |
| TC-26 | Mark a past confirmed booking as No-show | A different `confirmed` booking whose slot has passed | 1) Click "Mark No-show" on that row. | Status becomes `No_show`, rendered with the outlined red `.badge-status-no-show` style (visually distinct from solid-red `Rejected` in the same column). | | |
| TC-27 | Illegal transition: approve/reject a booking that is not pending | Any `confirmed`, `completed`, `cancelled`, or `rejected` booking | 1) Craft a direct POST to `admin/bookings.php` with `action=approve` (or `reject`) and that reservation's ID, using a valid admin session + CSRF token. | `change_reservation_status()` calls `can_transition()`, which returns false for e.g. `confirmed → confirmed`; response: danger flash "Cannot change status from 'confirmed' to 'confirmed'."; no row updated. | | |
| TC-28 | Illegal transition: complete/no-show a booking whose slot hasn't passed yet | A `confirmed` booking with a future date/time | 1) Confirm no "Mark Completed"/"Mark No-show" buttons render for this row in the UI. 2) Attempt the same direct-POST technique as TC-27 with `action=mark_completed`. | `can_transition('confirmed','completed')` is actually allowed by the state machine (no time check inside it), so this specific POST **does** succeed — this is a documented business-logic gap, not a crash. Record the actual behaviour here; see `docs/remaining-work.md` if it should become a defect entry. | | |

## Module: Admin CRUD validation (CRUD)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-29 | Duplicate table code is rejected | Admin logged in; table `T01` already exists | 1) On `admin/tables.php`, click "+ Add Table". 2) Enter code `T01` (any capacity/area). 3) Submit. | Danger flash "Could not save table: This table code is already in use."; no duplicate row inserted. | | |
| TC-30 | Invalid table code pattern / out-of-range capacity is rejected | Admin logged in | 1) Add a table with code `abc` (lowercase, wrong pattern) and/or capacity `0` or `25`. | Danger flash listing "Table code must be one uppercase letter followed by two digits (e.g. T05, V02)." and/or "Capacity must be between 1 and 20."; no row inserted. | | |
| TC-31 | Time slot end time not after start time is rejected | Admin logged in | 1) On `admin/timeslots.php`, add a slot with start `14:00`, end `13:00`. | Danger flash "End time must be after start time."; no row inserted. | | |
| TC-32 | Overlapping active time slot is rejected | Admin logged in; slot `12:30–14:00` already active | 1) Add a new slot `12:00–13:00` (overlaps). | Danger flash "This time range overlaps with an existing active time slot."; no row inserted. | | |
| TC-33 | Deleting a table that has reservations is refused, deactivate offered instead | Admin logged in; pick a table with a non-zero reservation count shown in its row | 1) Click "Delete" on that table's row (button only renders if reservation count is 0 — use a direct POST with `form_action=delete` and that table's ID to bypass the UI for this test). | Danger flash "Cannot delete this table: it has N reservation(s) on record. Use Deactivate instead to keep booking history intact."; row not removed; FK `ON DELETE RESTRICT` would also block it at the DB level if this check were somehow skipped. | | |
| TC-34 | Deleting a table with zero reservations succeeds | Admin logged in; a table with 0 reservations (e.g. a newly-added test table) | 1) Click "Delete" on that row, confirm the dialog. | Success flash "Table deleted."; row no longer appears in the list or in the database. | | |

## Module: Self-protection (SELF)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-35 | An admin cannot deactivate or demote their own account | Logged in as `admin@goldenlotus.test` | 1) On `admin/users.php`, find the row marked "You" (the currently logged-in admin) — confirm no Deactivate/role controls render for it. 2) Attempt a direct POST with `form_action=toggle_active`, `id=<own id>`, using a valid CSRF token. | Danger flash "You cannot change your own role or active status."; `is_active`/`role` unchanged in the database — this check runs before any action-specific logic, so it also blocks a forged `change_role` POST targeting your own ID. | | |

## Module: Search / filter / sort / pagination (LIST)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-36 | Combined filters narrow the admin bookings list correctly | Admin logged in; seeded data has bookings across multiple statuses/areas/dates | 1) On `admin/bookings.php`, enter a customer name in "Search customer", pick a status, pick an area, set a date range. 2) Submit. | Only rows matching **all** active filters are shown; "Showing X-Y of Z bookings" reflects the filtered count, not the total; the filter values remain populated in the form after submit (state preserved in the URL query string). | | |
| TC-37 | Sorting toggles direction and survives together with active filters | Admin logged in; at least one filter active from TC-36 | 1) Click the "Date" column header once, then again. 2) Confirm the ▲/▼ indicator flips and rows reorder. 3) Confirm the filters from TC-36 are still applied. | Ascending then descending order on `reservation_date`; filter values in the URL/form unchanged throughout — pending-status rows still bubble to the top per the tie-break rule, but sub-order changes correctly. | | |
| TC-38 | Pagination boundary: out-of-range page number is clamped | Admin logged in; enough bookings to exceed one page (15/page) | 1) Manually set `?page=999` in the URL. | The page silently clamps to the last valid page (no blank page, no error) — `paginate()` in `includes/listing.php` mins the requested page against `total_pages`. | | |

## Module: Reports and CSV export (REPORT)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-39 | CSV export matches the filtered date range, with correct headers and escaping | Admin logged in; at least one seeded booking has a customer name or notes field containing a comma or quote character (or add one via `book.php` notes) | 1) On `admin/reports.php`, set a from/to date range. 2) Click "Export CSV". | Browser downloads `bookings_report_<from>_to_<to>.csv`; `Content-Type: text/csv; charset=utf-8`; first row is the header (`Reservation ID,Date,Start Time,...`); every data row's Date falls within the chosen range; a value containing a comma is wrapped in quotes by `fputcsv()` and does not shift columns when opened in Excel/a text editor. | | |

## Module: Security probes (SEC)

| ID | Objective | Preconditions | Steps | Expected result | Actual result | Pass/Fail |
|---|---|---|---|---|---|---|
| TC-40 | SQL injection attempt in a search box | Admin logged in | 1) On `admin/bookings.php` (or `admin/users.php`), enter `' OR '1'='1` (or `x'; DROP TABLE users;--`) into the search field. 2) Submit. | Treated as a literal (harmless) search string — matched against `LIKE '%...%'` via a bound parameter, returns zero rows (no customer is actually named that); no SQL error, no data leaked, `users`/`reservations` tables intact afterward. Every query in this codebase uses PDO prepared statements with bound values, so this class of injection is structurally prevented, not just filtered. | | |
| TC-41 | XSS attempt in the reservation notes field | Logged in as a customer | 1) On `customer/book.php`, in the Notes field enter `<script>alert(1)</script>`. 2) Complete the booking. 3) View it on `customer/my-reservations.php` and on `admin/bookings.php`. | The literal text `<script>alert(1)</script>` is displayed as visible text (HTML-escaped), no alert box fires — every dynamic value is passed through `e()` (`htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`) before being echoed. | | |
| TC-42 | CSRF request with a missing/invalid token is rejected | Logged in as any user | 1) Submit any state-changing form (e.g. cancel a booking, approve a booking, create a table) via a direct POST that omits `csrf_token` or sends an obviously wrong value. | Danger flash "Phien lam viec da het han, vui long thu lai." ("Your session has expired, please try again."); redirected back without the action taking effect; verified with `csrf_verify()`'s `hash_equals()` comparison against `$_SESSION['csrf_token']`. | | |
| TC-43 | Direct URL access to an admin page as a customer, with a forged POST | Logged in as `customer1@goldenlotus.test` | 1) Attempt a direct POST to `admin/tables.php` with `form_action=delete` and any table ID, using a customer session (no valid admin CSRF token can even be obtained from an admin page this account can't view). | `require_admin()` runs before any POST-handling code and redirects to `index.php` with "Ban khong co quyen truy cap trang nay." before the delete logic is ever reached — access control, not CSRF, is the controlling defence here, and it fires first. | | |

---

## Known unresolved defects

*(fill in as cases are run — each entry needs: which TC found it, a one-line
description, severity, and whether a fix is planned before submission or
being accepted as a documented limitation. TC-28 above already flags one
candidate: `can_transition()` allows `confirmed → completed/no_show`
regardless of whether the slot's time has actually passed — the UI never
exposes this because the buttons are conditionally rendered, but a forged
POST could complete/no-show a future booking early. Decide before P9
whether to add the time check into `can_transition()`/
`change_reservation_status()` itself or document it as an accepted
limitation.)*

| Defect ID | Found by | Description | Severity | Resolution |
|---|---|---|---|---|
| | | | | |
