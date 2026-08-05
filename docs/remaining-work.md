# Remaining Work — Plain Checklist for P8/P9

Written 2026-08-05, at the end of the last AI-assisted session (Claude Pro
access ends after this). Everything below is what's left to reach
submission. Ordered by priority/dependency, not strictly by phase number —
do them roughly top to bottom. Time estimates assume one person working
alone; adjust down if teammates split the work.

Legend: **[SOLO]** = requires a decision or action only you (or your human
team) can make — an AI session cannot do this for you, even a future one.
**[AI-OK]** = a future AI session can do the mechanical part if you provide
the missing input (e.g. "here are our real names, update the docs").

---

## Phase P8 — Testing & security (do this block first)

1. **[AI-OK, but must be run for real] Execute all 43 test cases in
   `docs/test-plan.md`.** Fresh import of `schema.sql` + `seed.sql` first
   (some cases, e.g. TC-33/34, delete data). Fill in Actual Result and
   Pass/Fail by hand as you go, or paste terminal/browser output to a
   future AI session and ask it to fill the table in for you.
   **Estimate: 2–3 hours.**

2. **[SOLO decision, AI-OK to implement] Decide on the TC-28 gap.**
   `can_transition()` currently allows `confirmed → completed/no_show`
   even if the booking's date/time hasn't passed yet — the UI hides the
   buttons until it has, but a forged POST could act early. Either (a)
   add a time check into `change_reservation_status()` before allowing
   that specific transition, or (b) accept it and write it up as a
   documented limitation in the test-plan defect table and
   `docs/report-content.md` §10. Either is defensible; just decide and
   record which. **Estimate: 30 min (decide) + 30 min (implement if
   fixing).**

3. **[AI-OK] Fix any other defects found in step 1, re-test, update the
   defect table in `docs/test-plan.md`.** **Estimate: 30 min – 2 hours,
   depends what's found.**

4. **[SOLO — needs a human at the browser] Capture every screenshot in
   `docs/screenshot-checklist.md`** (48 items across customer, admin, and
   security-evidence sections, including 3 narrow-width shots). Save into
   `docs/evidence/screenshots/` using the naming pattern in that file.
   **Estimate: 1.5–2 hours.**

5. **[AI-OK] Re-read `docs/security-review.md` against the final code
   state** after any fixes from step 3, and correct anything that drifted
   (e.g. if TC-28 was fixed, update the "known gap" note). **Estimate: 15 min.**

## Phase P9 — Packaging & submission

6. **[SOLO — needs real information] Fill in team names.** Currently only
   student ID 23080025 / GitHub `met23080025-ui` is on record. Needs: full
   names + student IDs for `docs/requirements.md` §11, `CLAUDE.md`'s team
   table, and the report cover page. If this is genuinely a solo
   submission, say so explicitly rather than leaving the table blank —
   graders read blank team tables as unfinished, not as "solo by design."
   **Estimate: 10 min.**

7. **[SOLO — GitHub UI/CLI, human judgement needed] Create the GitHub
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

8. **[AI-OK once inputs above exist] Finish `docs/report-content.md`
   remaining TODOs:** paste in completed test-plan results (§10), insert
   screenshots (§8), add the Project board link (§5), fill the References
   section and finalize the AI-usage declaration table in §12 using
   `docs/development-log.md`'s interim record as the source. **Estimate:
   1 hour.**

9. **[SOLO] Assemble the actual report document** (PDF) from
   `docs/report-content.md`'s content, following the 12-section order and
   whatever the lecturer's template requires, with screenshots embedded
   inline near the feature they demonstrate. **Estimate: 2–3 hours** — this
   is genuinely the single biggest remaining time cost, since it's manual
   formatting work a markdown file can't substitute for.

10. **[SOLO] Export the final database dump** for
    `HSB2006_MET4_23080025_<FullName>_Database.sql` — via phpMyAdmin
    "Export" on the fully-tested `golden_lotus` database (structure + data,
    after step 1's testing has run, so the export reflects real exercised
    state — or re-import a clean `schema.sql`+`seed.sql` first if you'd
    rather submit pristine seed data instead of post-testing state; either
    is defensible, just be consistent with what the report's screenshots
    show). **Estimate: 15 min.**

11. **[SOLO] Rename and zip for submission** per `CLAUDE.md`'s exact
    filenames once the full name is known:
    `HSB2006_MET4_23080025_<FullName>_Report.pdf`,
    `HSB2006_MET4_23080025_<FullName>_Source.zip` (whole repo, excluding
    `.git/`, `config.php`, any `node_modules`/cache — check
    `.gitignore` for the full exclude list), and the Database.sql from
    step 10. **Estimate: 20 min.**

12. **[SOLO, do this last] Full regression pass + demo rehearsal.** Fresh
    `schema.sql`+`seed.sql` import, click through the entire customer flow
    and entire admin flow once more end to end, then do a timed dry run of
    whatever you'll actually demo/say at the viva using
    `docs/viva-preparation.md`'s 20 Q&A as prep. **Estimate: 1 hour.**

---

## Total remaining estimate

Roughly **10–14 hours** of focused work, split across testing/screenshots
(P8, ~5–7 hours) and packaging/report assembly (P9, ~5–7 hours). The report
PDF assembly (step 9) and the Project board (step 7) are the two items
most likely to take longer than estimated — budget slack there first if
short on time.

## If you're down to the wire

Priority order if you must cut scope: **1 (run tests) → 4 (screenshots) →
7 (Project board) → 9 (report PDF) → 10–11 (export/zip)** are the ones that
directly cost marks if skipped entirely. Steps 2/3/5/8 improve quality but
a partially-run test plan with an honest, smaller defect list still beats
an untested one. Steps 6/12 are quick — do them regardless of time
pressure.
