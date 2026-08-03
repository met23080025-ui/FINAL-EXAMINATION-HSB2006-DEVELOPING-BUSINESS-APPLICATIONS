# Golden Lotus Restaurant — Reservation System

> HSB2006 – Developing Business Applications (Class MET4) final project.
> Authentic Vietnamese dining — reserve your table in seconds.

## Project purpose

A database-driven web application that lets customers browse tables and time
slots, book a reservation online, and manage their booking history — while
letting restaurant admins approve/reject bookings, manage tables and time slots,
manage users, and view booking reports.

## Features

**Customer**
- Register / log in / log out
- Browse tables and available time slots for a chosen date
- Make a reservation (date, time slot, party size, notes) with automatic
  double-booking prevention
- View and filter own reservation history
- Cancel a pending/confirmed booking
- Edit profile and change password

**Admin**
- Dashboard: today's bookings, pending count, cancellation rate, busiest time slot
- Booking list with search, filter, sort, and pagination
- Approve / reject bookings; mark completed / no_show
- CRUD for tables and service time slots
- User management (view, lock/unlock, change role)
- Booking statistics with CSV export

## Technology stack

- PHP 8.x (procedural / lightweight OOP, no framework)
- MySQL 8 / MariaDB (via XAMPP)
- PDO with prepared statements for all database access
- HTML5, CSS3, Bootstrap 5 (CDN), vanilla JavaScript
- No Node.js, no Firebase, no ORM

## Installation (local, XAMPP)

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** + **MySQL**.
2. Clone this repository into your XAMPP `htdocs` folder, e.g.
   `C:\xampp\htdocs\golden-lotus` (or `/Applications/XAMPP/htdocs/golden-lotus`).
3. Copy `config.sample.php` to `config.php` and adjust the DB credentials if your
   XAMPP MySQL setup differs from the defaults (`root` / empty password).
4. Import the database — see next section.
5. Visit `http://localhost/<folder-name>/` in your browser (adjust `BASE_URL` in
   `config.php` to match your folder name).

## Database import

1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
2. Create a new database named `golden_lotus` (or matching `DB_NAME` in
   `config.php`).
3. Import `database/schema.sql` first (creates tables/constraints).
4. Import `database/seed.sql` second (adds sample data: tables, time slots, test
   accounts, and demo reservations).

## Test accounts

All seeded accounts share the same demo password: **`Password123!`**
(stored as a real bcrypt hash in `database/seed.sql`, never plaintext).

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

*(to be updated as development progresses and in Phase P8/P9 — e.g. no payment
processing, no email notifications, single-restaurant/single-branch only)*

## Third-party assets and licences

- [Bootstrap 5](https://getbootstrap.com/) — MIT License, loaded via CDN.
- *(add any icon sets, fonts, or images used, with their licences, as they are
  introduced)*

## Repository & project management

- GitHub repository: *(link — add once created, Phase P0/P1)*
- GitHub Project board: *(link — add once created, Phase P1)*

## Team

See `docs/requirements.md` for the team contribution table.

## Development status

This project follows a 15-day phased roadmap documented in `CLAUDE.md`. See that
file for full context, locked business rules, and coding standards.
