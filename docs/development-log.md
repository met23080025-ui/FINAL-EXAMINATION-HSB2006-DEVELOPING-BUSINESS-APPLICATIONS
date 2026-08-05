# Development Log

Chronological record of what was built, decided, and fixed in each phase,
with commit hashes. Kept up to date through the end of each session so the
project's history doesn't have to be reverse-engineered from commit
messages alone at report-writing time. The "AI usage record" section at the
bottom is the interim source for `docs/report-content.md` §12's final
declaration table.

---

## Phase P0 — Project bootstrap
**Commit:** `c81ad32` (2026-08-03) — *chore: bootstrap project structure for Phase P0*

Folder structure (`includes/`, `public/`, `auth/`, `customer/`, `admin/`,
`database/`, `docs/`), `.gitignore`, `config.sample.php`, `CLAUDE.md`
(project brief/roadmap), skeleton `README.md`. No application logic yet.

## Phase P1 — Requirements
**Commit:** `64b66ca` (2026-08-03) — *docs: add Phase P1 requirements, user stories and traceability matrix*

`docs/requirements.md` written in full: business problem, objectives,
scope (in/out), assumptions/limitations, two actors, 15 functional
requirements (FR-01…FR-15), 6 non-functional requirements (NFR-01…NFR-06),
16 user stories with Given/When/Then acceptance criteria, and a
US→FR→marking-criterion traceability matrix.

## Phase P2 — Diagrams and data dictionary
**Commit:** `1293b3b` (2026-08-03) — *docs: add use case, activity and sequence diagrams with data dictionary*

`docs/diagrams/use-case.mmd`, `activity-booking.mmd`,
`sequence-booking.mmd` (Mermaid source), and `docs/data-dictionary.md`
(planned schema for `users`/`tables`/`time_slots`/`reservations`, ahead of
Phase P3 actually implementing it) — establishing the double-booking
constraint design (generated column + unique index) as a documented
decision before the schema was written, not after.

## Phase P3 — Database schema and seed data
**Commits:** `ed7eb03` (2026-08-03) *feat(db): add schema with double-booking constraint and seed data*, `5679a2a` (2026-08-03) *docs: record P3 verification evidence and confirmed environment*

`database/schema.sql` (all four tables, foreign keys, the
`active_slot_key` generated column + `uq_reservations_active_slot` unique
index) and `database/seed.sql` (20 tables, 7 time slots, 7 users, ~57
reservations). Verified live against a real XAMPP install: PDO connection
works, `password_verify()` works, and — critically — the double-booking
unique constraint actually rejects a duplicate insert with MySQL error
1062 (not just a design claim). That live proof, plus `information_schema`
output and exact reproduction steps, is preserved in
`docs/evidence/double-booking-proof.md`. Also recorded the verified local
environment (PHP 8.0.30, XAMPP paths) in `README.md`/`CLAUDE.md`, and
flagged PHP 8.0.30's end-of-life as a known limitation at this point
already — not something discovered late.

## Phase P4 — UI/UX design system
**Commits:** `d45f5a3` (2026-08-03) *feat(P4): UI/UX design process doc and base stylesheet tokens*, `e6624ee` (2026-08-03) *fix(P4): real contrast ratios, spacing/type scales, close doc gaps*

`docs/design-process.md` written first-pass (`d45f5a3`): user-needs
analysis, sitemap, user flows, text wireframes for 5 screens, colour
palette + typography rationale, responsive convention, error/success
message conventions. `public/css/style.css` given the resulting design
tokens as CSS custom properties.

**Issue found and fixed (`e6624ee`), the same day:** a review of the P4
doc surfaced five gaps: (1) the original "passes WCAG AA" claims for the
colour palette were asserted, not calculated — replaced with real WCAG 2.1
relative-luminance contrast ratios for every pair actually used, which
revealed `--gl-accent` genuinely fails AA even at large-text size
(2.28:1) and the `no_show` outline badge fails when placed directly on
`--gl-bg` (4.26:1) — both are now explicitly restricted in the doc, and
the badge's CSS forces a white background so it's safe regardless of
where it's placed; (2) the spacing/typography scales were named but not
consistently applied to real selectors — fixed; (3) three §7 conventions
(empty-state wording, submit-button loading state, destructive-action
confirmation) were missing — added, including the reversibility rationale
for why Approve doesn't need a confirm() gate but Cancel/Reject do; (4) a
contradiction between §3's prose and §4.3's wireframe about *when* the
customer picks a table vs. the search criteria — resolved in favour of the
wireframe and the two diagrams (which were already correct and untouched),
correcting only the prose; (5) the sitemap converted to Mermaid to match
the rest of the project's diagram convention.

## Phase P4b — Application shell
**Commit:** `a72fefc` (2026-08-04) — *feat(P4b): PDO connection, role middleware, flash/CSRF helpers, shared header/footer*

`includes/db.php`, `includes/helpers.php`, `includes/auth.php`,
`includes/header.php`/`footer.php` — the shared infrastructure every later
page builds on: PDO connection with native prepares, `e()`/CSRF/flash
helpers, `require_login()`/`require_admin()` role middleware, and the
Bootstrap 5 shell with role-aware navigation. `index.php` wired up as the
first real page proving the whole include chain end-to-end.

## Phase P5 — Authentication
**Commit:** `3f661eb` (2026-08-04) — *feat(P5): registration, login, logout, profile - password hashing, CSRF, open-redirect guard*

`auth/register.php`, `auth/login.php`, `auth/logout.php`,
`customer/profile.php`. Key decisions: generic login error message +
dummy-hash timing defence against user enumeration;
`session_regenerate_id(true)` on login/logout against session fixation;
`safe_redirect_target()` added to defend the homepage's `?redirect=`
param against open-redirect while still honouring legitimate internal
targets — this function and its four-condition check were written and
documented in the same commit, not retrofitted later.

## Phase P6 — Core reservation workflow
**Commit:** `6c58094` (2026-08-04) — *feat(P6): core reservation workflow - availability, booking, cancellation, admin approval queue*

`includes/reservation.php` (the single source of truth for availability
search, booking-window validation, status-lifecycle transitions via
`can_transition()`, and the double-booking race handled via a transaction
+ unique-constraint-violation catch), wired into `customer/book.php`,
`customer/my-reservations.php`, and a first-pass `admin/bookings.php`
(approve/reject/complete/no-show, no search/filter/sort/pagination yet —
that was deliberately deferred to P7 per the roadmap). Also renamed
`customer/my-bookings.php` → `customer/my-reservations.php` to match the
filename already locked in `docs/design-process.md`'s sitemap, and
appended a UI-level double-booking reproduction (two-browser-window steps)
to `docs/evidence/double-booking-proof.md` §7, including the discovery
that a human-timed demo almost always shows the *application*-layer
conflict message rather than the database-constraint one, and why that's
expected rather than a gap (see `docs/viva-preparation.md` Q2).

## Phase P7 — Admin CRUD, search/filter/sort/pagination, dashboard, reports
**Commit:** `c828f47` (2026-08-05) — *feat(P7): admin CRUD, search/filter/sort/pagination, dashboard, reports*

Built in this order: `includes/listing.php` (shared whitelisted-sort +
bound-pagination + query-string-preservation helpers, used by every admin
listing page); `admin/bookings.php` upgraded with keyword search,
status/area/date-range filters, sortable columns, and 15/page pagination;
full CRUD for `admin/tables.php` and `admin/timeslots.php` (Bootstrap
modals for add/edit, deactivate-instead-of-delete once a row has
reservation history, time-slot overlap validation); `admin/users.php`
(search/role filter, activate/deactivate, role change, server-side
self-protection); a real `admin/dashboard.php` (four live-SQL tiles +
pending-queue preview); `admin/reports.php` (date-range stats as HTML/CSS
bar charts, CSV export); a real `customer/dashboard.php`. Design-system CSS
and JS additions (dashboard tiles, sort indicators, bar charts, filter
bars, empty states, `js-auto-submit` filter forms) layered onto the
existing tokens from `docs/design-process.md`, not new ad hoc styling.

**Issue found and fixed during testing, same session:** PDO with
`EMULATE_PREPARES=false` (set in `includes/db.php` since P4b) does not
allow the same **named** placeholder to appear twice in one query. Two
keyword-search queries — `(u.full_name LIKE :keyword OR u.email LIKE
:keyword)` in `admin/bookings.php`, and the equivalent in
`admin/users.php` — used this pattern and threw
`SQLSTATE[HY093]: Invalid parameter number` the first time they were
exercised with a real search term. Caught by driving the live app with
`curl` against the real seeded database (not just PHP's `-l` lint, which
doesn't execute queries), not by code review alone. Fixed by using two
distinct placeholders (`:keyword1`, `:keyword2`) bound to the same value
in both files.

**Testing note (process, not a code defect):** Apache and MySQL were found
stopped at the start of the testing pass and were started manually to
drive real HTTP requests end-to-end. During delete-safeguard testing
(`admin/tables.php`'s refuse-to-delete-if-referenced check), table `T05`
was deleted to probe the refusal path and was discovered to have zero
seeded reservations (not a bug — the safeguard correctly allowed the
delete since no reservation referenced it) — it was immediately
re-inserted with its original `id=5` and the exact capacity/area from
`CLAUDE.md`'s locked table (`4`, `indoor_main`) to restore the seed dataset
to its documented state. One real pending reservation (id 41) was also
approved to `confirmed` as part of verifying the filter-preserving-through-
actions behaviour on `admin/bookings.php` — a normal use of the feature,
noted here only because it shifts the demo dataset's pending/confirmed
counts slightly from a completely pristine import.

Full manual verification performed this session (see chat transcript /
this log for detail, formal write-up now lives in `docs/test-plan.md`):
CRUD create/edit/delete/deactivate on tables and time slots, duplicate-code
and overlap rejection, CSRF-missing-token rejection, admin self-protection
against self-deactivate/self-demote, filter-preservation through
approve/reject actions, non-admin blocked from every admin page, and CSV
export headers/filename/escaping — all confirmed working against the real
database before the phase was called complete.

## Phase P7.5 — Handover documentation
**Not yet committed as of writing this entry — see `docs/remaining-work.md`.**

With the AI-assisted session's Claude Pro access ending, this pass wrote
the documents needed to finish Phases P8/P9 without further AI help:
`docs/test-plan.md` (43 test cases, replacing the Phase P0 placeholder),
`docs/security-review.md` (every implemented control referenced to its
file), `docs/screenshot-checklist.md`, `docs/viva-preparation.md` (20
Q&A), `docs/remaining-work.md` (this checklist), a finalized `README.md`,
and this log. `docs/report-content.md` §9–12 were also filled in as far as
possible without information only a human can supply (team names, actual
screenshots, actual test results, the Project board link).

---

## AI usage record (interim — finalize in report §12)

| Phase(s) | Tool | Purpose | Parts assisted | How verified |
|---|---|---|---|---|
| P5, P6, P7 | Claude Code (Claude Sonnet 5) | Feature implementation per the CLAUDE.md-documented roadmap | Authentication flow, core reservation workflow, admin CRUD/dashboard/reports, shared listing helpers, CSS/JS polish | Every phase's code was read back and reasoned about in-session before being called done; Phase P7 additionally driven with real `curl` requests against the live seeded database (login, CRUD, CSRF, validation, filter-preservation, CSV export) rather than trusting `php -l` alone — this is how the placeholder-duplication PDO bug was actually caught, not by inspection |
| P7 (handover) | Claude Code (Claude Sonnet 5) | Test plan, security review, screenshot checklist, viva prep, remaining-work checklist, README, this log | All content in `docs/test-plan.md`, `docs/security-review.md`, `docs/screenshot-checklist.md`, `docs/viva-preparation.md`, `docs/remaining-work.md`, `README.md`, this file | Every claim in these documents is grounded in a specific file/line in the actual codebase (cited inline) rather than generic security-checklist boilerplate — cross-check any claim against the cited file if in doubt; the 43 test cases still need to be **actually executed** (they were written, not run, in this pass — see `docs/remaining-work.md` item 1) |
| P0–P4b | *(unconfirmed)* | — | — | Commits `c81ad32` through `a72fefc` do not carry a `Co-Authored-By: Claude Sonnet 5` git trailer, unlike every commit from P5 onward. This may mean those phases were done without AI assistance, or simply that the trailer wasn't added for those sessions — **confirm which, by hand, before finalizing the report's AI declaration table**, since this log can only report what's observable from git history, not what actually happened in an untracked session. |

**Standing note for the final report:** regardless of which phases used AI
assistance, the team is responsible for the correctness, security,
licensing, and originality of everything submitted — AI usage does not
transfer that responsibility. Every phase above involved reading the
generated code/docs back before accepting it, and Phase P7 specifically
caught a real bug through independent testing rather than trusting the
output — cite that as evidence of verification, not just generation, in
the final declaration.
