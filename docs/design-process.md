# UI/UX Design Process — Golden Lotus Restaurant Reservation System

Phase P4 deliverable. Written before any page markup exists (Phase P4b/P5+),
so the visual language and navigation are decided once and applied
consistently afterward, rather than improvised page by page. Traces back to
the actors and requirements in `docs/requirements.md` §5–7 and forward to
`public/css/style.css` (design tokens) and the report §3 (Implementation
evidence — screenshots).

## 1. User needs analysis

Two actors (`docs/requirements.md` §5), two very different contexts of use:

| | Customer | Admin |
|---|---|---|
| Device | Mostly phone, booking on the go (down to 375px per NFR-03) | Mostly desktop, at the restaurant during service |
| Session length | Short (book in "a couple of minutes" — §2 objectives) | Long (working the pending queue repeatedly through a shift) |
| Primary need | Confidence the booking went through, and its current status | Speed: see today's load, clear pending queue, act on it |
| Tolerance for clutter | Low — one clear task at a time | Higher — dense tables/filters are expected and wanted |
| Error costs | A missed conflict = arriving to no table | A missed pending booking = an unhappy walk-in customer |

Design consequences drawn from this:
- Customer-facing pages: one primary action per screen, generous spacing,
  large touch targets, minimal choices visible at once (progressive
  disclosure of the booking form: date → slot → party size → table).
- Admin-facing pages: information-dense tables, inline filters, and
  status-colour-coded rows so the pending queue is scannable at a glance —
  this directly serves FR-08/FR-09/FR-10/FR-11.
- Both: every state-changing action gives immediate, unambiguous feedback
  (flash message + updated status), because "silent failure" is explicitly
  ruled out by NFR-03.

## 2. Sitemap

```
/ (index.php — public landing)
├── auth/
│   ├── register.php
│   ├── login.php
│   └── logout.php
├── customer/  (requires customer session)
│   ├── book.php            — browse availability + create reservation (FR-03, FR-04)
│   ├── my-reservations.php — history, filter by status, cancel (FR-05, FR-06)
│   └── profile.php         — edit profile / change password (FR-07)
└── admin/  (requires admin session)
    ├── dashboard.php        — summary tiles (FR-08)
    ├── bookings.php         — search/filter/sort/paginate + approve/reject/complete/no_show (FR-09, FR-10, FR-11)
    ├── tables.php           — table CRUD (FR-12)
    ├── time-slots.php       — time slot CRUD (FR-13)
    ├── users.php            — user management (FR-14)
    └── reports.php          — date-range stats + CSV export (FR-15)
```

Every logged-in page shares one header/footer (`includes/header.php`,
`includes/footer.php`, built in Phase P4b) with role-aware navigation: the
customer nav shows Book / My Reservations / Profile; the admin nav shows
Dashboard / Bookings / Tables / Time Slots / Users / Reports. A visitor with
no session only ever sees Login / Register.

## 3. User flows

**Customer — make a reservation (the core workflow, see
`docs/diagrams/activity-booking.mmd` for the full decision tree):**
Landing → Login (if not already) → Book a Table → pick date/party size →
system lists available tables for that date, filtered by slot and capacity →
pick table + slot → confirm → flash "submitted, pending approval" →
My Reservations shows it as `pending`.

**Customer — cancel:**
My Reservations → find booking (status `pending`/`confirmed`) → Cancel →
confirm → flash "cancelled", row updates in place, no page reload of the
whole list needed beyond the one row.

**Admin — clear the pending queue:**
Dashboard (sees pending count tile) → click through to Bookings filtered to
`pending` → Approve or Reject per row → flash confirms → dashboard's pending
count decrements on next view.

**Admin — end of service:**
Bookings filtered to today, status `confirmed`, past slot end time →
mark Completed or No-show per row.

## 4. Wireframes — 5 main screens

Text wireframes only at this stage (Phase P4); annotated real screenshots are
captured in Phases P5–P7 for report §8.

### 4.1 Landing page (`index.php`)

```
┌──────────────────────────────────────────────────┐
│ Golden Lotus                    [Login] [Register]│
├──────────────────────────────────────────────────┤
│        Authentic Vietnamese dining —               │
│        reserve your table in seconds.               │
│               [ Book a Table ]                      │
├──────────────────────────────────────────────────┤
│  Opening hours: 11:00–22:00 daily                   │
│  4 areas: Indoor Main · Terrace · Garden · VIP      │
└──────────────────────────────────────────────────┘
```
One primary CTA above the fold; secondary info (hours/areas) below, not
competing for attention.

### 4.2 Login / Register

```
┌───────────────────────────┐
│         Login              │
│  Email    [____________]   │
│  Password [____________]   │
│  [ Log in ]                │
│  No account? Register →    │
│  (flash: validation errors │
│   appear here, above form) │
└───────────────────────────┘
```
Single centred card, one form, one primary button — matches the "low
clutter, one task" rule from §1.

### 4.3 Book a Table (`customer/book.php`)

```
┌──────────────────────────────────────────────────┐
│ Date [____] Party size [__] Slot [11:00-12:30 ▾] [Search]│
├──────────────────────────────────────────────────┤
│ Available tables:                                    │
│  ○ T05 — Indoor Main — seats 4   [Select]           │
│  ○ T12 — Terrace     — seats 6   [Select]           │
│  ○ V01 — VIP Room    — seats 8   [Select]           │
│  (smallest sufficient table listed first — locked rule)│
├──────────────────────────────────────────────────┤
│ Notes (optional) [______________________]           │
│                [ Confirm Reservation ]              │
└──────────────────────────────────────────────────┘
```
Progressive disclosure: table list and the confirm button only appear after
a search; prevents the customer from being shown irrelevant controls first.

### 4.4 My Reservations (`customer/my-reservations.php`)

```
┌──────────────────────────────────────────────────┐
│ Filter status: [All ▾]                              │
├──────────────────────────────────────────────────┤
│ 2026-08-10  18:30-20:00  T05  4 guests  [Confirmed] │
│ 2026-08-03  14:00-15:30  T12  2 guests  [Pending] [Cancel]│
│ 2026-07-28  17:00-18:30  V01  8 guests  [Completed] │
└──────────────────────────────────────────────────┘
```
Status shown as a colour-coded badge (see §5); Cancel button only rendered
for rows whose status is `pending`/`confirmed`, per FR-06.

### 4.5 Admin Dashboard (`admin/dashboard.php`)

```
┌──────────────────────────────────────────────────┐
│ [Today: 12]  [Pending: 5]  [Cancel rate: 8%]  [Busiest: 18:30-20:00]│
├──────────────────────────────────────────────────┤
│ Pending queue (top 5) →  [ Go to full list ]        │
│  09:40  customer3  T09  4 guests  [Approve][Reject] │
│  ...                                                 │
└──────────────────────────────────────────────────┘
```
Four summary tiles answer FR-08 at a glance; the pending-queue preview below
gives a one-click path into `admin/bookings.php` (FR-09/FR-10) without
forcing a navigation click first — the highest-frequency admin action is
reachable from the very first screen of a shift.

## 5. Colour palette and typography (with rationale)

CSS custom properties defined in `public/css/style.css :root`, layered on top
of (not replacing) Bootstrap 5 per the mandatory stack.

| Token | Value | Use | Rationale |
|---|---|---|---|
| `--gl-primary` | `#0B5D3B` (deep emerald green) | Header, nav, primary buttons | Evokes Vietnamese lacquerware/garden greens; distinct from Bootstrap's default blue so the brand reads as its own restaurant, not "a Bootstrap template" |
| `--gl-accent` | `#C9A227` (antique gold) | Headings accents, hover states, badges | "Golden Lotus" — the literal brand colour; used sparingly as an accent, never as body text (fails contrast on light backgrounds) |
| `--gl-accent-soft` | `#F4E7C1` | Card highlights, hover backgrounds | Muted tint of the gold accent for subtle emphasis without shouting |
| `--gl-text` | `#1F2A24` | Body text | Near-black with a green cast, ties back to the primary colour; passes WCAG AA on white |
| `--gl-bg` | `#FAF8F2` | Page background | Warm off-white (tablecloth/paper tone) instead of stark white — fits a "fine dining" feel |

**Status badge colours** (used on My Reservations and the admin Bookings
list — one consistent mapping everywhere, per the status lifecycle in
`CLAUDE.md`):

| Status | Colour | Bootstrap class |
|---|---|---|
| `pending` | amber | `text-bg-warning` |
| `confirmed` | green | `text-bg-success` |
| `completed` | blue-grey | `text-bg-info` |
| `no_show` | dark red | `text-bg-danger` (outline variant, see below) |
| `cancelled` | grey | `text-bg-secondary` |
| `rejected` | red | `text-bg-danger` |

`no_show` and `rejected` are both semantically "bad" but must stay visually
distinguishable at a glance (no_show is a past-tense customer failure,
rejected is an admin decision) — `no_show` uses a danger *outline* style
while `rejected` uses solid danger, so the two never look identical in the
same table column.

**Typography:** no external font is loaded. Headings use a system serif
stack (`Georgia, 'Times New Roman', serif`) for an elegant-dining feel; body
text uses Bootstrap's own default system-UI sans-serif stack, already loaded
with no extra request. See §7 for why a webfont (e.g. Google Fonts) was
considered and rejected.

## 6. Responsive breakpoint conventions

No custom breakpoints are defined — the app uses Bootstrap 5's breakpoints
exactly as shipped (`sm` 576px, `md` 768px, `lg` 992px, `xl` 1200px, `xxl`
1400px), via its existing grid/utility classes. Decision, not oversight:
introducing a second breakpoint system alongside Bootstrap's would violate
NFR-05 (maintainability/consistency) for no real gain, since Bootstrap's
defaults already satisfy NFR-03's 375px-width floor. Customer-facing pages
are designed mobile-first (single column below `md`); admin tables use
Bootstrap's `.table-responsive` horizontal scroll wrapper below `md` rather
than hiding columns, since admins must not lose data at smaller widths.

## 7. Error / success message conventions

- Every state-changing request follows Post/Redirect/Get: the POST handler
  sets one flash message in the session, then redirects; the next GET
  renders it as a single Bootstrap alert (`alert-success`, `alert-danger`,
  or `alert-warning`) at the top of the main content area, dismissible, then
  clears it from the session — so a page refresh never repeats a stale
  message and a form resubmission warning never appears.
- Field-level validation errors (e.g. weak password, invalid email) render
  inline directly under the offending field, in addition to the top-level
  alert — satisfies "surface validation errors ... inline on the same page"
  (NFR-03) rather than only a generic banner.
- Client-side validation (HTML5 `required`/`pattern`/JS checks) is UX-only
  and never trusted; every rule is re-checked server-side per the mandatory
  coding rules, so the same message wording is reused on both sides to avoid
  confusing the user with two different error texts for the same problem.

## 8. Alternatives considered and rejected

- **Google Fonts (e.g. Playfair Display + Inter) for a more distinctive
  look** — rejected: adds an external CDN dependency beyond the one already
  authorised (Bootstrap 5) and CLAUDE.md's mandatory stack lists no other
  CDN; also a small extra network request/FOUC risk against NFR-01/NFR-03.
  A system serif/sans pairing achieves a similar "fine dining vs. plain
  Bootstrap" distinction at zero extra cost.
- **A custom breakpoint system** — rejected in favour of Bootstrap's
  defaults; see §6.
- **A fully custom design system (no Bootstrap components)** — rejected
  outright: CLAUDE.md mandates Bootstrap 5 as the UI layer; the design
  tokens in §5 are meant to sit on top of it, not replace it.
- **Hiding table columns on the admin Bookings list at narrow widths** —
  rejected in favour of horizontal scroll (`.table-responsive`); admins
  need every column (date, slot, table, customer, status, actions)
  available at once for FR-09, and hiding columns silently would risk an
  admin acting on a row without seeing its full context.
- **Separate colour per booking status chosen ad hoc per page** — rejected;
  a single status→colour mapping (§5) is defined once here and must be
  reused on every screen that shows a status, so the customer and admin
  interfaces stay visually consistent (NFR-05).
