# Screenshot Checklist — Report §8 (Implementation Evidence)

Every screenshot needed for `docs/report-content.md` §8, in the order they
should appear in the report (customer flow first, then admin, then the
security/integrity evidence shots). For each: the exact page/URL, the state
to set up **before** taking the shot, and what to annotate on it (arrows,
boxes, or captions — a plain unannotated screenshot earns far less credit
than one that visibly points at the thing being demonstrated).

**General rules for every shot:**
- Use the seeded test accounts (README "Test accounts"). Don't use real
  personal data.
- Browser window at a normal desktop width for admin pages (dense tables
  need room); take **at least 2 customer-facing shots at a narrow width
  (~375px)** — use browser DevTools device toolbar — to back up NFR-03's
  mobile-usability claim.
- Crop out unrelated browser chrome (bookmarks bar, unrelated tabs) but
  keep the address bar visible where the URL itself is part of the point
  (e.g. proving a direct-URL admin-page block).
- Save as PNG, filename pattern `P<phase>-<page>-<state>.png` (e.g.
  `p7-admin-dashboard-tiles.png`), into a `docs/evidence/screenshots/`
  folder (create it) so they're easy to find and reference by name in the
  report text.

---

## A. Customer flow

| # | Page / URL | Setup first | Annotate |
|---|---|---|---|
| A1 | `index.php`, logged out | Nothing — default landing state | Circle the single primary CTA ("Book a Table") and note the hero/info-band layout matches the wireframe in `docs/design-process.md` §4.1 |
| A2 | `auth/register.php`, an invalid submission | Submit with a weak password and a mismatched confirm | Box the inline field-level errors (not just a top banner) — evidence for NFR-03 |
| A3 | `auth/register.php`, success | Register a genuinely new test account | Box the success flash + redirect to login |
| A4 | `auth/login.php`, invalid credentials | Submit wrong password | Box the generic "Invalid email or password." message — annotate "same message for wrong password AND unknown email" (ties to security review §4) |
| A5 | `customer/dashboard.php`, no upcoming reservation | Log in as a customer with none pending/confirmed in the future (or a fresh registration) | Box the empty-state message and the "Book a Table" link |
| A6 | `customer/dashboard.php`, with an upcoming reservation | Log in as a customer with a future pending/confirmed booking | Box the prominent next-reservation card, the status-count badges, and the recent-history table |
| A7 | `customer/book.php`, search form only | Load the page before searching | Box the date/party-size/slot search bar — note progressive disclosure (no results/table list shown yet) |
| A8 | `customer/book.php`, results list | Search a date/slot/party size with several available tables | Box the smallest-sufficient-table-first ordering (annotate capacities to prove it) |
| A9 | `customer/book.php`, empty-state (no tables) | Search party size 99 (or a fully-booked slot) | Box the exact empty-state wording |
| A10 | `customer/book.php`, submitting | Select a table, click Confirm Reservation, screenshot **during** the brief disabled/spinner state (may need a slow network throttle in DevTools to catch it) | Circle the disabled button + spinner + "Submitting..." label |
| A11 | `customer/my-reservations.php`, mixed statuses | A customer with several bookings across different statuses | Box at least 3 different status badge colours side by side, referencing the colour mapping in `docs/design-process.md` §5.1 |
| A12 | `customer/my-reservations.php`, status filter applied | Filter to one status via the dropdown | Box the filtered result + confirm the URL shows `?status=...` (bookmarkable) |
| A13 | `customer/my-reservations.php`, cancel confirmation dialog | Click "Cancel" on a cancellable row, screenshot the `confirm()` browser dialog before accepting | Box the exact confirmation wording |
| A14 | `customer/my-reservations.php`, after cancel | Confirm the dialog | Box the success flash and the row's badge now showing `Cancelled` |
| A15 | `customer/profile.php`, validation error | Submit a mismatched new-password/confirm pair | Box the inline error |
| A16 | `customer/profile.php`, success | Successfully update full name/phone | Box the success flash |
| A17 | `customer/book.php` at 375px width | DevTools device toolbar, iPhone SE or similar | Full-page shot proving the layout is still usable (form fields stack, nothing is cut off) — NFR-03 evidence |
| A18 | `customer/my-reservations.php` at 375px width | Same narrow width | Full-page shot showing `.table-responsive` horizontal scroll still exposes every column |

## B. Admin flow

| # | Page / URL | Setup first | Annotate |
|---|---|---|---|
| B1 | `admin/dashboard.php` | Log in as admin, default view | Box all four tiles with their icons/values, and the pending-queue preview below — label each tile with the FR it satisfies (FR-08) |
| B2 | `admin/dashboard.php`, pending queue action | Click Approve on a preview row | Box the success flash and the tile numbers changing (compare a before/after pair if possible) |
| B3 | `admin/bookings.php`, unfiltered | Default view | Box the "pending rows float to top" behaviour — point at 2-3 pending rows above confirmed/completed ones |
| B4 | `admin/bookings.php`, filters applied | Apply keyword + status + area + date-range filters together | Box each active filter value and the "Showing X-Y of Z bookings" line updating |
| B5 | `admin/bookings.php`, sort indicator | Click a sortable column header | Circle the ▲/▼ arrow next to the column label |
| B6 | `admin/bookings.php`, pagination | A filtered/unfiltered view with >15 rows | Box the pagination control at the bottom, current page highlighted |
| B7 | `admin/bookings.php`, empty filter result | Filter to a combination matching nothing (e.g. a nonsense keyword) | Box the exact "No bookings match these filters..." wording + Clear filters link |
| B8 | `admin/bookings.php`, reject confirmation dialog | Click "Reject" on a pending row, screenshot the `confirm()` dialog before accepting | Box the exact wording |
| B9 | `admin/tables.php`, list | Default view | Box the reservation-count column and the Active/Inactive badges |
| B10 | `admin/tables.php`, Add Table modal open | Click "+ Add Table" | Box the modal form fields and validation hint text |
| B11 | `admin/tables.php`, validation error | Submit a duplicate table code | Box the danger flash with the exact error message |
| B12 | `admin/tables.php`, delete refused | Attempt to delete a table with reservations (button is hidden in UI when count > 0 — use a table you know has bookings and note in the caption that this was tested via the safeguard, referencing TC-33) | Box the "Cannot delete this table..." message |
| B13 | `admin/timeslots.php`, overlap rejected | Try to add a slot overlapping an existing active one | Box the "This time range overlaps..." message |
| B14 | `admin/users.php`, list | Default view | Box the role badges, active/inactive badges, and the "You" badge on the logged-in admin's own row |
| B15 | `admin/users.php`, self-row protection | Point at the "You" row | Box the absence of Deactivate/role controls on that row specifically, contrasted with a normal row that has them |
| B16 | `admin/users.php`, role change | Change a customer's role to admin and back | Box the confirm() dialog and the resulting role badge change |
| B17 | `admin/reports.php`, charts | Load with the default date range (has seed data) | Box all four panels (per-day, by status, by area, busiest slots) plus the average-party-size tile |
| B18 | `admin/reports.php`, CSV downloaded | Click Export CSV, open the file | Screenshot the file opened in Excel/a spreadsheet viewer showing clean columns, plus the browser's download filename bar showing `bookings_report_<from>_to_<to>.csv` |
| B19 | `admin/dashboard.php` at 375px (optional but recommended) | Narrow width | Shows the tile grid reflows to a single column rather than breaking |

## C. Security / integrity evidence

| # | What to capture | Setup first | Annotate |
|---|---|---|---|
| C1 | Direct-URL admin block | Log in as a customer, type `admin/dashboard.php` into the address bar | Box the address bar (showing the attempted URL) together with the redirect result and flash message — this is the single most direct access-control proof |
| C2 | Open-redirect rejected | Log in via `auth/login.php?redirect=https://evil.example` | Box the address bar showing the malicious `redirect=` param, and the final landing page proving it was ignored |
| C3 | Double-booking race, Window A | Follow `docs/evidence/double-booking-proof.md` §7 exactly | Box the success flash for the first submitter |
| C4 | Double-booking race, Window B | Same session, the stale-page second submit | Box the conflict flash — ideally place C3/C4 side by side in the report as one figure |
| C5 | CSRF rejection | Submit a form with a stripped/corrupted `csrf_token` (see TC-42) | Box the "Phien lam viec da het han..." message |
| C6 | XSS neutralised | Book with `<script>alert(1)</script>` in the notes field, view it rendered | Box the literal escaped text on both the customer and admin view, annotate "no alert fired" |
| C7 | SQL injection harmless | Search `admin/bookings.php` with `' OR '1'='1` in the keyword box | Box the "no bookings match" empty state — annotate that the app is still running normally, no SQL error surfaced |
| C8 | Double-booking constraint at the DB level (optional, strong evidence) | Re-run the CLI script in `docs/evidence/double-booking-proof.md` §6 | Screenshot the terminal output showing the verbatim `SQLSTATE[23000]` / MySQL 1062 error |

---

## Coverage check before submission

- [ ] At least one screenshot per FR-01 through FR-15 (cross-check against
      `docs/requirements.md` §6 — every functional requirement should be
      visibly demonstrated somewhere in A/B above)
- [ ] At least 2 mobile-width (375px) shots (A17, A18, optionally B19)
- [ ] At least one shot per status badge colour (A11 covers most; add a
      `no_show` example separately if it wasn't in that customer's history —
      check `admin/bookings.php` filtered to `no_show`)
- [ ] All 8 items in section C captured — these are the security/integrity
      proof points criterion 5 specifically rewards
- [ ] Every screenshot has at least one annotation (arrow, box, or caption)
      — an unannotated raw screenshot is much weaker evidence
