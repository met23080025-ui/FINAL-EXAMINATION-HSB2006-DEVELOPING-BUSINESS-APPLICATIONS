# Viva Preparation — 20 Likely Examiner Questions

Golden Lotus Restaurant Reservation System, HSB2006 MET4. Each answer is
written to be *said out loud in under a minute*, with a file pointer so you
can pull the code up live if asked to show it. Practice these — the goal at
the viva is to sound like you understand the system, not like you're
reading it for the first time. The first ten are the ones most likely to
be probed hardest (they map directly to the trickiest design decisions in
the codebase); the remaining ten round out the rest of the system.

---

### Q1. How is double-booking prevented, and why a generated column rather than a trigger or just an application-level check?

**Answer:** Two layers. First, `customer/book.php` re-runs the availability
`SELECT` immediately before inserting, rejecting stale submissions cheaply.
Second — the layer that actually guarantees correctness under
concurrency — `reservations` has a `STORED` generated column,
`active_slot_key`, that evaluates to `CONCAT(table_id, '_',
reservation_date, '_', time_slot_id)` for any "still active" status, and to
`NULL` for `cancelled`/`rejected`. A `UNIQUE KEY` sits on that column.
Because MySQL treats every `NULL` in a unique index as distinct from every
other `NULL`, a cancelled booking never blocks the slot from being
rebooked, but two active bookings for the same table/date/slot collide
atomically at `INSERT` time.

A trigger doing a manual `SELECT`-then-`INSERT` check was considered and
rejected: the check and the write inside a trigger are still two separate
steps, not atomic with each other, without extra manual row locking — so a
trigger alone would still let two near-simultaneous inserts both pass the
check. An application-only check has the identical problem, one level up.
The unique index is the only option here that's atomic at the storage
engine itself.

**Files:** `database/schema.sql` (`CREATE TABLE reservations`, long
Vietnamese comment block above it), `docs/data-dictionary.md`,
`includes/reservation.php` (`create_reservation()`).

---

### Q2. Why does the UI demo usually show the *application* pre-check message, not the database constraint — and how do you demonstrate the constraint directly?

**Answer:** Because the app-layer `SELECT` re-check in `customer/book.php`
runs first and catches the overwhelmingly common case (two people who both
loaded the availability page, one already booked) before an `INSERT` is
even attempted — so a two-browser-window demo will almost always show the
friendly "that table is no longer available" message (layer 1), not the
"table was just taken" message (layer 2, which only fires on the narrower
race between that `SELECT` and the `INSERT` itself — too small a window for
a human clicking two windows to reliably land inside). That is expected,
not a gap: either layer stops the duplicate, and the database constraint is
what makes trusting layer 1 safe in the first place.

To show the constraint firing **directly**, run the standalone CLI script
in `docs/evidence/double-booking-proof.md` §6 — it opens its own PDO
connection outside the web app, inserts a duplicate for an existing
booking's exact table/date/slot inside a transaction, and prints the
verbatim `SQLSTATE[23000]` / MySQL error 1062, then rolls back so nothing
in the seed data changes. That document also has the two-window UI steps
(§7) and, optionally, how to temporarily disable the layer-1 pre-check to
force a layer-2 demo instead.

**Files:** `docs/evidence/double-booking-proof.md` (both sections),
`customer/book.php`.

---

### Q3. Prepared statements protect against SQL injection — what do they *not* protect, and how do you cover that gap?

**Answer:** They can only bind **values** (`?`/named placeholders stand in
for a literal), never **identifiers** — a column or table name. You cannot
write `ORDER BY :column` and bind a column name to it; PDO would quote it
as a string literal and MySQL would reject the syntax. But the admin
listing pages let a user pick a sort column via `?sort=...` in the URL,
and that has to become raw SQL text somewhere. The fix is
`resolve_sort()` in `includes/listing.php`: each page defines a
hard-coded array like `['date' => 'r.reservation_date', 'created' =>
'r.created_at']` — only the *values* of that developer-written array
(never anything from `$_GET`) are ever concatenated into the `ORDER BY`
clause. If the URL's `sort` value isn't one of the array's keys, the code
silently falls back to a default column instead of passing anything
through.

**Files:** `includes/listing.php` (top-of-file comment + `resolve_sort()`),
any of `admin/bookings.php` / `tables.php` / `timeslots.php` / `users.php`
for a live example of the whitelist array.

---

### Q4. How does the CSRF protection work?

**Answer:** A random 256-bit token (`bin2hex(random_bytes(32))`) is
generated once per session and stored server-side in
`$_SESSION['csrf_token']`. Every form that changes state embeds it as a
hidden input via `csrf_field()`. Every POST handler calls `csrf_verify()`
as its very first action — before touching the database — comparing the
submitted token against the session's copy using `hash_equals()` rather
than `===`, specifically because `===` short-circuits at the first
mismatched byte, which is measurably faster for a "very wrong" guess than
a "one byte off" guess; `hash_equals()` always takes the same time so that
timing difference can't be used to guess the token byte-by-byte. A
missing or wrong token gets "Phien lam viec da het han..." and the request
is dropped before any mutation happens.

**Files:** `includes/helpers.php` (`csrf_token()`, `csrf_field()`,
`csrf_verify()`); any POST handler, e.g. the top of
`customer/my-reservations.php` or `admin/bookings.php`.

---

### Q5. Why are passwords hashed rather than encrypted?

**Answer:** Encryption is reversible — decrypt with the right key and you
get the original plaintext back. The app never needs to recover a user's
original password, only to check whether a freshly-typed one matches what
was stored, so a **one-way** function is the correct primitive, and it's
strictly safer: if the `users` table ever leaked, an encrypted column is
recoverable by anyone who also gets the key, while a bcrypt hash
(`password_hash()`'s default) is not reversible at all — only guessable,
and bcrypt is deliberately slow (a tunable cost factor) specifically to
make large-scale guessing expensive.

**Files:** `auth/register.php` (`password_hash($password,
PASSWORD_DEFAULT)`), `auth/login.php` (`password_verify()`).

---

### Q6. Why do all the seeded test accounts have the identical password hash?

**Answer:** Because bcrypt salts every hash randomly, calling
`password_hash()` on the *same* password twice produces two *different*-
looking hashes by design — that's the whole point of a salt, defending
against a precomputed rainbow-table attack across many accounts. There's
no way to write 7 accounts that all use the literal password
`Password123!` and have their `password_hash` column values look
different in a plain `.sql` seed file, short of running `password_hash()`
seven separate times and hard-coding each different result — which
`database/seed.sql` doesn't bother doing since it's demo data, not real
user data. The important distinction: this is a **seed-data-only**
artifact. Register two brand-new accounts through `auth/register.php` with
the same password and compare their `password_hash` values — they'll
differ, proving the real registration path salts correctly; the identical
seed hashes are just a shortcut for writing static demo SQL, not a flaw in
how hashing actually works at runtime.

**Files:** `database/seed.sql` (the `INSERT INTO users` block, note the
repeated `$2y$10$...` value), `auth/register.php`.

---

### Q7. How is role-based access control (customer vs. admin) enforced?

**Answer:** `includes/auth.php` defines `current_user()` (reads
`$_SESSION['user']`, written only by `auth/login.php` after a successful
`password_verify()`), and two guard functions: `require_login()` (redirect
to login if no session) and `require_admin()` (calls `require_login()`
first, then checks `$_SESSION['user']['role'] === 'admin'`, redirecting to
the homepage with a "no permission" flash otherwise). Every file under
`admin/` calls `require_admin()`, and every file under `customer/` calls
`require_login()`, as the **first executable statement**, before any HTML
is echoed — so a direct URL visit is blocked server-side regardless of
what links are or aren't shown in the navbar. This is enforced on every
single request, not cached or trusted from a previous page.

**Files:** `includes/auth.php`; the first few lines of any file in
`admin/` or `customer/`.

---

### Q8. How is the booking status lifecycle kept valid — what stops an illegal transition like `completed → pending`?

**Answer:** One function, `can_transition($from, $to)` in
`includes/reservation.php`, is the single source of truth for every legal
transition (`pending → confirmed|rejected|cancelled`; `confirmed →
completed|no_show|cancelled`; every other status is terminal — an empty
allowed-list). Every place in the app that changes a reservation's status —
customer cancel, admin approve/reject/complete/no-show — calls
**one** shared function, `change_reservation_status()`, which calls
`can_transition()` before writing anything; nowhere in the codebase does
any page run `UPDATE reservations SET status = ...` directly. If a
transition isn't allowed, the function returns a failure message and
nothing is written — verified in `docs/test-plan.md` TC-27 (a forged POST
attempting `confirmed → confirmed` is refused). `change_reservation_status()`
also locks the target row with `SELECT ... FOR UPDATE` inside a
transaction, so two near-simultaneous status changes on the same booking
(e.g. admin approves right as the customer cancels) can't both act on a
stale read of the old status.

**Files:** `includes/reservation.php` (`can_transition()`,
`change_reservation_status()`).

---

### Q9. Why do Cancel and Reject get a `confirm()` dialog but Approve doesn't?

**Answer:** The deciding factor is **reversibility under the status
lifecycle**, not which actor performs the action. `cancelled` and
`rejected` are both terminal states — there's no path back to
`pending`/`confirmed` from either, so a mis-click can only be undone by the
customer starting a brand-new reservation from scratch. Approving isn't
terminal in the same sense — a confirmed booking can still be cancelled by
the customer, or acted on later by the admin — and it's also the
highest-frequency admin action during a shift, so gating it behind a
confirmation dialog would add friction to the common case for no matching
reduction in risk.

**Files:** `docs/design-process.md` §7 ("Destructive action confirmation");
`public/js/main.js` (`data-confirm` handler); `data-confirm="..."`
attributes on the Cancel/Reject/Deactivate buttons across
`customer/my-reservations.php`, `admin/bookings.php`, `admin/tables.php`,
`admin/timeslots.php`, `admin/users.php`.

---

### Q10. Why does the open-redirect guard exist — what's the actual attack it stops?

**Answer:** The homepage's "Book a Table" CTA sends a signed-out visitor to
`auth/login.php?redirect=/customer/book.php` so they land back where they
meant to go after logging in. That `redirect` value comes from the URL —
anyone can craft their own link with a different value and send it to a
victim. If the app trusted it blindly, a phishing link could show the
*real* `goldenlotus` domain in the address bar while the victim logs in
(building trust, since the domain looks legitimate) and only redirect to a
look-alike phishing page **after** a genuine successful login — far less
suspicious than a link that looks wrong from the start.
`safe_redirect_target()` only accepts a value that starts with exactly one
`/` (blocks `//evil.example`, a protocol-relative URL), contains no
`://`/`scheme:` prefix (blocks `javascript:` payloads), and contains no
backslash or control characters (blocks `\`-based host confusion and
header injection). Anything else silently falls back to the normal
role-based dashboard.

**Files:** `includes/helpers.php` (`safe_redirect_target()`, has a long
Vietnamese rationale comment above it), `auth/login.php`, `index.php`.

---

### Q11. Why is `area` an ENUM on `tables` instead of a separate lookup table?

**Answer:** The four areas (Indoor Main, Terrace, Garden, VIP Room) are
locked business parameters in `CLAUDE.md` that aren't expected to change
during this project, so an ENUM keeps every query that touches `area`
(availability search, reports grouped by area) simpler with no extra join.
The documented trade-off: if the restaurant needed to add or rename areas
at runtime without a schema migration, a lookup table would be the better
choice — that's a genuine limitation of the ENUM approach, noted
deliberately rather than overlooked.

**Files:** `docs/data-dictionary.md` (`tables` section design note),
`database/schema.sql`.

---

### Q12. How does the "smallest sufficient table first" rule work, and why does it matter?

**Answer:** `get_available_tables()` orders its result
`ORDER BY t.capacity ASC, t.table_code ASC` after filtering to active
tables with `capacity >= party_size` and no conflicting active booking.
Ordering by capacity ascending means a 2-person party sees the smallest
table that fits them first, not an 8-seat VIP table — keeping large tables
free for large parties instead of a solo diner accidentally occupying one,
which is the locked business rule from `CLAUDE.md`.

**Files:** `includes/reservation.php` (`get_available_tables()`).

---

### Q13. How is pagination implemented safely — what stops someone passing a huge or negative `LIMIT`?

**Answer:** `paginate()` in `includes/listing.php` computes `total_pages`
from the actual row count and clamps the requested page into
`[1, total_pages]` before computing `offset` — so `?page=999` or `?page=-5`
can't produce an out-of-range or negative offset. The resulting `LIMIT`/
`OFFSET` values are always plain non-negative integers computed by PHP,
never taken as raw strings from `$_GET`, and are bound explicitly as
`PDO::PARAM_INT` via `bindValue()` — MySQL's native prepared-statement
protocol (`EMULATE_PREPARES=false`) actually requires that explicit
integer typing for `LIMIT`/`OFFSET` to work at all.

**Files:** `includes/listing.php` (`paginate()`, `get_current_page()`); the
`bindValue(':limit', ..., PDO::PARAM_INT)` calls in e.g.
`admin/bookings.php`.

---

### Q14. Why does `includes/db.php` set `PDO::ATTR_EMULATE_PREPARES => false`, and does it matter?

**Answer:** With emulation on (PHP's default), PDO builds the final SQL
string itself by substituting bound values in *before* sending it to
MySQL — which is safe as long as PDO's own escaping is correct, but it's
still PHP doing the escaping, not the database engine parsing a real
parameterised query. With emulation off, PHP sends the SQL template and
the parameter values as **separate** pieces over the wire; MySQL's own
protocol keeps them separate the whole time, so there is no string
ever assembled that could contain attacker-controlled SQL syntax.
It's also *why* the repeated-named-placeholder bug came up during testing
(`SQLSTATE[HY093]`) — native prepares don't allow the same named
placeholder twice in one statement, something emulated mode would have
silently allowed — so turning it off actually surfaced a real bug earlier
rather than masking it.

**Files:** `includes/db.php`; `docs/security-review.md` §2 (the bug and
its fix).

---

### Q15. How is session fixation prevented, and why does it matter here specifically?

**Answer:** `session_regenerate_id(true)` is called in `auth/login.php`
immediately after `password_verify()` succeeds, *before*
`$_SESSION['user']` is written — and again in `auth/logout.php` after
destroying the old session. Without this, if an attacker could get a
victim to use a session ID the attacker already knows (e.g. a crafted link
on a network without HTTPS enforced), the ID itself would stay the same
across the login boundary, so the attacker's copy of that ID would become
authenticated the moment the victim logged in — a session hijack without
ever seeing the victim's password. Regenerating the ID at that exact
boundary makes any pre-login ID worthless afterward.

**Files:** `auth/login.php`, `auth/logout.php`.

---

### Q16. Why is the login error message identical for "wrong password" and "email doesn't exist" — and what's the *dummy hash* for?

**Answer:** Two separate defences against the same goal (stopping an
attacker from learning which emails are registered — user enumeration).
First, the message itself: both cases show "Invalid email or password.",
so the text gives nothing away. Second, and more subtle: `password_verify()`
takes real, roughly-constant time to run (that's bcrypt's whole design).
If the code only called it when a matching user was actually found, the
"no such email" path would return almost instantly while the "wrong
password" path took the full verify time — an attacker measuring response
times could still tell the two cases apart even though the text is
identical. The fix: when no user matches, `password_verify()` still runs,
against a syntactically-valid dummy bcrypt string
(`'$2y$10$invalidinvalidinvalidinvalidinvalidu'`), so both paths cost
about the same wall-clock time.

**Files:** `auth/login.php` (the `$hash_to_check` ternary).

---

### Q17. Why can't an admin hard-delete a table or time slot that already has reservations?

**Answer:** Two layers again. The database layer:
`reservations.table_id`/`time_slot_id` are foreign keys with
`ON DELETE RESTRICT`, so MySQL itself refuses to delete a row still
referenced by any reservation — this guarantees no reservation can ever
end up pointing at a table/slot that no longer exists. The application
layer, on top of that: `admin/tables.php`/`admin/timeslots.php` proactively
`SELECT COUNT(*)` against `reservations` before attempting the delete, so
the admin gets a specific, friendly message ("...has N reservation(s) on
record. Use Deactivate instead...") rather than a raw constraint-violation
error. Deactivation (`is_active = 0`) is offered as the alternative: it
removes the row from future availability searches while keeping historical
bookings intact.

**Files:** `database/schema.sql` (`fk_reservations_table`,
`fk_reservations_slot`); `admin/tables.php`, `admin/timeslots.php` (the
`form_action === 'delete'` block in each).

---

### Q18. How do you know the admin dashboard's four numbers are real and not hard-coded?

**Answer:** Each tile is a live SQL query run on every page load, no
cached or static value anywhere: today's bookings is a `COUNT(*)` on
`reservation_date = CURDATE()` excluding cancelled/rejected; pending is a
plain `COUNT(*) WHERE status = 'pending'`; cancellation rate is
`cancelled_count / total_count * 100`, rounded; busiest slot is a
`GROUP BY time_slot_id` with `ORDER BY COUNT(*) DESC LIMIT 1`. You can
prove it live: approve or reject a pending booking from the dashboard's
own quick-action buttons and reload — the pending tile's number changes
immediately, because it's the same live query re-run, not a value fixed at
page-render time from something cached.

**Files:** `admin/dashboard.php` (every tile's query is inline above the
HTML, no separate "stats service" or cache layer).

---

### Q19. What's explicitly out of scope, and why?

**Answer:** Payment/deposit collection, food ordering, a loyalty
programme, multi-branch support, automated email/SMS notifications, a
native mobile app, live chat, multi-language UI, POS integration, a
waitlist for full slots, and table-combining for oversized parties — all
listed in `docs/requirements.md` §3 and §10. The common thread: none of
them are needed to demonstrate the core graded workflow (reservation
creation → conflict prevention → admin approval → status lifecycle →
reporting), and each would add real scope/complexity (e.g. table-combining
would break the "one table, one party, one slot" invariant the double-
booking constraint relies on) without adding marks under the rubric's
criteria. Keeping them out was a deliberate scoping decision, not an
oversight — CLAUDE.md's "golden rule" is that a feature that can't be
demonstrated running earns zero marks even if described in the report, so
effort went into fewer features working completely rather than more
features half-working.

**Files:** `docs/requirements.md` §3 ("Out of scope") and §10 ("Deferred /
out of scope").

---

### Q20. If you had more time, what would you do differently or add?

**Answer:** Be honest and specific rather than vague — this question
rewards demonstrating you understand the current design's actual limits,
not listing generic "add more features." Good, defensible answers drawn
from this project's real state: (1) close the TC-28 gap in
`can_transition()` so a `confirmed → completed/no_show` transition is
rejected server-side (not just hidden in the UI) until the booking's
date/time has actually passed; (2) add an audit-log table so status
changes have a full history, not just the current status plus
`actioned_by`/`actioned_at` (explicitly noted as a limitation in
`docs/requirements.md` §4); (3) load-test the double-booking constraint
beyond the small seeded dataset, since NFR-06 was proven correct but not
proven to scale; (4) a lookup table for `area` instead of an ENUM if the
restaurant ever needed runtime-configurable areas (see Q11). Pick 2-3 of
these rather than reciting all of them — depth over breadth.

**Files:** `docs/requirements.md` §4 ("Limitations"),
`docs/test-plan.md` (TC-28 and the defect table), `docs/remaining-work.md`.
