# Evidence: Double-Booking Constraint Enforced at the Database Level

> Captured 2026-08-03, during Phase P3 verification (before Phase P4 started).
> Evidence for marking criterion 4 (Database & Backend Development, 45 pts) and
> NFR-06 (data integrity / no double-booking under concurrency) — referenced from
> `docs/report-content.md` §7 (database schema) and §9 (security controls).

## What was tested, and why it matters

The business rule (`CLAUDE.md`, locked parameters) is: one table may hold at
most one **non-cancelled** reservation per `(table_id, reservation_date,
time_slot_id)`. The rule must be enforced in PHP **and** at the database level,
because a PHP-only "check, then insert" is not atomic — two near-simultaneous
requests can both pass the check before either has inserted, producing two
active bookings for the same table/date/slot (a classic TOCTOU race).

`database/schema.sql` implements the database-level guarantee with a `STORED`
generated column, `reservations.active_slot_key`, plus a `UNIQUE KEY` on that
column (full rationale in the Vietnamese comment block above `CREATE TABLE
reservations` in that file, and in `docs/data-dictionary.md`). This document
proves that guarantee actually fires against the live, seeded database —
it is not just a design claim.

Three things were verified:
1. The generated column and unique index exist exactly as designed.
2. A direct duplicate `INSERT` for an already-booked table/date/slot is
   rejected by MySQL itself, inside a transaction that was then rolled back
   (so the seed data was left untouched).
3. The reservations row count was unchanged (57) after the rollback.

## 1. `information_schema` output — generated column

Query:
```sql
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, GENERATION_EXPRESSION, EXTRA
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'golden_lotus' AND TABLE_NAME = 'reservations'
  AND COLUMN_NAME = 'active_slot_key';
```

Result:
```
[COLUMN_NAME] => active_slot_key
[COLUMN_TYPE] => varchar(50)
[IS_NULLABLE] => YES
[GENERATION_EXPRESSION] => case when `status` in ('cancelled','rejected') then NULL else concat(`table_id`,'_',`reservation_date`,'_',`time_slot_id`) end
[EXTRA] => STORED GENERATED
```

Confirms `active_slot_key` is a real `STORED GENERATED` column (not `VIRTUAL`,
not application-computed) with exactly the `CASE` expression documented in the
schema comments.

## 2. `information_schema` output — unique index

Query:
```sql
SELECT INDEX_NAME, COLUMN_NAME, NON_UNIQUE, SEQ_IN_INDEX
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = 'golden_lotus' AND TABLE_NAME = 'reservations'
  AND INDEX_NAME = 'uq_reservations_active_slot';
```

Result:
```
[INDEX_NAME]   => uq_reservations_active_slot
[COLUMN_NAME]  => active_slot_key
[NON_UNIQUE]   => 0
[SEQ_IN_INDEX] => 1
```

`NON_UNIQUE = 0` confirms this is a true unique index on `active_slot_key`.

## 3. The reservation row used as the duplicate target

```
[id]               => 33
[user_id]          => 4
[table_id]         => 2
[reservation_date] => 2026-08-03
[time_slot_id]     => 3
[party_size]       => 1
[status]           => confirmed
```

An already-`confirmed` seeded booking for table 2, 2026-08-03, time slot 3.

## 4. Duplicate INSERT attempt — verbatim MySQL error

Inside a PDO transaction (`PDO::ATTR_ERRMODE = PDO::ERRMODE_EXCEPTION`,
`PDO::ATTR_EMULATE_PREPARES = false`), attempting to insert a second row for
the same `(table_id=2, reservation_date=2026-08-03, time_slot_id=3)` with
status `pending`:

```
SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '2_2026-08-03_3' for key 'uq_reservations_active_slot'
```

MySQL error 1062 (duplicate entry) is raised by the storage engine itself, at
`INSERT` time — before any application code decides anything — confirming the
constraint is atomic and cannot be bypassed by application logic.

## 5. Rollback confirmation

- The transaction was explicitly `ROLLBACK`'d after the exception was caught.
- Post-rollback row count: `SELECT COUNT(*) FROM reservations` → **57**
  (unchanged from the pre-test count), confirming no partial/orphaned row was
  left behind and the seed data integrity was preserved.

## 6. Exact steps to reproduce (for live demo / viva)

These steps use a standalone PHP script run via CLI, **outside** `htdocs`, so
nothing in the repo or the live app is modified. `includes/db.php` does not
yet create a `$pdo` object (Phase P4b work), so the script builds its own PDO
connection directly from `config.php`'s constants.

1. Confirm XAMPP's Apache + MySQL are running and `config.php` exists at the
   repo root (copied from `config.sample.php`, DB credentials filled in).
2. Create a temporary PHP file **outside** the repo (e.g. in a scratch/temp
   folder, never inside `htdocs`) with this content:

   ```php
   <?php
   require 'C:/xampp/htdocs/golden-lotus/config.php';

   $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
   $pdo = new PDO($dsn, DB_USER, DB_PASS, [
       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
       PDO::ATTR_EMULATE_PREPARES => false,
   ]);

   // Pick any existing pending/confirmed reservation to target.
   $existing = $pdo->query("
       SELECT user_id, table_id, time_slot_id, reservation_date, party_size
       FROM reservations
       WHERE status IN ('confirmed','pending')
       ORDER BY id LIMIT 1
   ")->fetch(PDO::FETCH_ASSOC);

   $before = $pdo->query("SELECT COUNT(*) AS c FROM reservations")->fetch(PDO::FETCH_ASSOC)['c'];

   $pdo->beginTransaction();
   try {
       $ins = $pdo->prepare("
           INSERT INTO reservations
               (user_id, table_id, time_slot_id, reservation_date, party_size, notes, status)
           VALUES (?, ?, ?, ?, ?, 'DEMO duplicate test', 'pending')
       ");
       $ins->execute([
           $existing['user_id'], $existing['table_id'], $existing['time_slot_id'],
           $existing['reservation_date'], $existing['party_size'],
       ]);
       echo "UNEXPECTED: insert succeeded" . PHP_EOL;
   } catch (PDOException $e) {
       echo "Insert rejected as expected:" . PHP_EOL . $e->getMessage() . PHP_EOL;
   } finally {
       $pdo->rollBack();
   }

   $after = $pdo->query("SELECT COUNT(*) AS c FROM reservations")->fetch(PDO::FETCH_ASSOC)['c'];
   echo "Row count before: $before, after rollback: $after" . PHP_EOL;
   ```

3. Run it: `C:\xampp\php\php.exe path\to\that\script.php`
4. Expected output: a `SQLSTATE[23000] ... 1062 Duplicate entry` message, and
   the row count identical before and after.
5. Delete the temporary script afterward — it is not part of the repo.

This can be re-run live at any time without affecting seed data, since it
always rolls back.

## 7. UI-level reproduction (Phase P6, `customer/book.php`)

> Added after Phase P6 (`includes/reservation.php`'s `create_reservation()`,
> `customer/book.php`). Sections 1-6 above prove the constraint fires at the
> SQL level via a CLI script; this section proves the same guarantee holds
> through the actual customer-facing interface, end to end, with a friendly
> error rather than a crash — for the report and the viva demo. The exact
> steps and expected messages below were run for real (twice — once as two
> sequential requests sharing a stale page, once as two genuinely parallel
> HTTP requests) against the live app before being written down, not assumed.

### Two independent layers, and which one a human demo actually exercises

`customer/book.php`'s confirm handler defends this in two layers, not one:

1. Before inserting, it re-runs `get_available_tables()` and rejects if the
   submitted `table_id` is no longer in that fresh result — this is an
   ordinary `SELECT`-based check, not the database constraint.
2. Only if layer 1 passes does it call `create_reservation()`, which does
   the actual `INSERT` and is the one that would catch a
   `uq_reservations_active_slot` violation (`SQLSTATE[23000]` / MySQL error
   `1062`) if one occurred.

Layer 1 exists precisely because it catches the overwhelmingly common case
cheaply, without needing a failed `INSERT` at all. Testing both a
sequential two-window click and two `curl` requests fired in true parallel
(`&` + `wait` in bash, no artificial delay) against the live app, layer 1
caught the conflict **every time** — the second request's `SELECT` recheck
already saw the first request's committed row, so it never reached the
`INSERT` at all. The message a demo will realistically show is therefore
layer 1's: *"Sorry, that table is no longer available for this date, time,
and party size. Please choose another table."* Layer 2's message ("that
table was just taken") guards a narrower window still — between layer 1's
`SELECT` and the `INSERT` completing — which sections 1-6 above already
prove fires correctly at the SQL level directly; a human-timed demo is not
precise enough to land inside that specific gap, and that is expected, not
a gap in the defence: either layer stops the duplicate, and the database
constraint is still what makes layer 1 itself safe to trust (layer 1 alone,
without the constraint backing it, would be exactly the non-atomic
"check-then-write" TOCTOU bug this whole design avoids).

### Exact steps

1. **Use two independent sessions**, not two tabs in the same browser —
   two tabs share one `PHPSESSID` cookie and would both be logged in as the
   same user. Use two different browsers (e.g. Chrome + Firefox), or one
   normal window and one private/incognito window.
2. **Window A:** log in as `customer1@goldenlotus.test` / `Password123!`.
   **Window B:** log in as `customer2@goldenlotus.test` / `Password123!`.
3. In **both** windows, go to *Book a Table* and search with the **identical**
   date, party size, and time slot — pick a date/slot combination not
   already used by the seed data (e.g. today + 28 days, party size 2, slot
   `11:00-12:30`), so the result set is predictable.
4. Confirm both windows show the same list of available tables, with the
   same smallest-sufficient table at the top (e.g. `T01`, 2 seats).
5. In **both** windows, select that **same table** via its radio button.
   Do **not** click "Confirm Reservation" in either window yet — both pages
   now hold the same "table is free" snapshot, exactly like two customers
   who both loaded availability moments before either commits.
6. Click **Confirm Reservation in Window A**. Expected: green flash
   *"Your reservation has been submitted and is pending approval."*,
   landing on My Bookings with a new `pending` row for `T01` on that
   date/slot.
7. **Without refreshing or re-searching**, click **Confirm Reservation in
   Window B** (it is still showing the same stale "T01 is available" page
   from step 5). Expected: red flash *"Sorry, that table is no longer
   available for this date, time, and party size. Please choose another
   table."*, landing back on the search-results page for the same
   date/slot/party size — no PHP error page, no HTTP 500. If Window B
   searches again from this point, `T01` no longer appears in the results.
8. **Verify in the database** — exactly one row exists despite two
   submissions for the identical `(table, date, slot)`:
   ```sql
   SELECT id, user_id, status FROM reservations
   WHERE table_id = <T01's id> AND reservation_date = '<the test date>'
     AND time_slot_id = <the slot id>;
   ```
   Expected: a single row, `user_id` = customer1's id, `status = 'pending'`.
   Customer2's attempt left no row at all — it was rejected before any
   `INSERT` was attempted (see the layer explanation above), so there is
   nothing to roll back and nothing partial was written.
9. For the report (§8 implementation evidence): screenshot Window A's
   success flash and Window B's conflict flash together — this is the
   customer-facing counterpart to the raw SQL error in section 4 above, and
   should be captured alongside the admin-side screenshots reminded of
   elsewhere in this project.
10. **Optional, for completeness:** to actually observe layer 2's message
    and confirm it independently (rather than relying on sections 1-6's
    separate CLI proof), an admin/tester can temporarily comment out the
    `get_available_tables()` recheck block in `customer/book.php` and repeat
    steps 6-8 — with layer 1 disabled, the request now reaches
    `create_reservation()`, and Window B should instead show *"Sorry, that
    table was just taken for this date and time. Please choose another
    table."* Revert the change immediately afterward; this is a
    demonstration step only, never to be left disabled.
