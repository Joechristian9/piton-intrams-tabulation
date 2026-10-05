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

- Roles: `users.role` is `admin` or `judge`. Admin routes use the `admin` middleware
  (`App\Http\Middleware\EnsureUserIsAdmin`). Every judge belongs to exactly one event
  (`users.event_id`); admins have none. Judges log in with their username (or email).
- **Events** (`events`): name, unique `code` (judge-username prefix), status
  `setup | live | closed` (several can be live), `rounds` 1|2, `finalists_per_group`,
  `finals_from_zero`, `round1_weight`/`finals_weight` (carry-over %), `finalists_set_at`
  (Round 1 lock). Groups (`event_groups`, ranked separately, ordered by `position`),
  categories (`categories`: round, name, `max_score` = most one judge can give, position),
  candidates (`candidates.event_id/group_id`; numbers unique per group), finalists
  (`finalists`, ordered by id = the order they were set), scores (`scores`: one row per
  category × candidate × judge, `decimal(5,2)`).
- Event #1 is the original PITON pageant (code `piton`): Female/Male; Round 1 Production
  Number 10, Sports Wear 25, Swim Wear 25, Formal Wear 25, Casual Interview 15; finals Beauty
  of the Face and Figure 50, Delivery 40, Over-all Appeal / X-factor 10 (from zero). Never
  show "Closed Door Interview" (its old column key was `closed_door_interview`).
- **Results math** lives only in `App\Results\Tabulator` (pure functions; fed by
  `App\Results\EventResults`): category average = Σ judges' scores ÷ the event's number of
  judges (missing = 0), rounded to 2; round total = Σ unrounded category averages, rounded
  at the end; carry-over standings = R1 × w1% + Finals × w2% from unrounded totals; ranks
  1, 2, 2, 4 within each group (stable sort). Round 1 lists a group's candidates by number;
  finals list its finalists. Judge columns are keyed by judge **id** (ordered by id) —
  never look judges up by name.
- Scores are saved only by `Judge\ScoringController` (details below); never trust a
  submitted `judge_id`. Setting finalists locks Round 1 for that event.
- Browser tab titles come from the active sidebar item's label, so renaming a category
  renames the tab too.

## Frontend map

- Judge scoring: `Pages/Judge/Score.jsx` (one page for every category; tabs per group;
  `GroupTab` is defined outside the page so live reloads don't remount cards) with
  `Pages/Judge/Partials/{CandidateGrid,ScoreAlertDialog,ScoreInput,RoundClosedNotice}.jsx`.
  Drafts persist in localStorage via `lib/scoreDrafts.js` (key `score-draft:cat{id}:{judge}`).
  `Pages/Judge/Waiting.jsx` shows "not started" / "ended" for a judge's non-live event.
- `Components/ui/tabs.jsx` keeps only the active tab's **value** in state and renders the
  content from current props.
- Candidate photos: always render through `Components/CandidatePhoto.jsx` (`size="card"` or
  `"thumb"`); it maps `candidates/<gender>/<n>.JPEG` and `uploads/candidates/<event>/<uuid>.jpg`
  to their `.webp` / `-thumb.webp` siblings (a missing WebP breaks the image). Original photos:
  run **`npm run images`** (`scripts/optimize-images.mjs`, sharp) after replacing them.
  Uploads: the browser makes the three sizes (`lib/photoResize.js`).
- `ScoreInput.jsx` animates the glow only while hovered/focused — don't bring back
  always-running per-card animations. `backgrounds/stars.jsx` star counts were cut for
  low-end devices.
- Fonts are self-hosted (`@fontsource/figtree` in `app.jsx`, `@fontsource/orbitron` in
  `Welcome.jsx`) — no external font/CDN links; the event network may have no internet.
- Admin: `Pages/Admin/Events/{Index,Edit,JudgeSlips}.jsx` (+ `Tabs/{Settings,Groups,Categories,
  Candidates,Judges}.jsx`), `Pages/Admin/Results/{Category,Round,Standings}.jsx`,
  `Pages/Admin/NotifyJudges.jsx`; shared `Admin/Partials/{ResultTable,TopFiveSelectionTable,
  TieBreakDialog,ConfirmFinalistsDialog,PrintButton}.jsx`, `Components/PasswordConfirmDialog.jsx`.
- Live updates use `App\Support\EventFeed` in the cache store `config('cache.feed_store')`
  (`file` in the app; `CACHE_FEED_STORE=array` in phpunit.xml). Never write feeds with
  `Cache::store('file')`. Per-event stamps (`App\Support\LiveVersions`, topics `event`,
  `finalists`, `scores`; JS `lib/liveVersions.js`) — judges watch event+finalists, admins all
  three; bump the right topic wherever data other people see changes. Both pollers
  (`Components/JudgeNotifications.jsx`, `Components/ScoreSubmissionToasts.jsx`) chain
  `setTimeout` every 3 s (10 s timeout, paused in hidden tabs) — never `setInterval`. The
  judge banner stays in the page flow (never floating over the group tabs); dismissals are
  kept per judge in localStorage; calls stay 2 hours.
- PDF: `Admin/Partials/PrintButton.jsx` builds a white landscape A4 report with one signature
  line per judge; `html2pdf.js` is lazy-loaded. Keep the bottom padding and `pagebreak.avoid`
  rules (rows and the signature block must never split). **Confidentiality:** printed score
  columns read "Judge 1…n" (headers tagged `data-judge-column` in `ResultTable.jsx`), and the
  signatures show names only, alphabetically (`lib/printReport.js`, tested in
  `tests/js/printReport.test.mjs`), so a printout can't link a judge to their scores. The
  on-screen admin table still shows names.
- Layout: `Layouts/PageLayout.jsx` + `Components/SidebarMain.jsx`, which renders the
  server-built `nav` prop (`App\Support\Navigation`): judges get their live event's categories
  (finals section only after finalists are set); admins get an event picker and that event's
  result pages plus Management (Events, Notify Judges). Landing page `Pages/Welcome.jsx` —
  keep it general and minimal (logo, title, org name, tagline, one login CTA, footer
  "© year Darryl Tamayo & Andrei Sam Pambid").
- Login and other account pages: `Layouts/GuestLayout.jsx` is a dark PITON shell (adds the
  `dark` class). `Pages/Auth/Login.jsx` ("Username or email", show/hide password, inline errors
  with `aria-describedby`, focus on failure, loading state; "Ask the organizer to reset it"
  instead of the broken forgot-password flow). There is no public sign-up.
- Shared backdrop: `Components/PitonBackdrop.jsx` (stars + HUD grid, static for reduced
  motion), used by the landing and login pages.
- Tab titles: `app.jsx` renders `"<title> - PITON"`, or just `"PITON"` when a page sets none.
- Brand assets in `public/`: `PITON LOGO.png` (original), `piton-logo.webp` (384px, use this),
  `isu-logo.webp`, `favicon.ico`, `favicon-32x32.png`, `apple-touch-icon.png`.

## UI work

- Use the `ui-ux-pro-max` skill for visual/UX decisions, but brand wins: dark background,
  gold `yellow-400` primary with blue accents from the logo, Orbitron only for the PITON
  wordmark, Figtree elsewhere.
- Meet the basics: text contrast ≥ 4.5:1 (use `gray-400` or lighter on black, not
  `gray-500/600`), visible focus rings, ≥ 44px tap targets, `prefers-reduced-motion`
  respected, no horizontal scroll at 375px, SVG icons (no emoji).

## Multi-event features (reference)

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
  `admin.events.score_submissions` for the event in `nav.event`.
- Events: `Admin\EventController` (`admin.events.index|store|edit|update|destroy|start|close|duplicate`).
  Several events can be live. Start/Close/Delete need the admin password
  (`Components/PasswordConfirmDialog.jsx` + `Pages/Admin/Events/usePasswordAction.js`). Start
  needs judges, groups, candidates in every group and categories for every round. Locks
  (`App\Support\EventLocks`): rounds/finals settings once scored, Top N once finalists set,
  code once judges exist; going 2 → 1 rounds needs the Round 2 categories deleted and no
  finalists; delete only without scores. Duplicate copies settings, groups and categories
  (code `{code}-copy[-n]`, shortened to fit 20 chars). Uploaded photos use the `uploads` disk
  (`public/uploads`, git-ignored, no storage:link).
- Groups/categories: `Admin\GroupController` (`admin.groups.*`; names unique per event; can't
  delete a group with candidates) and `Admin\CategoryController` (`admin.categories.*`; max
  0.01–999.99; a scored category keeps its max/round and can't be deleted; a round with scores
  takes no new categories, added or moved in). Setup tabs `Pages/Admin/Events/Tabs/{Groups,Categories}.jsx` use
  `Tabs/request.js` (`send(method, url, data, toast, then)` — chain dependent requests).
- Candidates: `Admin\CandidateController` (`admin.candidates.*`; update is PUT via POST +
  `_method` for multipart). Numbers unique per group; photo = `photo` (JPEG ≤ 5 MB) +
  `photo_card` (WebP ≤ 1 MB) + `photo_thumb` (WebP ≤ 200 KB), required together, stored as
  `uploads/candidates/{event}/{uuid}.jpg|.webp|-thumb.webp`; replacing/deleting removes old
  upload files only after the save succeeds (a failed save removes the new files instead) and
  never touches `public/candidates/`. Scored candidates can't be deleted or
  regrouped. `CandidatePhoto.jsx` maps both path styles to their WebPs. Tests use real image
  fixtures in `tests/fixtures/` (this PHP has no WebP support to fake them).
- Judges per event: `App\Support\JudgeAccounts` (`create($event, $count)` → "Judge n",
  `{code}-judge{n}` numbering on from the highest existing, email `{username}@judges.local`,
  8-char password from `ALPHABET`, encrypted copy in `password_plain_encrypted` for
  reveal/reprint; `resetPassword`, `revealPassword`). `Admin\EventJudgeController`
  (`admin.event-judges.store|update|reset|destroy|slips`; delete needs password and no scores).
  Judges tab + `Pages/Admin/Events/JudgeSlips.jsx` (printable). The old global Judges page,
  `JudgeController` and their tests are gone. **Judges can't edit or delete their own
  account**: `/profile` and `password.update` use the `admin` middleware
  (`JudgeAccountLockTest`); the admin renames judges and resets passwords.
- Login: field `login` ("Username or email"); `LoginRequest` uses `email` when it contains
  `@`, otherwise `username`; errors and throttling are keyed on `login`.
- Old tables (`top_five_*`, `candidates.gender`) are dropped by
  `database/migrations/pending/2026_10_20_000001_drop_legacy_score_tables.php`, which plain
  `migrate` never runs. Only with the user's go-ahead, between events: `php artisan db:backup`,
  then `php artisan migrate --path=database/migrations/pending`. After that,
  `events:migrate-legacy` can't run (it needs the old tables).
- Judge tab submit rules live in `resources/js/lib/scoreSheet.js` (`isLocked`, `canSubmit`,
  `scoresToSubmit`: saved scores count as filled, only unsaved candidates are sent — so a
  candidate added mid-event can still be scored). Tested with Node's runner:
  `npm run test:js` (`tests/js/*.test.mjs`). The page clears drafts only when the response is
  the scoring page; a closed event redirects straight to the dashboard with the error.
- Performance: admin setup/list pages load counts and "has scores" flags in bulk
  (`withCount` / `withExists`, `Event::scores()` is a has-many-through) —
  `AdminQueryCountTest` fails if a page's query count grows with the event. Measured
  2026-10-05 on a full Event #1: judge poll 0 queries, scoring page 8, admin results 8–10.
  On the judge page `CandidateGrid` renders memoized `CandidateCard`s and `Score.jsx` keeps
  `handleScoreChange` stable (`useCallback`), so typing re-renders one card, not the grid.
- `php artisan serve` uses the project's `server.php` router (Laravel picks it up when it
  exists): real files get `Cache-Control` (build assets 1 year immutable; photos/fonts 1 hour
  + ETag/304) and JS/CSS/SVG are gzipped; everything else goes to `public/index.php`. The bare
  built-in server sent no caching headers at all. `DevServerTest` starts a real `php -S` with
  it. Restart `php artisan serve` after changing it.
- **A page must never import another page** (e.g. `Edit.jsx` importing from `Index.jsx`): Vite
  folds the imported page into the importer and drops it from the build manifest, so the
  server 500s ("Unable to locate file in Vite manifest"). Put shared code in `lib/`,
  `Components/`, `Partials/` or `Tabs/`. Guarded by `tests/js/pages.test.mjs`. PHP tests use
  `withoutVite()` and can't catch this — after building, open the pages for real.
- Event #1 was migrated on the real database on 2026-10-05 (backups
  `database/backups/piton-2026-10-05_125401.sqlite` before, `…_125415.sqlite` just before import).
- Routes are cached too: clear them (`php artisan route:clear`) before tests after route
  changes, then `php artisan optimize` when done.
- `php artisan events:migrate-legacy` moves the old pageant into Event #1 (code `piton`):
  refuses if an event with code `piton` exists, if old score rows came from non-judge accounts,
  or if the old tables hold duplicate (candidate/finalist, judge) rows. It only touches judges
  and candidates not yet in any event, so events created first are safe. It runs
  `db:backup`, imports in a transaction, and commits only if `App\Legacy\Snapshot::diff`
  against `App\Legacy\LegacyResults` (frozen copy of the old formulas, `DB::table` only —
  never "improve" it) is empty. Run it between events only. Its test keeps RefreshDatabase
  but returns [] from `connectionsToTransact()` and runs `migrate:fresh` around each test
  (VACUUM can't run inside a transaction; `DatabaseMigrations` broke later test files by
  rolling back the shared in-memory schema).

## Known issues (project analysis, 2026-10-03; updated 2026-10-05) — not yet fixed unless noted

Ask the user before fixing; report them when they touch the area you're working on.

1. ~~Admin results and Top 3 selection weren't admin-only~~ — fixed: every admin route uses
   the `admin` middleware.
2. ~~Score submissions trusted `judge_id`~~ — fixed: scores are saved for the logged-in judge.
3. **A judge can resubmit (overwrite) their own scores by a direct request.** The UI locks a
   submitted tab, but `score.store` upserts. Round 1 is locked server-side once finalists are set.
4. ~~`/profile` let a judge delete or change their own account~~ — fixed: admins only.
5. ~~Public registration~~ — removed; judges are created per event.
6. **Password reset can't work** (`MAIL_MAILER=log`); the login page tells users to ask the
   organizer instead. The `/forgot-password` routes still exist.
7. **`APP_DEBUG=true`** — detailed error pages expose code and config; turn off for the event.
8. **Backups are manual.** `php artisan db:backup` exists; run it before and during the event.
9. Code health: `console.log` of user details in `Pages/Dashboard.jsx`. (The 8 duplicated judge
   pages were replaced by `Pages/Judge/Score.jsx`.)

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
