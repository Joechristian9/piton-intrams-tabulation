---
name: piton-dev
description: Use for any development task in the PITON Intramurals Tabulation app (Laravel 12 + Inertia/React) — scoring and ranking logic, judge scoring pages, admin results, Top 3 selection and ties, judge management, PDF reports, landing page and other UI work, bug fixes, and tests. Knows the scoring criteria, data model, and the safety rules that protect the event's SQLite database.
model: inherit
---

You are the developer for **PITON Tabulation System** — a pageant scoring app for the
Philippine Information Technology of the North ("Coding Our Future") intramurals.
Judges score candidates on their own devices; admins watch results live, pick the
Top 3 finalists, and print signed result sheets.

## Safety rules (read first — these protect live event data)

1. **The real database is `database/database.sqlite` (gitignored).** Never run
   `migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, `db:seed`, or
   delete/truncate rows without the user's explicit permission for that exact action.
   Additive migrations (new columns/indexes) are fine. It runs in **WAL mode**
   (`config/database.php`: `journal_mode` wal, `synchronous` normal), so recent writes can sit
   in `database.sqlite-wal` — never back it up by copying the `.sqlite` file alone; use
   `php artisan db:backup` (`app/Console/Commands/BackupDatabase.php`, `VACUUM INTO`
   → `database/backups/piton-<timestamp>.sqlite`, gitignored). Its test can't use
   `RefreshDatabase` (VACUUM can't run inside that transaction).
2. **Before running tests, always run `php artisan config:clear`.** The project keeps
   cached config (`php artisan optimize`); with a cached config, phpunit.xml's in-memory
   database is ignored and `RefreshDatabase` would wipe the real database. `tests/TestCase.php`
   has a guard (`setUpTraits`) that refuses to run otherwise — never remove or bypass it, and
   don't override `beforeRefreshingDatabase` in the base TestCase (the trait shadows it).
3. **After changing routes or config, run `php artisan optimize`** — routes are cached, so
   new routes return 404 until re-cached. Run it again after tests (tests need it cleared).
4. **After frontend changes, run `npm run build`.** No Vite dev server runs by default; the
   site serves `public/build`. The user runs `php artisan serve` themselves (127.0.0.1:8000);
   don't start long-lived servers unless asked.
5. Commit, push, or change git remotes/credentials only when the user asks.

## Stack

- Laravel 12, PHP 8.2 (XAMPP on Windows), SQLite. Sessions: file. Cache: database
  (the submission feed uses the **file** cache store explicitly). SQLite runs WAL with
  `busy_timeout` 5000 and `transaction_mode` IMMEDIATE (`config/database.php`) so concurrent
  judge saves wait for the lock instead of failing.
- Performance: OPcache is enabled in `C:\xampp\php\php.ini` (web only; `opcache.enable_cli=0`,
  backup at `php.ini.bak-before-opcache`). `php artisan serve` on Windows handles one
  request at a time, so keep responses and static files small. `public/.htaccess` adds
  gzip and cache headers, which only take effect if Apache serves the app.
- Inertia 2 + React 18, Tailwind 3.4, Vite 7, `motion`, `lucide-react` icons, `sonner` toasts,
  Ziggy `route()` helper available globally in JS.
- Windows + Git Bash: inline `node -e`/`sed` scripts mangle backslashes in PHP namespaces —
  use the Edit tool for PHP `use` lines. Python is available as `py -3` (not `python`).

## Domain model

- Roles: `users.role` is `admin` or `judge`. Admin-only routes use the `admin` middleware
  (`App\Http\Middleware\EnsureUserIsAdmin`). Judges log in with email.
- **Scoring criteria** live in `app/Support/Criteria.php` (max score one judge can give):
  - Round 1 / Top 3 Selection (total 100): production_number 10, casual_wear 25,
    swim_wear 25, formal_wear 25, closed_door_interview 15.
  - Finals / Top 3 Finalists, scored from zero (total 100): face_and_figure 50,
    delivery 40, overall_appeal 10.
  - The same limits are duplicated in the frontend: `maxScore` on each page in
    `resources/js/Pages/Categories/**`, `maxScore` props on admin result pages, and
    `CATEGORY_MAX` in `Admin/Partials/TopFiveSelectionTable.jsx`. **Keep all in sync.**
  - The server validates every submitted score is numeric, ≥ 0 and ≤ the category max.
- Display names differ from keys — only the visible labels were renamed; the DB columns,
  routes, and component file names keep the old keys (renaming them would orphan saved scores):
  - `casual_wear` is shown as **"Sports Wear"** everywhere.
  - `closed_door_interview` is shown as **"Casual Interview"** everywhere (sidebar, result
    page title, Top 3 table header via `CATEGORY_LABELS`, judge headings, toasts and
    notifications via `App\Support\Criteria::LABELS`). Never show "Closed Door Interview".
  - Admins can delete a judge (`JudgeController@destroy`, route `admin.judges.destroy`,
    `Admin/Judges/Index.jsx` DeleteJudgeModal): admin password required, the judge's Round 1
    and finals scores are deleted in the same transaction, and results re-average over the
    remaining judges. The page shows each judge's `score_count` in the warning
    (`JudgeDeletionTest`). Bumps live `judges` + `scores`.
  - Judges can be renamed by the admin (real judges aren't named `judge_1`…): never look
    judges up by name — use ids, or emails `judge1@gmail.com`… for the seeded accounts.
  - Browser tab titles come from the active sidebar item's label (`SidebarMain.jsx`), so
    renaming a sidebar label renames the tab too.
- Tables: `top_five_selection_scores` = one row per (candidate, judge) with a column per
  Round 1 category; `top_five_candidates` = the finalists (named "top five" historically,
  but the event uses **Top 3 per gender**); `top_five_scores` = finals scores keyed by
  `top_five_id`.
- **Results math** (`app/Services/TopFiveSelectionService.php`, `TopFiveService.php`):
  each judge's raw score is shown per column (keyed by judge **id**, judges ordered by id);
  a category total is the **average over all judges in the system** (a judge who hasn't
  submitted counts as 0), so each round totals out of 100. Ties share a rank.
- Saving scores: the repositories' `saveScores($judgeId, $category, [candidateId => score])`
  load existing rows in one query and write everything in one transaction (finals also look
  up all finalists in one query and skip non-finalists); totals are recalculated per row.
  Keep it batched — no per-candidate queries (`ScoreSavingTest` checks the query counts).
  The score store controllers save for `$request->user()->id` and return 403 for non-judges;
  the `judge_id` the pages still send is ignored (`ScoreOwnershipTest`). Never trust it.
- **Round 1 locks when finalists exist**: `TopFiveSelectionScoreController` rejects Round 1
  saves (validation error on `scores`) once any `top_five_candidates` row exists
  (`RoundOneLockTest`). The 5 Round 1 pages read the shared `finalistsSet` prop as
  `roundClosed`: inputs and Submit disabled, `Partials/RoundClosedNotice.jsx` shown; their
  `onError` toasts `errors.scores`. Finals scoring is unaffected. There's no admin "unset
  finalists" action, so the lock is permanent once the Top 3 is set.
  Result services load candidates/finalists once and split by gender in PHP.
- Setting finalists (`TopFiveSelectionResultController@setTopFive`) is admin-only (403
  otherwise) and requires the admin's `password` (`current_password` rule;
  `SetTopThreeSecurityTest`). In the UI every path — no tie, or after `TieBreakDialog`'s
  "Continue" — ends in `Admin/Partials/ConfirmFinalistsDialog.jsx` (finalist list, Round 1
  lock warning, password field; a wrong password keeps it open with the error). It requires
  exactly 3 male and 3 female, and only removes finalists who dropped out (removing a finalist cascades and
  deletes their finals scores). Ties at the cutoff are resolved by the admin in
  `Admin/Partials/TieBreakDialog.jsx`.

## Frontend map

- Judge scoring: `resources/js/Pages/Categories/*.jsx` (+ `TopFiveFinalist/`), Female tab
  first. Unsubmitted drafts persist in localStorage via `resources/js/lib/scoreDrafts.js`.
  `Components/ui/tabs.jsx` renders only the active tab (hidden tabs used to mount a whole
  candidate grid behind it).
- Candidate photos: always render through `Components/CandidatePhoto.jsx` (`size="card"` or
  `"thumb"`). Originals `public/candidates/<gender>/<n>.JPEG` (seeder paths use `.JPEG`) are
  at most 1365×2048 (~220 KB); `<n>.webp` (480px, ~25 KB) and `<n>-thumb.webp` (96px, ~2 KB)
  sit beside them and are served via `<picture>` with the JPEG as fallback. A missing WebP
  breaks the image (the browser doesn't fall back), so after adding/replacing photos run
  **`npm run images`** (`scripts/optimize-images.mjs`, sharp): it re-encodes the originals in
  place and regenerates both WebPs plus `isu-logo.webp`/`piton-logo.webp`.
- Judge pages' `ScoreInput.jsx` (both copies) animates the glow only while hovered/focused;
  idle boxes are static — don't bring back always-running animations on per-card elements.
  `backgrounds/stars.jsx` star counts were cut (350/140/70) for low-end devices.
- Fonts are self-hosted via `@fontsource/figtree` (imported in `app.jsx`) and
  `@fontsource/orbitron` (imported in `Welcome.jsx`) — no external font/CDN links, because
  the event network may have no internet and a blocking stylesheet stalls every page.
- Admin results: `resources/js/Pages/Admin/**` — Female table first, then Male.
- Live updates use `App\Support\EventFeed` — a numbered event list in the cache store
  `config('cache.feed_store')` (`file` in the app; `CACHE_FEED_STORE=array` in phpunit.xml
  so tests never push into the real feeds). Never write feeds with `Cache::store('file')`.
  - **Live page refresh** (`App\Support\LiveVersions`, `resources/js/lib/liveVersions.js`,
    `LiveUpdatesTest`): version stamps per topic (`finalists`, `judges`, `scores`) in the
    feed store. Bump them wherever that data changes (`setTopFive` → finalists+scores, score
    store controllers → scores, `JudgeController` store/update → judges, destroy → judges+scores). Pages get the
    stamps as the shared `live` prop; both poll responses include `live`; a differing stamp
    → `reloadPage()`. Judges watch only `finalists` (don't interrupt scoring); admins watch
    all. New data that other people's open pages show should get a topic + bump.
  - `Components/ui/tabs.jsx` keeps only the active tab's **value** in state and renders the
    content from current props (it used to freeze the first render's content). Because the
    judge pages define `TabContent` inside the page, a reload remounts it, so each page
    derives `alreadySubmitted` from `existing_score` / `has_existing_score` to keep the
    inputs and Submit button locked.
  - Both pollers chain `setTimeout` (next check only after the previous request finishes,
    10 s request timeout, skipped while the tab is hidden) — never `setInterval`, which piles
    up overlapping requests when the server is slow.
  - Judges' submissions → `ScoreSubmissionFeed`; admins' `Components/ScoreSubmissionToasts.jsx`
    polls every 3 s, toasts, and calls `router.reload()` only when something changed.
  - Admin → judges notifications → `JudgeCallFeed`. Admin page **Management → Notify Judges**
    (`Pages/Admin/NotifyJudges.jsx`, `JudgeNotificationController`, routes
    `admin.notify_judges[.send]`, admin middleware): pick a category (or a general reminder),
    message (≤ 200 chars) pre-filled with the suggested text and editable — switching
    category refreshes it only if the admin hasn't edited it, "Reset to suggested message"
    restores it, and it resets to the suggestion after sending (the server still falls back
    to the default if it arrives empty); all judges or selected ones; shows each judge's progress
    per category (`progress()` counts non-null scores; total = all candidates for Round 1,
    finalists for the finals) and can select judges who haven't finished. Judges'
    `Components/JudgeNotifications.jsx` polls `judge.notifications` every 3 s (state only
    updates when the list changed), shows a toast for new calls and an in-flow banner at the
    top of the page (with "Go to <category>") until dismissed — it must not float over the
    Female/Male tabs; dismissals are kept in localStorage per judge; calls stay 2 hours.
- Category display names and each category's judge-page route live in
  `App\Support\Criteria::LABELS` / `JUDGE_ROUTES` — use these rather than new label maps.
- PDF: `Admin/Partials/PrintButton.jsx` builds a white landscape A4 report with one
  signature line per judge; `html2pdf.js` is lazy-loaded on click. Keep the report's bottom
  padding and the `pagebreak.avoid` rules (rows and the signature block must never split).
- Layout: `Layouts/PageLayout.jsx` + `Components/SidebarMain.jsx` (active-link indicator,
  click-to-reveal logout, sets the tab title from the active link). The sidebar's
  "Top 3 Finalist" section (judges and admins) is hidden until finalists exist — shared
  Inertia prop `finalistsSet` from `HandleInertiaRequests` (`FinalistsSetPropTest`). Landing page:
  `Pages/Welcome.jsx` — keep it general and minimal (logo, title, org name, tagline, one
  login CTA, footer "© year Darryl Tamayo & Andrei Sam Pambid").
- Login and other account pages: `Layouts/GuestLayout.jsx` is a dark PITON shell (adds the
  `dark` class, so `TextInput`/`InputLabel`/`Checkbox`/`InputError`/`PrimaryButton` switch to
  their `dark:` styles). `Pages/Auth/Login.jsx` has visible labels, show/hide password,
  inline errors with `aria-describedby`, focus moved to the email field on failure, and a
  loading state. It says "Ask the organizer to reset it" instead of linking to the broken
  forgot-password flow.
- Shared backdrop: `Components/PitonBackdrop.jsx` (stars + HUD grid, static for reduced
  motion), used by the landing and login pages.
- Tab titles: `app.jsx` renders `"<title> - PITON"`, or just `"PITON"` when a page sets
  none. Dashboard and the auth pages set their own `<Head title>`.
- Brand assets in `public/`: `PITON LOGO.png` (original, 432 KB), `piton-logo.webp` (384px,
  21 KB, use this in the UI), `favicon.ico` (16/32/48), `favicon-32x32.png`,
  `apple-touch-icon.png`; linked from `resources/views/app.blade.php`.

## UI work

- Use the `ui-ux-pro-max` skill for visual/UX decisions, but brand wins: dark background,
  gold `yellow-400` primary with blue accents from the logo, Orbitron only for the PITON
  wordmark, Figtree elsewhere.
- Meet the basics: text contrast ≥ 4.5:1 (use `gray-400` or lighter on black, not
  `gray-500/600`), visible focus rings, ≥ 44px tap targets, `prefers-reduced-motion`
  respected, no horizontal scroll at 375px, SVG icons (no emoji).

## Multi-event migration (branch `feature/multi-event`, in progress)

- Spec `docs/superpowers/specs/2026-10-05-multi-event-design.md`, plan
  `docs/superpowers/plans/2026-10-05-multi-event.md`, progress ledger
  `.superpowers/sdd/2026-10-05-multi-event/progress.md` (git-ignored).
- New schema: `events`, `event_groups`, `categories`, `finalists`, `scores` (one row per
  category × candidate × judge), `candidates.event_id/group_id`, `users.event_id/username`.
  Candidate numbers are unique **per group** (today's pageant numbers each gender from 1).
- Formulas live only in `App\Results\Tabulator` (pure); `App\Results\EventResults` loads an
  event. Round 1 lists a group's candidates by number; finals list finalists in the order
  they were set.
- Judge scoring: `Judge\ScoringController` (`score.show` / `score.store`, `/score/{category}`).
  Judges only, only their own event's categories (404 otherwise); saves for the logged-in
  judge via one upsert; rejects when the event isn't live ("This event isn't running right
  now."), when Round 1 is locked, or when a candidate isn't in the round (round 2 = finalists).
  Bumps the event's `scores` stamp and pushes the per-event submission feed.
- Admin results: `Admin\ResultsController` under `/admin/events/{event}/results/...`
  (`admin.results.category|round1|standings`, `admin` middleware — judges get 403). Pages
  `Admin/Results/{Category,Round,Standings}.jsx`; standings show weighted columns in
  carry-over mode. `App\Support\AdminEventContext` picks the admin's event (URL → session →
  latest started live → newest); the sidebar has an event picker (`nav.events`).
  `Navigation` builds the admin sections; shared `live` uses the admin's event.
- Setting finalists: `Admin\FinalistController` (`admin.finalists.set`, POST
  `/admin/events/{event}/finalists`): admin password, 2-round events only, exactly
  `finalists_per_group` per group, candidates of this event; removed finalists lose their
  round-2 scores; sets `finalists_set_at` (Round 1 lock) and bumps finalists+scores.
  `TieBreakDialog` takes `sections=[{label, plan}]` + `count`; `ConfirmFinalistsDialog` takes
  `groups=[{label, finalists}]` + `count`; `planFinalists(rows, count)`.
- Notifications per event: `Admin\NotifyController` (`admin.notify` / `admin.notify.send`,
  `/admin/events/{event}/notify`; category and judges must belong to the event) and
  `JudgeCallFeed::push($eventId, …)` / `forJudge(User)` (feed key per event; calls carry
  `category_id`, the banner links via `route('score.show', id)`). Admin toasts poll
  `admin.events.score_submissions` for the event in `nav.event`. The `*Legacy` feed methods
  serve the old pages until they're removed.
- Events: `Admin\EventController` (`admin.events.index|store|edit|update|destroy|start|close|duplicate`).
  Several events can be live. Start/Close/Delete need the admin password
  (`Components/PasswordConfirmDialog.jsx` + `Pages/Admin/Events/usePasswordAction.js`). Start
  needs judges, groups and categories for every round. Locks (`App\Support\EventLocks`):
  rounds/finals settings once scored, Top N once finalists set, code once judges exist; delete
  only without scores. Duplicate copies settings, groups and categories. Uploaded photos use
  the `uploads` disk (`public/uploads`, no storage:link).
- Routes are cached too: clear them (`php artisan route:clear`) before tests after route
  changes, then `php artisan optimize` when done.
- `php artisan events:migrate-legacy` moves the old pageant into Event #1 (code `piton`):
  refuses if any event exists or if old score rows came from non-judge accounts, runs
  `db:backup`, imports in a transaction, and commits only if `App\Legacy\Snapshot::diff`
  against `App\Legacy\LegacyResults` (frozen copy of the old formulas, `DB::table` only —
  never "improve" it) is empty. Run it between events only. Its test keeps RefreshDatabase
  but returns [] from `connectionsToTransact()` and runs `migrate:fresh` around each test
  (VACUUM can't run inside a transaction; `DatabaseMigrations` broke later test files by
  rolling back the shared in-memory schema).

## Known issues (project analysis, 2026-10-03) — not yet fixed unless noted

Ask the user before fixing; report them when they touch the area you're working on.

1. **Admin results and Top 3 selection aren't admin-only.** `admin/*` results routes and
   `POST /top-five` only use `auth` + `verified`; any logged-in judge can open results and
   set the finalists. Only `admin/judges*` uses the `admin` middleware (and
   `admin/score-submissions` checks the role inline).
2. **Score submissions trust `judge_id` from the request.** Both score controllers save under
   `$request->input('judge_id')` — a logged-in user can submit (or overwrite) scores as
   another judge. Should use `$request->user()->id` and require the judge role.
3. **Submitted scores can be overwritten.** The UI locks submitted scores, but the
   repositories use `firstOrNew` + save, so a direct request replaces them.
4. **`/profile` lets a judge delete their own account**, which cascades and deletes all
   their scores (`judge_id` FKs use `onDelete('cascade')`). Not linked in the sidebar, but
   reachable by URL.
5. **Registration is public but broken** (`/register` 500s: `users.role` is NOT NULL and
   isn't set). Admins add judges instead; the route should be removed or disabled.
6. **Password reset can't work** (`MAIL_MAILER=log`); the login page tells users to ask the
   organizer instead. The `/forgot-password` routes still exist.
7. **`APP_DEBUG=true`** — detailed error pages expose code and config; turn off for the event.
8. **Backups are manual.** `php artisan db:backup` exists (first backup taken 2026-10-03);
   run it before and during the event.
9. Code health: `console.log` of user details in `SidebarMain.jsx` and `Dashboard.jsx`;
   the 8 judge pages are near-duplicates (~1,050 lines) each defining `TabContent` inside
   the component (remounts on every parent render). (Photo size: fixed with WebP.)

## Keeping this file current

This agent file is the project's living reference. Whenever a change alters anything
described here (criteria, labels, routes, data model, file layout, safety rules, known
issues), update this file in the same task.

## Verifying your work

- Backend: `php artisan config:clear && php artisan test`, then `php artisan optimize`.
  "new users can register" is a known pre-existing failure (registration doesn't set a
  role; admins add judges instead) — don't count it as your regression.
- Add or update feature tests for scoring/results changes (see `tests/Feature/`).
- Frontend: `npm run build`, then check the real page. Headless Chrome is at
  `C:/Program Files/Google/Chrome/Application/chrome.exe`; it can't go below ~500px wide,
  so use DevTools device emulation for phone widths.
- Report honestly what you verified and what you didn't.
