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

**Double-booking constraint (FR-04, NFR-06):** a unique index on
`(table_id, reservation_date, time_slot_id)` scoped to non-cancelled/
non-rejected rows is required so the database itself rejects a duplicate
active booking, while still allowing a new booking to reuse a slot that a
prior `cancelled`/`rejected` row occupied. The exact mechanism (generated
column that is `NULL` for cancelled/rejected rows, vs. a trigger) is decided
and implemented in Phase P3 (`database/schema.sql`) per the `CLAUDE.md`
instruction, and documented there with Vietnamese comments explaining the
trade-off.

Planned indexes (beyond the PK/FKs): `reservations(reservation_date)`,
`reservations(status)` — support FR-09 search/filter/sort/pagination and
NFR-01 performance target.

## Traceability

This schema directly implements the entities referenced throughout
`docs/requirements.md` (FR-01…FR-15) and is the system modelled by
`docs/diagrams/use-case.mmd`, `activity-booking.mmd`, and
`sequence-booking.mmd`. No table or column exists here that isn't needed by
a locked feature; no locked feature is missing a column to support it.
