# Data Dictionary — Golden Lotus Restaurant Reservation System

Phase P2 deliverable. Describes the planned schema exactly as it will be
implemented in `database/schema.sql` (Phase P3), so requirements, diagrams,
and code stay consistent. Engine: MySQL 8 / MariaDB (XAMPP), `utf8mb4_unicode_ci`.

## `users`

| Column | Data type | Constraints | Description |
|---|---|---|---|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique user identifier |
| full_name | VARCHAR(100) | NOT NULL | User's full name |
| email | VARCHAR(150) | NOT NULL, UNIQUE | Login email (used as username) |
| password_hash | VARCHAR(255) | NOT NULL | Hash from PHP `password_hash()`, never plaintext |
| phone | VARCHAR(20) | NULL | Contact phone number |
| role | ENUM('customer','admin') | NOT NULL, DEFAULT 'customer' | Access role for RBAC |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | 0 = account locked by admin, cannot log in |
| created_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Account creation time |
| updated_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Last profile update time |

Index: UNIQUE on `email` (supports FR-01 duplicate check and NFR-01 fast login lookup).

## `tables`

| Column | Data type | Constraints | Description |
|---|---|---|---|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique table identifier |
| table_code | VARCHAR(10) | NOT NULL, UNIQUE | Table number, e.g. `T01`, `V03` |
| capacity | TINYINT UNSIGNED | NOT NULL, CHECK (capacity > 0) | Maximum party size the table seats |
| area | ENUM('indoor_main','terrace','garden','vip') | NOT NULL | Seating area (see design note below) |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | Inactive tables excluded from availability search |
| created_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Row creation time |

**Design note (area as ENUM vs. lookup table):** `area` uses a fixed `ENUM`
rather than a separate lookup table because the 4 areas (Indoor Main,
Terrace, Garden, VIP Room) are locked business parameters that will not
change during the project, and an ENUM keeps `tables` queries and the
availability search simpler with no extra join. If the restaurant needed to
add/rename areas at runtime, a lookup table would be preferred — noted here
so the trade-off is explicit for the viva.

## `time_slots`

| Column | Data type | Constraints | Description |
|---|---|---|---|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique slot identifier |
| start_time | TIME | NOT NULL | Slot start, e.g. `11:00:00` |
| end_time | TIME | NOT NULL | Slot end, e.g. `12:30:00` |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | Inactive slots excluded from the booking form |

Seeded with the 7 fixed 90-minute slots locked in `CLAUDE.md` (11:00 through 21:30).

## `reservations`

| Column | Data type | Constraints | Description |
|---|---|---|---|
| id | INT UNSIGNED | PK, AUTO_INCREMENT | Unique reservation identifier |
| user_id | INT UNSIGNED | NOT NULL, FK → `users.id` | Customer who made the booking |
| table_id | INT UNSIGNED | NOT NULL, FK → `tables.id` | Table reserved |
| time_slot_id | INT UNSIGNED | NOT NULL, FK → `time_slots.id` | Slot reserved |
| reservation_date | DATE | NOT NULL | Booking date (today .. +30 days at creation time) |
| party_size | TINYINT UNSIGNED | NOT NULL, CHECK (party_size > 0); app layer also enforces `party_size <= tables.capacity` | Number of guests |
| notes | VARCHAR(255) | NULL | Optional customer note |
| status | ENUM('pending','confirmed','completed','no_show','cancelled','rejected') | NOT NULL, DEFAULT 'pending' | Booking lifecycle state (see status lifecycle in `CLAUDE.md`) |
| actioned_by | INT UNSIGNED | NULL, FK → `users.id` | Admin who approved/rejected/marked the booking |
| actioned_at | DATETIME | NULL | When the admin action occurred |
| created_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP | Row creation time |
| updated_at | DATETIME | NOT NULL, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP | Last status/field change time |
| active_slot_key | VARCHAR(50), GENERATED ALWAYS AS (...) STORED | NULL when `status IN ('cancelled','rejected')`, otherwise `CONCAT(table_id,'_',reservation_date,'_',time_slot_id)` | Generated column that backs the double-booking constraint (see below); not written to directly |

**Double-booking constraint (FR-04, NFR-06) — implemented in Phase P3
(`database/schema.sql`):** a `UNIQUE KEY uq_reservations_active_slot` sits on
the generated column `active_slot_key`, not directly on `(table_id,
reservation_date, time_slot_id)`. That column evaluates to `NULL` whenever
`status` is `cancelled` or `rejected`, and to a `table_id_date_slot` string
for every other status (`pending`, `confirmed`, `completed`, `no_show`).
Because MySQL/MariaDB treat every `NULL` in a unique index as distinct from
every other `NULL`, cancelled/rejected rows never block a new booking from
reusing that table/date/slot, while any two rows that are both still "active"
for the same table/date/slot collide immediately at `INSERT`/`UPDATE` time —
enforced atomically by the storage engine, not by an application-level
check-then-write race. A `BEFORE INSERT` trigger doing a manual `SELECT`
check was considered and rejected for the same reason: the check and the
write aren't atomic without extra manual locking, so a trigger alone would
still permit two near-simultaneous inserts to both pass the check. Full
trade-off discussion (in Vietnamese, as required by `CLAUDE.md`'s coding
rules) is in the `reservations` table comment block in `database/schema.sql`.

Planned indexes (beyond the PK/FKs): `reservations(reservation_date)`,
`reservations(status)` — support FR-09 search/filter/sort/pagination and
NFR-01 performance target.

## Traceability

This schema directly implements the entities referenced throughout
`docs/requirements.md` (FR-01…FR-15) and is the system modelled by
`docs/diagrams/use-case.mmd`, `activity-booking.mmd`, and
`sequence-booking.mmd`. No table or column exists here that isn't needed by
a locked feature; no locked feature is missing a column to support it.
