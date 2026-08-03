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
