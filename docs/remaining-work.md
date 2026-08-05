# Remaining Work — Plain Checklist for P8/P9

Written 2026-08-05, updated the same day after a further UI modernisation
pass (Phase P7.6 in `docs/development-log.md`) — this session turned out
not to be the last one after all, so treat "last AI-assisted session"
mentions elsewhere in this doc set loosely; update this checklist again if
another session happens after this one. Everything below is what's left
to reach submission. Ordered by priority/dependency, not strictly by phase
number — do them roughly top to bottom. Time estimates assume one person
working alone; adjust down if teammates split the work.

Legend: **[SOLO]** = requires a decision or action only you (or your human
team) can make — an AI session cannot do this for you, even a future one.
**[AI-OK]** = a future AI session can do the mechanical part if you provide
the missing input (e.g. "here are our real names, update the docs").

---

## Phase P8 — Testing & security (do this block first)

1. **[SOLO — needs a human at the browser, do this before anything else
   below] Finish the eyes-on-screen checks the last session couldn't run.**
   The Chrome browser extension wouldn't connect in the Phase P7.6 UI
   polish session, so three things were built and reasoned about but never
   actually watched happen:
   - **Browser console errors** — open DevTools console on `index.php`,
     `auth/login.php`, `customer/book.php` (search + confirm a booking),
     `admin/dashboard.php`, and one admin CRUD modal (`admin/tables.php`
     "+ Add Table"). Confirm zero red errors on each. The toast/count-up/
     bar-animation/scroll-reveal JS in `public/js/main.js` is the newest,
     least-exercised code in the app.
   - **`prefers-reduced-motion: reduce` actually stills the page** —
     Chrome DevTools → ⋮ → More tools → Rendering → "Emulate CSS media
     feature prefers-reduced-motion: reduce" (or toggle it at the OS level:
     Windows Settings → Accessibility → Visual effects → Animation
     effects off). Reload `index.php` (hero/scroll-reveal),
     `admin/dashboard.php` (count-up tiles), `admin/reports.php` (bar
     growth), and the post-booking highlight on
     `customer/my-reservations.php`. Check against the behaviour table in
     `docs/design-process.md` §9.5 — every row should show its "instant,
     correct final state" outcome, not a broken/half-animated one.
   - **Keyboard-only tab order + visible focus** — unplug the mouse
     mentally and Tab through `index.php`, the login form, `book.php`'s
     table cards, and an admin CRUD modal. Confirm the dual-layer focus
     ring from `docs/design-process.md` §9.3 (green outline + gold glow)
     is visible on every stop, including the toast close button and the
     table-card radio inputs.
   - **(Added Phase P7.8) Webfonts actually render** — open DevTools
     Network tab on any page, confirm the 6 `.ttf` requests under
     `public/fonts/` succeed (not 404, not falling back silently); visually
     confirm headings render as Playfair Display (not Georgia — compare
     the distinctive high-contrast serif strokes) and body text as Be
     Vietnam Pro; specifically check "Duy hoàng" in the navbar (or any
     Vietnamese name in the seeded data) renders its diacritics correctly,
     not as a missing-glyph box (`.tofu`). Also confirm no heading wraps
     awkwardly in a tight space (modal titles, card headers) — this was
     mitigated in `public/css/style.css` (§5.3/§9.9 of the design doc) but
     never actually seen rendered.

   If anything fails, it's a real bug in the polish pass, not a
   documentation gap — fix it in `public/css/style.css`/`public/js/main.js`
   and note the fix in `docs/development-log.md`. **Estimate: 30–45 min.**

2. **[AI-OK, but must be run for real] Execute all 43 test cases in
   `docs/test-plan.md`.** Fresh import of `schema.sql` + `seed.sql` first
   (some cases, e.g. TC-33/34, delete data). Fill in Actual Result and
   Pass/Fail by hand as you go, or paste terminal/browser output to a
   future AI session and ask it to fill the table in for you.
   **Estimate: 2–3 hours.**

3. **[SOLO decision, AI-OK to implement] Decide on the TC-28 gap.**
   `can_transition()` currently allows `confirmed → completed/no_show`
   even if the booking's date/time hasn't passed yet — the UI hides the
   buttons until it has, but a forged POST could act early. Either (a)
   add a time check into `change_reservation_status()` before allowing
   that specific transition, or (b) accept it and write it up as a
   documented limitation in the test-plan defect table and
   `docs/report-content.md` §10. Either is defensible; just decide and
   record which. **Estimate: 30 min (decide) + 30 min (implement if
   fixing).**

4. **[AI-OK] Fix any other defects found in steps 1/2, re-test, update the
   defect table in `docs/test-plan.md`.** **Estimate: 30 min – 2 hours,
   depends what's found.**

5. **[SOLO — needs a human at the browser] Capture every screenshot in
   `docs/screenshot-checklist.md`** (48 items across customer, admin, and
   security-evidence sections, including 3 narrow-width shots). Save into
   `docs/evidence/screenshots/` using the naming pattern in that file. Do
   this after step 1, so the newest UI (toasts, gradients, count-up tiles)
   is what actually gets captured. **Estimate: 1.5–2 hours.**

6. **[AI-OK] Re-read `docs/security-review.md` against the final code
   state** after any fixes from step 4, and correct anything that drifted
   (e.g. if TC-28 was fixed, update the "known gap" note). **Estimate: 15 min.**

## Phase P9 — Packaging & submission

7. **[SOLO — needs real information] Fill in team names.** Currently only
   student ID 23080025 / GitHub `met23080025-ui` is on record. Needs: full
   names + student IDs for `docs/requirements.md` §11, `CLAUDE.md`'s team
   table, and the report cover page. If this is genuinely a solo
   submission, say so explicitly rather than leaving the table blank —
   graders read blank team tables as unfinished, not as "solo by design."
   **Estimate: 10 min.**

8. **[SOLO — GitHub UI/CLI, human judgement needed] Create the GitHub
   Project board and one issue per user story (US-01…US-16), assigned to
   whoever did that work.** This is worth real marks under Criterion 1
   (Project Management, 10 pts) and CLAUDE.md is explicit that "a single
   end-of-project bulk upload forfeits project-management marks" — so
   backdating this convincingly is both against the spirit of the rubric
   and hard to fake credibly (issue timestamps are visible). If work
   genuinely happened solo without a formal board so far, create the board
   now, retroactively add the 16 issues marked Done with an honest note
   rather than pretending they tracked work in real time. Link it from
   `docs/requirements.md` §12 once created. **Estimate: 45 min – 1.5 hours.**

9. **[AI-OK once inputs above exist] Finish `docs/report-content.md`
   remaining TODOs:** paste in completed test-plan results (§10), insert
   screenshots (§8), add the Project board link (§5), fill the References
   section and finalize the AI-usage declaration table in §12 using
   `docs/development-log.md`'s interim record as the source (now includes
   the Phase P7.6 UI polish pass and its own verification/bug entry).
   **Estimate: 1 hour.**

10. **[SOLO] Assemble the actual report document** (PDF) from
    `docs/report-content.md`'s content, following the 12-section order and
    whatever the lecturer's template requires, with screenshots embedded
    inline near the feature they demonstrate. **Estimate: 2–3 hours** — this
    is genuinely the single biggest remaining time cost, since it's manual
    formatting work a markdown file can't substitute for.

11. **[SOLO] Export the final database dump** for
    `HSB2006_MET4_23080025_<FullName>_Database.sql` — via phpMyAdmin
    "Export" on the fully-tested `golden_lotus` database (structure + data,
    after step 2's testing has run, so the export reflects real exercised
    state — or re-import a clean `schema.sql`+`seed.sql` first if you'd
    rather submit pristine seed data instead of post-testing state; either
    is defensible, just be consistent with what the report's screenshots
    show). Current live database is clean as of 2026-08-05 (8 users, 60
    reservations — all AI-testing residue from both the P7 and P7.6
    sessions has been identified and removed; see
    `docs/development-log.md`'s Phase P7.6 entry for exactly what was
    deleted and how it was confirmed safe to delete). **Estimate: 15 min.**

12. **[SOLO] Rename and zip for submission** per `CLAUDE.md`'s exact
    filenames once the full name is known:
    `HSB2006_MET4_23080025_<FullName>_Report.pdf`,
    `HSB2006_MET4_23080025_<FullName>_Source.zip` (whole repo, excluding
    `.git/`, `config.php`, any `node_modules`/cache — check
    `.gitignore` for the full exclude list), and the Database.sql from
    step 11. **Estimate: 20 min.**

13. **[SOLO, do this last] Full regression pass + demo rehearsal.** Fresh
    `schema.sql`+`seed.sql` import, click through the entire customer flow
    and entire admin flow once more end to end, then do a timed dry run of
    whatever you'll actually demo/say at the viva using
    `docs/viva-preparation.md`'s 20 Q&A as prep. **Estimate: 1 hour.**

---

## Total remaining estimate

Roughly **10.5–15 hours** of focused work, split across the new
eyes-on-screen check (~30-45 min), testing/screenshots (P8, ~5–7 hours),
and packaging/report assembly (P9, ~5–7 hours). The report PDF assembly
(step 10) and the Project board (step 8) are the two items most likely to
take longer than estimated — budget slack there first if short on time.

## If you're down to the wire

Priority order if you must cut scope: **1 (eyes-on-screen check) → 2 (run
tests) → 5 (screenshots) → 8 (Project board) → 10 (report PDF) → 11–12
(export/zip)** are the ones that directly cost marks (or risk a visibly
broken demo) if skipped entirely. Steps 3/4/6/9 improve quality but a
partially-run test plan with an honest, smaller defect list still beats an
untested one. Steps 7/13 are quick — do them regardless of time pressure.
