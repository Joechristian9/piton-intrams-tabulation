# Multi-event tabulation — design

Date: 2026-10-05 · Status: approved in conversation, awaiting written-spec review

## 1. Goal

Today the app runs one pageant whose candidates, photos, categories, maximum scores and
judges are hard-coded (`App\Support\Criteria`, column-per-category score tables, one page
per category). The goal is to let the admin:

- create any number of events (pageants, contests) and start or close each one at any time,
  with several events live at once;
- manage each event's groups, categories (with max points), candidates (number, name,
  course, photo) and judges;
- run each event through 1 or 2 rounds with the same scoring rules as today.

The current pageant must become **Event #1** with identical names, numbers, photos,
categories, max points, scores, averages, totals and ranks.

## 2. Decisions

| Topic | Decision |
|---|---|
| Scoring unit | One score per judge × candidate × category, capped at the category's max points (as today). No sub-criteria. |
| Rounds | Each event has 1 or 2 rounds. 2 rounds: Round 1 → admin sets Top N per group → Finals for finalists only. |
| Finalists | N ("finalists per group") is set per event. |
| Finals scoring | Per event: **finals start from zero** (today's behaviour) or **carry over** with weights: final total = Round 1 total × Round 1 weight % + Finals total × Finals weight % (weights add up to 100). |
| Groups | Per event, named by the admin (Female + Male, a single group, anything). Each group is ranked separately and gets its own Top N. |
| Live events | Any number of events can be live at the same time. Each is started and closed independently. |
| Judges | Every judge account belongs to exactly one event. The admin enters "how many judges" and the accounts are created automatically. |
| Judge login | Generated username (e.g. `pageant26-judge1`) + random password. The login page accepts username or email. |
| Averaging | Category average = sum of the event's judges' scores ÷ number of judges in that event (missing score = 0). |
| Approach | One generic data model and code path for all events (approach A). Old tables are migrated, verified, then retired. |

## 3. Data model

New and changed tables (SQLite, Laravel migrations):

**events**
- `id`, `name`, `code` (short unique slug used as the judge-username prefix, e.g. `pageant26`; suggested from the name, editable until judges exist)
- `status`: `setup` | `live` | `closed`
- `rounds`: 1 or 2
- `finalists_per_group`: integer ≥ 1 (null for 1-round events)
- `finals_from_zero`: boolean (default true)
- `round1_weight`, `finals_weight`: integers 0–100 adding up to 100; used only when `finals_from_zero` is false
- `finalists_set_at`: nullable timestamp (Round 1 lock)
- `started_at`, `closed_at`, timestamps

**event_groups** — `id`, `event_id`, `name`, `position`. Unique (`event_id`, `name`).

**categories** — `id`, `event_id`, `round` (1|2), `name`, `max_score` decimal(5,2) > 0, `position`.

**candidates** (existing table, extended) — adds `event_id`, `group_id`; keeps `candidate_number`,
`first_name`, `last_name`, `course`, `profile_img`. `gender` is replaced by the group.
Unique (`group_id`, `candidate_number`) — each group numbers from 1, as today (Female #1–12, Male #1–10).

**users** (existing) — adds nullable `event_id` (set for judges, null for admins) and unique
nullable `username`, plus `password_plain_encrypted` (nullable, Laravel `Crypt`) so the admin can
reveal/reprint generated credentials. Cleared when a judge changes their own password.

**finalists** — `id`, `event_id`, `candidate_id`. Unique (`event_id`, `candidate_id`). Replaces
`top_five_candidates`.

**scores** — `id`, `category_id`, `candidate_id`, `judge_id`, `score` decimal(5,2), timestamps.
Unique (`category_id`, `candidate_id`, `judge_id`); indexes on `judge_id` and `candidate_id`.
Replaces `top_five_selection_scores` and `top_five_scores` (and their stored totals — totals are
always computed).

Foreign keys cascade from `events` to its groups, categories, candidates, finalists and judges;
deleting is restricted by the rules in §4, not by the database.

## 4. Rules

**Event lifecycle**
- New events start in `setup`. **Start** (admin password) sets `live`; **Close** (admin password)
  sets `closed`; a closed event can be started again. Several events may be live.
- Judges can score only while their event is `live`.
- **Duplicate** copies settings, groups and categories into a new `setup` event (no candidates,
  judges or scores).
- **Delete event** is allowed only when it has no scores (admin password).

**Editing once an event has scores** — allowed: renaming the event, groups, categories,
candidates and judges; changing photos and course labels; adding judges; adding candidates
(they start unscored). Blocked, with an explanation: changing a category's max points, deleting
a category, adding a category to a round that already has scores, deleting a group, deleting or
regrouping a scored candidate, deleting a judge who has scores, changing rounds,
`finals_from_zero` or weights, and changing `finalists_per_group` after finalists are set.

**Judges**
- "Add judges: how many?" creates accounts named Judge 1…n (continuing the numbering),
  usernames `{code}-judge{n}`, random 8-character passwords without look-alike characters.
- The event's Judges tab lists name, username and password (revealable), with **Reset password**,
  **Rename**, **Delete** (only without scores) and **Print credential slips**.
- Adding judges after scoring has started is allowed; it lowers averages until they score, as
  adding a judge does today.

**Round 1 lock** — once an event's finalists are set, its Round 1 scores can't be added or changed.

## 5. Admin screens

- **Events** (top of the admin sidebar): list with status and Start / Close / Edit / Duplicate /
  Delete.
- **Event setup** tabs: Settings · Groups · Categories (per round, with a running max total per
  round) · Candidates (table + add/edit form with photo picker) · Judges.
- **Event picker** at the top of the admin sidebar (stored in the session; defaults to the most
  recently started live event). Results, Top N, Notify Judges and PDFs always apply to the picked
  event, whose name is shown on every admin page. Closed events stay viewable.
- **Results** for the picked event: one page per category; "Top N Selection Results" (Round 1
  totals + Set Top N button, tie-break dialog, password confirm); finals page per category;
  "Final Standings" (with Round 1 / Finals / weighted columns when carry-over is on). 1-round
  events show category pages and Final Standings only.
- **PDF** keeps today's layout (white landscape A4, rows never split, one signature line per
  judge) and adds the event name.
- The global Judges page is removed; judges are managed inside each event.

## 6. Judge experience

- After login a judge lands in their own event. If it isn't live: "Your event hasn't started
  yet" / "This event has ended"; the page switches automatically when the admin starts it.
- Sidebar: the event name, Round 1 categories under "Top N Selection" ("Categories" for 1-round
  events), finals categories under "Top N Finalist" only after finalists are set.
- One generic scoring page per category (`/score/{category}`): tabs per group in group order,
  candidate cards with photos, inputs capped at the category max, localStorage drafts keyed by
  category id and judge, review-before-submit dialog, tab locked once fully submitted, Round 1
  lock notice, finals pages list only finalists.

## 7. Computation (must match today exactly)

For an event with judge set J (ordered by id) and |J| ≥ 1:

- **Category result** per candidate: each judge's raw score (missing = 0);
  `average = round(Σ scores / |J|, 2)`.
- **Round result** per candidate: per-category `avg_c = Σ scores_c / |J|` (unrounded);
  shown as `round(avg_c, 2)`; `round_total = round(Σ avg_c, 2)` using unrounded averages.
- **Final standings**: 1 round → Round 1 result. 2 rounds, from zero → Finals round result.
  2 rounds, carry-over → `round(R1_unrounded × w1/100 + F_unrounded × w2/100, 2)` for finalists.
- **Ranking** within each group: sort by total descending (stable); equal totals share a rank and
  the next rank skips (1, 2, 2, 4).
- **Population**: Round 1 = all candidates of the group (ordered by candidate number); finals =
  the group's finalists.
- Formulas live in pure functions (no database access) in one results service.

## 8. Live updates and notifications

- `LiveVersions` stamps become per event: keys `live-version:{event}:{topic}` with topics
  `event` (status, settings, categories, judges), `finalists`, `scores`.
- The shared `live` prop carries the stamps of the event the page shows. Judges watch `event` and
  `finalists` of their event; admins watch all three for the picked event.
- `JudgeCallFeed` and `ScoreSubmissionFeed` become per event (feed key includes the event id;
  events carry `category_id`). Notify Judges works on the picked event's judges and categories.

## 9. Server-side checks

Every score submission: the user is a judge; their event is live; the category belongs to their
event; every candidate belongs to their event (and is a finalist for round-2 categories); each
score is numeric, ≥ 0 and ≤ the category max; Round 1 isn't locked. Scores are saved for the
logged-in judge only. All admin routes use the `admin` middleware (including results pages,
which currently only require login). Start, Close, Delete event, Set Top N and judge deletion
require the admin's current password.

## 10. Photos

- Existing photos stay at `public/candidates/<gender>/<n>.JPEG` with their WebP versions.
- New uploads: the browser crops/resizes before upload (max 1365×2048 JPEG, 480px card WebP,
  96px square thumb WebP — the sizes `CandidatePhoto.jsx` uses today) and uploads the three
  files. They are stored under `public/uploads/candidates/{event}/` (a disk rooted in `public/`,
  avoiding `storage:link`, which can need administrator rights on Windows). The server validates
  type and size.
- `CandidatePhoto.jsx` learns the new path pattern; `npm run images` keeps working for the old
  folders.

## 11. Migrating the current pageant

New tables come from normal migrations. The data move is one command,
`php artisan events:migrate-legacy`, which:

1. runs `db:backup` and stops if it fails;
2. computes today's results from the old tables with a frozen copy of today's formulas
   (`LegacyResults`): every category page (per-judge scores, averages, ranks, order), Round 1
   totals, finals totals;
3. inside one transaction creates Event #1 ("PITON Pageant", code `piton`, live, 2 rounds,
   3 per group, finals from zero), groups Female then Male, the 8 categories with today's names,
   max points and order, links the 22 candidates (gender → group), attaches existing judges
   (usernames `piton-judge{n}` in id order; emails and passwords unchanged), copies finalists
   (setting `finalists_set_at` if any exist) and copies every non-null score cell into `scores`;
4. computes the same results with the new service and compares them value by value, rank by rank
   and in order;
5. commits only on an exact match; otherwise rolls back and prints the differences. Refuses to run
   when Event #1 already exists.

Resets: pending notifications and unsubmitted browser drafts are cleared. **Run the upgrade
between events, not during one.** Old tables (`top_five_selection_scores`, `top_five_scores`,
`top_five_candidates`) stay untouched until the final phase, then are dropped by a separate
migration after a backup and the user's go-ahead.

## 12. Testing

The `tests/TestCase.php` in-memory database guard stays; run `php artisan config:clear` before
tests and `php artisan optimize` after.

- **Calculator unit tests** with hand-checked numbers: missing scores as 0, rounding only at the
  end, ties (1, 2, 2, 4), weighted carry-over, 1-round events, groups ranked separately.
- **Legacy equivalence test**: fill the old tables with realistic data (decimals, missing scores,
  cutoff ties, finalists set), run `events:migrate-legacy`, assert identical results; also assert
  a forced mismatch rolls back.
- **Feature tests**: event CRUD and lifecycle; edit locks; judge auto-creation, username and email
  login, isolation to their own event; every score-submission check; Top N with password; Round 1
  lock; per-event live updates and notifications; photo upload validation; admin-only routes.
- Existing tests are rewritten for the new routes. Public self-registration (`/register`) is
  removed — judges are created by the admin per event — and the pre-existing failing
  `RegistrationTest` goes with it.

## 13. Build phases (on a separate branch; `main` stays usable)

1. New tables, results service, `events:migrate-legacy` with the equivalence check.
2. Generic judge scoring page and judge landing states.
3. Generic admin results pages and PDF; admin middleware on results.
4. Event management (settings, groups, categories, candidates with photo upload, duplicate).
5. Per-event judges (auto-creation, username login, credential slips), per-event live updates and
   notifications; removal of the old pages, `Criteria`, old services; drop old tables (with
   go-ahead).

## 14. Out of scope

Sub-criteria inside categories; more than 2 rounds; judges belonging to several events; public
(unauthenticated) results pages; importing candidates from spreadsheets; multi-admin permissions.
