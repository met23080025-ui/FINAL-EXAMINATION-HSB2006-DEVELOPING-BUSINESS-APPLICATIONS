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
**Commit:** `6ea5d90` (2026-08-05) — *docs(P8 handover): test plan, security review, viva prep, packaging checklists*

With the AI-assisted session's Claude Pro access believed to be ending,
this pass wrote the documents needed to finish Phases P8/P9 without
further AI help: `docs/test-plan.md` (43 test cases, replacing the Phase
P0 placeholder), `docs/security-review.md` (every implemented control
referenced to its file), `docs/screenshot-checklist.md`,
`docs/viva-preparation.md` (20 Q&A), `docs/remaining-work.md` (this
checklist), a finalized `README.md`, and this log. `docs/report-content.md`
§9–12 were also filled in as far as possible without information only a
human can supply (team names, actual screenshots, actual test results,
the Project board link). As it turned out, the session continued past
this point (Phase P7.6 below) — the handover doc set still stands as
written and remains the right reference for whoever finishes P8/P9,
AI-assisted or not.

## Phase P7.6 — UI modernisation pass ("Polish pass")
**Commit:** `03c503b` (2026-08-05) — *style: modern UI polish — gradients, motion, SVG identity, refined components*

Purely presentational upgrade across every page — no business logic,
routes, validation rules, or the locked status-badge colour mapping
changed. Built in this order:

1. **Token foundation** (`public/css/style.css` `:root`): shadow scale
   (`--gl-shadow-1..3`), radius scale, transition tokens, three gradient
   recipes built only from `--gl-primary`/`--gl-accent`, a blanket
   `@media (prefers-reduced-motion: reduce)` rule, and a dual-layer
   `:focus-visible` ring (solid `--gl-primary` outline + `--gl-accent`
   glow — a plain solid accent outline was rejected because it measures
   2.28:1 against `--gl-bg`, failing WCAG 1.4.11's 3:1 non-text-contrast
   minimum, the same restriction §5.2 already placed on accent-as-text).
2. **`includes/icons.php` (new file)** — a hand-drawn inline SVG library:
   a five-petal lotus motif (one petal `<path>` repeated via
   `transform="rotate"`), four area glyphs, a selection-check glyph, and
   five admin/report tile icons — all stroke-only, `currentColor`, so
   they inherit colour from context with zero extra colour declarations.
   Chosen specifically to give the project a signature, licence-free
   identity without adding any icon library or stock imagery (both ruled
   out by the brief).
3. **Toast flash messages** — `includes/header.php` now renders flashes
   as a top-right toast region instead of static top-of-page alerts;
   `public/js/main.js` auto-dismisses success/info/warning after 4s with
   a shrinking progress line, while error (`danger`) toasts persist until
   manually closed. The underlying session-flash mechanism
   (`includes/helpers.php`) is completely unchanged — presentation only.
4. **Every page**: gradient hero + area cards + scroll-reveal on
   `index.php`; split-layout gradient panel on `auth/login.php` and
   `auth/register.php`; staggered fade-in table-selection cards with a
   check-glyph selected state on `customer/book.php`; pill badges, row
   hover, and a one-time 2s highlight on the row just created/cancelled
   (via a `?highlight=<id>` query param read by JS after the PRG
   redirect) on `customer/my-reservations.php`; an accent-bordered
   gradient "next reservation" hero card on `customer/dashboard.php`;
   count-up tiles with SVG icons on `admin/dashboard.php`; pending-row
   accent borders, a sticky table header, and SVG sort-direction chevrons
   (replacing the previous Unicode ▲/▼) on `admin/bookings.php`;
   scale+fade modal entrances (pure CSS override of Bootstrap's own
   show/hide lifecycle, no JS change) on the `admin/tables.php` and
   `admin/timeslots.php` CRUD modals; bars that grow from 0 on load on
   `admin/reports.php`.
5. **`docs/design-process.md` §9 ("Polish pass")** — every new token, the
   reduced-motion decision and its accessibility rationale, the
   dual-layer focus-ring rationale, the lotus-motif decision, a
   component-by-component reduced-motion behaviour table, and a fresh
   WCAG contrast re-verification for every new text-bearing surface
   (hero gradient's worst-case stop: white on `#0e7048` = **6.12:1**;
   new `.text-muted` colour `#5b6960`: **5.44:1** on `--gl-bg`, **5.78:1**
   on white — both computed via the same relative-luminance method as
   §5.2, not asserted).

**Bug found and fixed, caught by live testing rather than lint:**
`index.php` called the new icon functions (`svg_icon_chair()` etc.) while
building its `$areas` array *before* `includes/header.php` was required —
and `icons.php` is only loaded from inside `header.php`. `php -l` passed
cleanly on `index.php` regardless, because `-l` only checks PHP syntax; it
does not execute the file, so a call to an undefined function is
completely invisible to it. The bug only surfaced when the homepage was
actually requested over HTTP (`curl http://localhost/golden-lotus/index.php`)
during the verification pass, which returned a real
`Fatal error: Uncaught Error: Call to undefined function svg_icon_chair()`.
Fixed with a direct `require_once __DIR__ . '/includes/icons.php';` near
the top of `index.php`, independent of `header.php`'s later include. Same
lesson as the Phase P7 PDO placeholder bug: **`php -l` only proves syntax
validity, never runtime correctness** — every phase in this project that
found a real bug found it by executing the code against the live server,
not by lint or code review alone. Re-verified after the fix: `curl` to
`/index.php` returned zero PHP errors.

**Verification performed this session:** `php -l` on all 18 touched
files (all clean, including after the fix above); `node --check` on
`public/js/main.js` (valid syntax); a script-based (not ad hoc inline)
CSS brace-balance check (130 open / 130 close); and a full live
`curl`-driven smoke run repeating the P5/P6/P7 flows end to end —
register → login → search availability → book → admin approve → customer
cancel → admin table CRUD create/delete → CSV export — all against the
real seeded database, all zero PHP errors, every status transition
confirmed correct directly in MySQL (not just trusted from the HTTP
response).

**Verification NOT performed — genuine gap, not silently skipped:** the
Chrome browser extension would not connect in this session
(`tabs_context_mcp` returned "Browser extension is not connected" on two
separate attempts), so none of the following were actually checked and
still need a human pass before the demo: **(1)** browser DevTools console
for JavaScript errors on any page, **(2)** visually confirming
`prefers-reduced-motion: reduce` genuinely stills every animation listed
in `docs/design-process.md` §9.5's table (OS-level: Windows Settings →
Accessibility → Visual effects → "Animation effects" off, or emulate via
Chrome DevTools' Rendering tab → "Emulate CSS media feature
prefers-reduced-motion"), and **(3)** tabbing through every page
keyboard-only to confirm the dual-layer focus ring (§9.3) is visible on
every interactive element in the actual tab order, including inside the
CRUD modals and the toast close buttons. See
`docs/remaining-work.md`'s new item for the exact check-list.

**Post-session cleanup:** the live smoke run in this phase and the
previous P7 session both registered throwaway test accounts and created
test reservations/tables against the real project database (not a
disposable test DB) to get genuine HTTP-level proof rather than trusting
code review alone. All of that residue was identified and removed in a
follow-up cleanup pass the same day: reservation id 68 (a cancelled
`UI smoke test` booking) and user ids 10–11
(`uitest<timestamp>@goldenlotus.test`, both literally named "UI Test
User" so they were unambiguous to find) were deleted after confirming via
SQL that neither was referenced anywhere else (checked `actioned_by` on
every reservation, not just `user_id`, before deleting). Real accounts the
project owner created manually between sessions (user id 2, whose email
had been changed to a real personal address, and user id 9, "Duy hoàng")
were left untouched — they were never part of the AI-driven testing and
were identified as such before any deletion, not assumed safe. Database
totals after cleanup: 8 users, 60 reservations.

## Phase P7.7 — Bugfix: invisible table cards on customer/book.php
**Commit:** `5bf9562` (2026-08-05) — *fix: invisible table cards on book.php - invalid animation shorthand*

**User report:** search on `customer/book.php` returned results (layout
space reserved, Notes/Confirm section rendered) but every table card was
invisible — reported as "almost certainly" the Phase P7.6 staggered
fade-in, with a hypothesis that `main.js` was erroring out before adding a
visibility class.

**What was actually true vs. the hypothesis:** the visibility symptom was
correct, but the mechanism wasn't — `.gl-table-card`'s reveal animation was
never coupled to a JS-added class in the first place; it was a pure CSS
`animation: ... forwards` declaration, so a JS error couldn't have been
the direct cause. Traced every handler in `main.js` against `book.php`'s
actual DOM (toast handling, table-card selection sync, count-up, bar
animation, scroll-reveal) and found no code path that throws on that page
— elements with zero matches simply no-op, and the ones that do match
(toast, table-card sync) have nothing in them that can throw. Said so
plainly rather than inventing a JS fix for a bug that wasn't there.

**Actual root cause:** a CSS shorthand authoring mistake. `--gl-t-base`/
`--gl-t-fast` (`public/css/style.css` `:root`) are defined as composite
values — `"250ms ease"`, duration *and* easing together, designed to drop
into a `transition:` shorthand cleanly. Five `animation:` declarations
(`.gl-table-card`, `.gl-bar-row`, `.invalid-feedback`, `.gl-toast`,
`.gl-toast.is-leaving`) additionally appended a literal `ease` (or `ease
reverse`) keyword after the `var()`, producing e.g. `animation:
gl-fade-in-up 250ms ease ease forwards` — two easing-function values in
one animation shorthand, which is invalid CSS. Per standard CSS error
handling, an invalid shorthand value is dropped in full, not partially
applied, so the `animation` property silently never took effect on any of
the five, leaving each element's separately-declared `opacity: 0` (where
present) permanently in effect. A secondary, previously-undiscovered
consequence of the same bug: `.gl-toast`'s dismiss handler in `main.js`
waits for the `animationend` event to remove a toast from the DOM after
adding `.is-leaving` — since that animation was also silently invalid,
toasts would never actually have been removed by the close button or the
4-second auto-dismiss timer once shown, a real functional regression
nobody had reported yet.

**Fix, two layers, both requested by the user:**
1. Removed the redundant easing keyword from all five declarations —
   this alone restores correct rendering, independent of JS.
2. Implemented the requested fail-safe architecture project-wide, not
   just for table cards: `document.documentElement.classList.add('js-ready')`
   (renamed from the earlier `'js-enabled'`, same mechanism, now used more
   broadly) is the literal first statement of `main.js`. Every
   "hidden-then-revealed" CSS rule (`.gl-reveal`, `.gl-table-card`,
   `.gl-bar-row`, `.invalid-feedback`, `.gl-hero-enter` and its two delay
   modifiers) was restructured so the base selector is always visible and
   only an `html.js-ready`-prefixed selector adds the hidden/animated
   state. One specificity bug caught and fixed while doing this: the
   `.gl-hero-enter-delay-1/2` modifier rules needed the same
   `html.js-ready` prefix added, or the (now higher-specificity) base
   `html.js-ready .gl-hero-enter` rule's implicit `animation-delay: 0s`
   would have won the cascade and silently cancelled the stagger.
   `docs/design-process.md` §9.8 has the full writeup and the reasoning
   for why both layers were needed (fixing the CSS alone doesn't add
   future defence; the `js-ready` gate alone wouldn't have fixed anything,
   since this specific bug was never actually about JavaScript).

**Verification:** `node --check` on `main.js` (valid), a CSS brace-balance
script check (still balanced), and a direct `diff` between the local file
and the file Apache actually serves (identical — ruled out a stale-cache
false alarm that came up mid-debugging, see below). Live smoke test
(script-based, not inline — see the feedback memory on this from the prior
session) covering: register → login → search with results (confirmed 20
`.gl-table-card` elements with correctly staggered `--gl-row-i` values,
zero PHP errors) → search with zero results (confirmed the exact §7 empty-
state wording plus the lotus motif icon still renders, zero PHP errors) →
complete a booking end to end (zero PHP errors, correct redirect with
`?highlight=`). **Two test-harness mistakes caught and corrected during
this pass, not app bugs:** first, an overly broad grep in the verification
script matched the *explanatory comment* documenting the old bug
(literally quotes the broken pattern as an example) and reported a false
"still broken" — resolved by diffing the actual served file against the
local one directly instead of trusting a naive grep. Second, an early
version of the smoke-test script POSTed login credentials to
`customer/dashboard.php` instead of `auth/login.php`, so every downstream
check failed for an unrelated reason (never actually authenticated) — caught
by writing an isolated step-by-step debug script that printed headers and
cookies at every stage, which immediately made the wrong URL obvious.

**Not verified, same gap as Phase P7.6:** the Chrome extension still would
not connect this session, so the fix is confirmed correct by CSS-grammar
reasoning plus server-rendered-markup checks, but was never watched
actually render in a browser. This remains open — see
`docs/remaining-work.md` item 1.

**Cleanup:** this session's smoke tests registered three more throwaway
accounts (`debugauth<timestamp>`, `bugfixtest<timestamp>` ×2) and one test
reservation (id 70, notes "CSS bugfix smoke test"). All confirmed
unreferenced elsewhere (checked `actioned_by` as well as `user_id`) and
deleted the same way as the Phase P7.6 cleanup. Post-cleanup state: 8
users, 61 reservations — the reservation count is one higher than Phase
P7.6's post-cleanup figure (60) not because of leftover test data, but
because the project owner made a further real booking (id 69, dated
2026-08-06) between sessions; confirmed by inspecting its `user_id` (9,
"Duy hoàng" — an account already established as the owner's own, not
AI-testing residue) before concluding it was legitimate rather than
assuming so.

---

## AI usage record (interim — finalize in report §12)

| Phase(s) | Tool | Purpose | Parts assisted | How verified |
|---|---|---|---|---|
| P5, P6, P7 | Claude Code (Claude Sonnet 5) | Feature implementation per the CLAUDE.md-documented roadmap | Authentication flow, core reservation workflow, admin CRUD/dashboard/reports, shared listing helpers, CSS/JS polish | Every phase's code was read back and reasoned about in-session before being called done; Phase P7 additionally driven with real `curl` requests against the live seeded database (login, CRUD, CSRF, validation, filter-preservation, CSV export) rather than trusting `php -l` alone — this is how the placeholder-duplication PDO bug was actually caught, not by inspection |
| P7 (handover) | Claude Code (Claude Sonnet 5) | Test plan, security review, screenshot checklist, viva prep, remaining-work checklist, README, this log | All content in `docs/test-plan.md`, `docs/security-review.md`, `docs/screenshot-checklist.md`, `docs/viva-preparation.md`, `docs/remaining-work.md`, `README.md`, this file | Every claim in these documents is grounded in a specific file/line in the actual codebase (cited inline) rather than generic security-checklist boilerplate — cross-check any claim against the cited file if in doubt; the 43 test cases still need to be **actually executed** (they were written, not run, in this pass — see `docs/remaining-work.md` item 2) |
| P7.6 (UI polish) | Claude Code (Claude Sonnet 5) | Full presentational UI modernisation pass (tokens, SVG icon system, toasts, gradients, motion, per-page polish) | `includes/icons.php` (new), `public/css/style.css`, `public/js/main.js`, `docs/design-process.md` §9, and every page file (`index.php`, both `auth/*`, all `customer/*`, all `admin/*`, `includes/header.php`/`footer.php`/`listing.php`) | `php -l` on all 18 touched files, `node --check` on the JS, a script-based CSS brace-balance check, and a full live `curl` smoke run repeating the P5/P6/P7 flows end to end with DB-level confirmation of every state change. **Not verified**: browser console errors, visual reduced-motion behaviour, and keyboard-only focus order — the Chrome extension would not connect this session; see this phase's log entry above and `docs/remaining-work.md` for the exact manual check-list still owed |
| P7.7 (bugfix) | Claude Code (Claude Sonnet 5) | Diagnose and fix the invisible-table-cards regression reported by the user | `public/css/style.css` (5 corrected `animation` shorthands + fail-safe `html.js-ready` restructuring), `public/js/main.js` (`js-enabled`→`js-ready` rename/broadened use), `docs/design-process.md` §9.8, this log | Root cause identified by CSS-grammar reasoning (not guessed) before touching code; user's JS-error hypothesis was checked against every handler in `main.js` and found not to hold, and that was reported plainly rather than fabricating a matching fix. Verified via `node --check`, a CSS brace-balance script, a direct diff of local vs. server-served CSS, and a full live smoke test — during which two mistakes in the verification script itself (not the app) were caught and corrected, see this phase's log entry for detail. Browser-based visual/console confirmation still not possible this session (extension would not connect) |
| P0–P4b | *(unconfirmed)* | — | — | Commits `c81ad32` through `a72fefc` do not carry a `Co-Authored-By: Claude Sonnet 5` git trailer, unlike every commit from P5 onward. This may mean those phases were done without AI assistance, or simply that the trailer wasn't added for those sessions — **confirm which, by hand, before finalizing the report's AI declaration table**, since this log can only report what's observable from git history, not what actually happened in an untracked session. |

**Standing note for the final report:** regardless of which phases used AI
assistance, the team is responsible for the correctness, security,
licensing, and originality of everything submitted — AI usage does not
transfer that responsibility. Every phase above involved reading the
generated code/docs back before accepting it, and Phase P7 specifically
caught a real bug through independent testing rather than trusting the
output — cite that as evidence of verification, not just generation, in
the final declaration.
