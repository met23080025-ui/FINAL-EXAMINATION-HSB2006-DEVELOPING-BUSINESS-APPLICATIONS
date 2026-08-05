# Security Review — Golden Lotus Restaurant Reservation System

Phase P8 deliverable (criterion 5). Every control below is implemented in
the codebase today (not planned/future work) and is referenced to the exact
file that implements it, so this document can be read side by side with the
code at the viva. Ordered roughly by where in a request's lifecycle each
control fires: transport/access → input handling → data layer → output.

---

## 1. Access control (authentication + authorisation)

**Files:** `includes/auth.php`, every `admin/*.php` and `customer/*.php` file.

- `current_user()` reads `$_SESSION['user']`; `is_logged_in()` and
  `is_admin()` are pure boolean checks on top of it.
- `require_login()` and `require_admin()` are called as the **first
  executable statement** in every protected page, before any HTML is
  echoed. `require_admin()` calls `require_login()` first, then checks
  `is_admin()` — so an unauthenticated request to an admin page gets the
  "please log in" flash (not a confusing "no permission" message for a user
  who isn't even logged in), while a logged-in customer gets "Ban khong co
  quyen truy cap trang nay." and is bounced to `index.php`.
- This is enforced **server-side on every request**, not by hiding nav
  links — a customer typing an admin URL directly gets redirected before
  any admin data is queried or rendered (verified: TC-12, TC-43 in
  `docs/test-plan.md`).
- **Self-protection in `admin/users.php`:** an admin cannot deactivate or
  change the role of their own account. The check compares the POST
  target's `id` against the logged-in admin's own `id` and runs *before*
  branching into the `toggle_active`/`change_role` logic, so it cannot be
  bypassed by choosing a different `form_action` value. Rationale in the
  file's own doc-comment: without this, an admin could lock themselves out
  with no other admin account able to reverse it (this project has no
  "recovery" admin or CLI tool) — see `docs/viva-preparation.md` Q8.

## 2. SQL injection defence

**Files:** every file that queries the database (`includes/reservation.php`,
`includes/listing.php`, all of `admin/*.php`, `customer/*.php`,
`auth/*.php`), configured via `includes/db.php`.

- `includes/db.php` opens PDO with `PDO::ATTR_EMULATE_PREPARES => false` —
  MySQL's **native** prepared-statement protocol is used, not PHP-side
  string substitution. Every single query in the codebase is a prepared
  statement with bound parameters (`?` or named placeholders); none
  concatenates user input into SQL text. This satisfies NFR-02 (100% of
  queries parameterised) exactly.
- **What prepared statements do *not* protect: identifiers.** A bound
  parameter can only stand in for a *value* (a string/int literal), never
  for a column or table *name*. `ORDER BY :column` is not valid syntax —
  PDO would bind it as a quoted string literal, which MySQL then rejects.
  This matters because the admin listing pages (`admin/bookings.php`,
  `admin/tables.php`, `admin/timeslots.php`, `admin/users.php`) let the
  user pick a sort column via `?sort=...` in the URL — that value has to be
  concatenated into the `ORDER BY` clause as raw SQL text, which would be
  an injection point if it came from `$_GET` unchecked.
- **The fix: a hard-coded whitelist**, `resolve_sort()` in
  `includes/listing.php`. Each page defines an array like
  `['date' => 'r.reservation_date', 'created' => 'r.created_at']` —
  the *keys* are what the URL is allowed to say, the *values* are the only
  SQL fragments that can ever reach the query, and both are written by the
  developer, never derived from request data. If `?sort=` contains anything
  not in the array's keys, `resolve_sort()` silently falls back to the
  page's default column instead of erroring or passing the value through.
  Full Vietnamese-commented rationale is in `includes/listing.php`'s
  top-of-file doc-comment. See `docs/viva-preparation.md` Q6 for how to
  explain this distinction (values vs. identifiers) live.
- `LIMIT`/`OFFSET` are also request-influenced (`?page=`) and are bound as
  `PDO::PARAM_INT` explicitly via `bindValue()` (not left to PDO's default
  string-typed binding) — with native prepares, MySQL rejects a
  string-typed value in a `LIMIT` clause, so this isn't optional; see the
  comment in `admin/bookings.php` at the `bindValue(':limit', ...)` call.
- **Bug found and fixed during Phase P7 testing:** PDO with
  `EMULATE_PREPARES=false` does not allow the same **named** placeholder to
  appear twice in one query (`... LIKE :kw OR ... LIKE :kw`) — it throws
  `SQLSTATE[HY093]: Invalid parameter number`. Both keyword-search queries
  (`admin/bookings.php`, `admin/users.php`) originally had this bug; fixed
  by using two distinct placeholders (`:keyword1`, `:keyword2`) bound to
  the same value. Caught by end-to-end `curl` testing against the live
  seeded database, not just code review — see
  `docs/development-log.md` Phase P7 entry.

## 3. Password handling

**Files:** `auth/register.php`, `auth/login.php`, `customer/profile.php`.

- Passwords are hashed with `password_hash($password, PASSWORD_DEFAULT)`
  (currently bcrypt, `$2y$` prefix) and verified with `password_verify()`.
  The plaintext value is never written to the database, a log, or an echo
  statement anywhere in the codebase.
- **Hashing, not encryption, and why that distinction matters:** encryption
  is reversible (decrypt with the right key); hashing is one-way by design.
  The application never needs to recover a user's original password — only
  to check whether a freshly-submitted one produces the same hash — so a
  one-way function is the correct primitive. If the `users` table were ever
  leaked, encrypted passwords would be recoverable by anyone who also
  obtained the key; bcrypt hashes are not reversible at all, only
  guessable, and bcrypt is deliberately slow (a tunable "cost" factor) to
  make large-scale guessing expensive. See `docs/viva-preparation.md` Q7.
- Registration enforces a minimum strength rule server-side
  (`is_strong_password()` in `includes/helpers.php`: ≥8 characters, ≥1
  letter, ≥1 digit) in addition to the client-side `minlength` hint —
  the server check is authoritative; the client one is UX only.
- `customer/profile.php`'s password-change form requires
  `password_verify()` against the **current** password before accepting a
  new one, so a hijacked-but-still-open session (e.g. an unattended
  browser) can't have its password silently changed by a passerby without
  knowing the current one.
- **Why every seeded test account shares one bcrypt hash:**
  `database/seed.sql` hard-codes the same real bcrypt hash (`$2y$10$...`)
  for all 7 accounts instead of computing 7 different ones, because the
  hash is only ever generated once (by `password_hash()`), and re-running
  the same input through bcrypt with a fresh random salt produces a
  *different* hash every time by design — there is no way to make 7
  visibly different-looking accounts share a human-typeable password
  without either the SQL file computing hashes at import time (not
  possible in plain SQL) or accepting that they'll all look identical in
  the dump. This is a demo/seed-data convenience only; real user
  registrations (`auth/register.php`) each get their own freshly-salted
  hash from a real `password_hash()` call, confirmable by registering two
  new test accounts with the same password and comparing their
  `password_hash` column values (they will differ). See
  `docs/viva-preparation.md` Q9.

## 4. Login hardening (timing/enumeration defence + session fixation)

**File:** `auth/login.php`.

- **User-enumeration defence:** whether the email doesn't exist or the
  password is simply wrong, the user sees the identical message, "Invalid
  email or password." — an attacker cannot use the login form to build a
  list of which emails are registered.
- **The dummy-hash timing defence, and what it closes:** `password_verify()`
  itself takes measurable, roughly-constant time (bcrypt's whole point).
  If the code only called it when a user was actually found, a
  non-existent email would return *immediately* (no verify call at all),
  while a real email with a wrong password would take the full
  `password_verify()` duration — an attacker measuring response times
  could distinguish "no such account" from "wrong password" even though
  both show the same error text, defeating the message-based defence above
  through a side channel. The fix in `auth/login.php`:
  ```php
  $hash_to_check = $user !== false ? $user['password_hash'] : '$2y$10$invalidinvalidinvalidinvalidinvalidu';
  $password_ok   = password_verify($password, $hash_to_check);
  ```
  `password_verify()` always runs, against a real-looking (syntactically
  valid) bcrypt string when no user matches, so the non-existent-email path
  costs approximately the same wall-clock time as the wrong-password path.
  See `docs/viva-preparation.md` Q10.
- **Session fixation defence:** `session_regenerate_id(true)` is called
  immediately after a successful `password_verify()`, *before*
  `$_SESSION['user']` is written. Without this, an attacker who tricks a
  victim into using a session ID the attacker already knows (e.g. via a
  crafted link on a network that doesn't force HTTPS) could hijack the
  now-authenticated session, because the ID itself never changed at the
  privilege boundary. Regenerating it means any pre-login session ID is
  worthless after login. `auth/logout.php` does the same
  (`session_start(); session_regenerate_id(true);`) after destroying the
  old session, for the same reason on the way out.
- A locked account (`is_active = 0`) is refused login with a distinct
  message, checked *after* the password itself is confirmed correct (so a
  locked account doesn't leak "your password would have worked" to an
  attacker who doesn't know it's locked — same enumeration-avoidance logic
  applied one layer deeper).

## 5. CSRF protection

**Files:** `includes/helpers.php` (`csrf_token()`, `csrf_field()`,
`csrf_verify()`), every file with a `<form method="post">`.

- A random 256-bit token (`bin2hex(random_bytes(32))`) is generated once
  per session and stored in `$_SESSION['csrf_token']`. `csrf_field()`
  emits it as a hidden input; every POST handler calls `csrf_verify()`
  as its first action, before touching the database.
- `csrf_verify()` uses `hash_equals()`, not `===`, specifically to avoid a
  timing side-channel on the token comparison itself (a naive `===`
  short-circuits on the first mismatched byte, which is measurably faster
  for a "more wrong" guess than a "one byte off" guess — `hash_equals()`
  always takes the same time regardless of where the strings first
  differ).
- Coverage is total, not selective: registration, login, logout-adjacent
  state, profile update, password change, booking creation, cancellation,
  every admin CRUD create/update/delete/toggle, every status-change action,
  and the reports filter's implicit GET (reports intentionally uses GET,
  not POST, since it only reads data — CSRF tokens are not needed on pure
  `GET` requests that don't mutate state, and adding one would actually
  break bookmarking/sharing a report URL).
- See `docs/viva-preparation.md` Q11 for how to demonstrate this live
  (TC-42 in `docs/test-plan.md`: strip/corrupt the hidden token and resubmit).

## 6. XSS (output escaping) defence

**Files:** `includes/helpers.php` (`e()`), every `.php` file that echoes
dynamic data.

- `e()` wraps `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` and is used
  around **every** piece of dynamic output — database values, `$_GET`/
  `$_POST` echoed back into a form (e.g. re-populating a failed
  registration form), and flash messages. `ENT_QUOTES` additionally
  escapes single quotes, which matters because several attributes in this
  codebase use single-quoted or mixed-quote HTML attributes (e.g. inline
  `data-confirm="..."` strings containing an apostrophe, like "Change
  Nguyen Van An's role?").
- The one deliberate exception is `status_badge_html()` and a handful of
  small HTML-fragment-returning helpers (`sort_header_html()`,
  `bar_row_html()`, `pagination_nav_html()` in `includes/listing.php`) —
  these build trusted, hard-coded HTML strings (badge markup, sort-arrow
  spans) and only interpolate *already-`e()`-escaped* values into them, so
  the raw-HTML concatenation inside them is safe by construction, not an
  oversight. Anywhere a **user-controlled** string reaches one of these
  helpers (e.g. a table's own name inside a `data-confirm` string), it is
  still passed through `e()` first.
- Verified directly: TC-41 in `docs/test-plan.md` submits
  `<script>alert(1)</script>` as a reservation note and confirms it renders
  as inert visible text on both the customer and admin booking views.

## 7. Input validation (defence in depth, not just UX)

**Files:** `includes/helpers.php`, `auth/register.php`, `customer/book.php`,
`customer/profile.php`, `admin/tables.php`, `admin/timeslots.php`.

- Every validation rule that exists client-side (HTML5 `required`,
  `pattern`, `min`/`max`, `minlength`) is **re-checked server-side** with
  the identical rule and, where practical, the identical message text —
  client-side validation is convenience only and is never trusted, per
  NFR-02/NFR-03 and the project's mandatory coding rules.
- Examples of server-side-authoritative checks: `is_valid_email()`,
  `is_strong_password()`, `is_valid_phone()` (`includes/helpers.php`);
  date-range and past-date checks in `is_slot_bookable()`
  (`includes/reservation.php`); table-code pattern
  (`/^[A-Z][0-9]{2}$/`) and capacity range (1–20) in `admin/tables.php`;
  time-slot end-after-start and active-overlap checks in
  `admin/timeslots.php`.
- All of these fail closed: on any validation error, the record is not
  written and the user sees a specific, field-level message — never a
  generic 500 or a silently-ignored bad value.

## 8. Double-booking defence (data-integrity control, two layers)

**Files:** `database/schema.sql` (the `reservations` table and
`uq_reservations_active_slot`), `includes/reservation.php`
(`get_available_tables()`, `create_reservation()`), `customer/book.php`.

This is the project's single most important integrity control (NFR-06) and
is deliberately implemented at **two layers**, not one, because a
PHP-only "check, then insert" is not atomic:

1. **Application-layer pre-check** (`customer/book.php`): immediately
   before inserting, the confirm handler re-runs `get_available_tables()`
   and rejects the submission if the chosen `table_id` is no longer in
   that fresh result. This is a plain `SELECT`, not a lock — it catches
   the overwhelmingly common case cheaply (two people browsing the same
   slot, one already booked it) with a friendly, specific message
   ("Sorry, that table is no longer available...").
2. **Database-layer constraint** (`database/schema.sql`): `reservations`
   has a `STORED` generated column, `active_slot_key`, that evaluates to
   `NULL` when `status IN ('cancelled','rejected')` and to
   `CONCAT(table_id,'_',reservation_date,'_',time_slot_id)` otherwise, with
   a `UNIQUE KEY` on that column. Because MySQL/MariaDB treat every `NULL`
   in a unique index as distinct from every other `NULL`, a cancelled or
   rejected booking never blocks the same table/date/slot from being
   rebooked, while two still-active rows for the same combination collide
   atomically at `INSERT` time, enforced by the storage engine itself —
   not by application logic that could race. `create_reservation()`
   catches the resulting `SQLSTATE[23000]`/MySQL error 1062 and turns it
   into the friendly message "Sorry, that table was just taken...".

**Why layer 2 exists even though layer 1 catches almost every real-world
case:** layer 1's `SELECT` and the eventual `INSERT` are still two separate
statements with a gap between them. Two requests that both pass the
`SELECT` in that gap (a genuine race, not just stale-page reuse) would both
attempt the `INSERT`; only the unique constraint — atomic at the storage
engine — guarantees exactly one of them succeeds. A `BEFORE INSERT`
trigger doing the same `SELECT`-then-allow check was considered and
rejected for the identical reason: the check and the write inside a
trigger are not atomic with each other either, without extra manual
locking. Live proof this constraint actually fires against the real seeded
database (not just a design claim) is in
`docs/evidence/double-booking-proof.md`, including a verbatim MySQL error
message, `information_schema` output, and exact reproduction steps for the
demo. See `docs/viva-preparation.md` Q1–Q2 for how to explain *and*
demonstrate both layers live, including why a human-timed UI demo almost
always shows layer 1's message rather than layer 2's.

## 9. Open-redirect defence

**File:** `includes/helpers.php` (`safe_redirect_target()`),
`auth/login.php`, `index.php`.

- The homepage's "Book a Table" CTA sends a signed-out visitor to
  `auth/login.php?redirect=/customer/book.php` so they land back where
  they meant to go after authenticating. That `redirect` value is
  attacker-controllable request input (anyone can craft their own login
  link with any `redirect=` value and send it to a victim), so it is never
  used directly in a `Location:` header.
- `safe_redirect_target()` only accepts a candidate if **all** of: it
  starts with exactly one `/` (rejects `//host`, a protocol-relative URL
  that browsers resolve against a different host); it contains no `://`
  and no `scheme:` prefix (rejects `/javascript:alert(1)`-style payloads);
  it contains no backslash (some browsers/libraries treat `\` as `/`,
  so `/\evil.example` could otherwise be reinterpreted as `//evil.example`)
  and no control characters (blocks header-injection via CR/LF). Anything
  failing any check silently falls back to the role-based default
  dashboard rather than erroring loudly (erroring would itself leak
  information about what the filter checks).
- **Why this matters even though it's "just" a redirect, not data theft:**
  if the app redirected to *any* value the attacker supplied, a phishing
  link could show the real `goldenlotus` domain in the address bar during
  login (building trust) and only redirect to a look-alike phishing page
  *after* a successful, genuine login — a target is far less suspicious of
  a post-login redirect than of a link that was suspicious from the start.
  Manually verified with three payloads
  (`https://evil.example`, `//evil.example`, `/\evil.example`) — all three
  are dropped (TC-15 in `docs/test-plan.md`). See
  `docs/viva-preparation.md` Q12.

## 10. Miscellaneous / defence-in-depth notes

- `password_hash()`'s output already embeds the algorithm identifier and
  cost factor, so future PHP versions upgrading `PASSWORD_DEFAULT` (e.g.
  to Argon2) would not break existing stored hashes.
- `config.php` (real DB credentials) is `.gitignore`d; only
  `config.sample.php` (no real credentials) is tracked — confirmed via
  `git status`/`.gitignore` inspection, no secret has ever been committed.
- `includes/db.php` hides raw PDO connection-error detail behind
  `APP_DEBUG` — with it off, a DB outage shows a generic "system is having
  an issue" message instead of leaking host/user/schema details in a stack
  trace.
- Foreign keys on `reservations` use `ON DELETE RESTRICT` for
  `user_id`/`table_id`/`time_slot_id` — the database itself refuses to
  silently orphan a reservation by deleting the table/slot/user it
  references; the application-level deactivate-vs-delete checks in
  `admin/tables.php`/`admin/timeslots.php` exist to give the admin a
  friendly message *before* hitting that constraint, not instead of it.

---

## Summary table (for quick report insertion)

| Control | Implemented in | Defends against |
|---|---|---|
| PDO prepared statements (100% of queries) | `includes/db.php` + every query site | SQL injection via values |
| ORDER BY column whitelist | `includes/listing.php` | SQL injection via identifiers (not covered by prepared statements) |
| `password_hash()`/`password_verify()` | `auth/register.php`, `auth/login.php` | Credential theft on data breach |
| Dummy-hash timing defence | `auth/login.php` | Timing-based user enumeration |
| Generic "invalid email or password" message | `auth/login.php` | Message-based user enumeration |
| `session_regenerate_id(true)` on login/logout | `auth/login.php`, `auth/logout.php` | Session fixation |
| CSRF token + `hash_equals()` | `includes/helpers.php` + every POST form | Cross-site request forgery |
| `e()` / `htmlspecialchars(ENT_QUOTES)` | `includes/helpers.php` + every echo of dynamic data | Stored/reflected XSS |
| Server-side re-validation of every client rule | throughout | Client-side-bypass of validation |
| `require_login()` / `require_admin()` | `includes/auth.php` + every protected page | Unauthorised access (vertical) |
| Admin self-protection check | `admin/users.php` | Accidental/malicious admin lockout |
| Two-layer double-booking defence | `customer/book.php` + `database/schema.sql` | Race-condition double-booking |
| `safe_redirect_target()` | `includes/helpers.php` | Open redirect / phishing |
| `ON DELETE RESTRICT` + deactivate-vs-delete UI | `database/schema.sql` + `admin/tables.php`/`timeslots.php` | Orphaned reservation records |
| `config.php` gitignored | `.gitignore` | Credential leakage via source control |
