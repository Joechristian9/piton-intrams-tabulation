# Multi-Event Tabulation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn the single hard-coded pageant into a multi-event system (events, groups, categories, candidates with photos, per-event judges) and migrate the current pageant into Event #1 with byte-identical results.

**Architecture:** One normalized schema (`events`, `event_groups`, `categories`, `finalists`, `scores`, plus `event_id` on `candidates`/`users`) replaces the column-per-category tables. A pure `Tabulator` holds every formula; `EventResults` loads an event and feeds it. A frozen `LegacyResults` copy of today's formulas lets `events:migrate-legacy` prove equivalence before committing. Generic judge/admin pages replace the 16 per-category pages; the sidebar is built server-side from the event's categories.

**Tech Stack:** Laravel 12, PHP 8.2, SQLite (WAL), Inertia 2 + React 18, Tailwind 3.4, Vite 7, PHPUnit (class-style tests), Ziggy `route()`.

**Spec:** `docs/superpowers/specs/2026-10-05-multi-event-design.md`

## Global Constraints

- Work on branch `feature/multi-event`; never push; `main` must stay runnable for the current pageant until Task 21.
- Before every test run: `php artisan config:clear`. After route/config changes and at the end of each task: `php artisan optimize`. Never weaken the `:memory:` guard in `tests/TestCase.php`.
- After frontend changes: `npm run build` (no Vite dev server; `public/hot` must not exist).
- Formulas (spec §7): category `round(Σ/|J|, 2)` with missing = 0 and `|J|` floored at 1; round total `round(Σ unrounded averages, 2)`; carry-over `round(R1_raw × w1/100 + F_raw × w2/100, 2)`; ranks 1,2,2,4 (stable sort by total desc).
- Scores and max points are `decimal(5,2)`; a score must be numeric, ≥ 0, ≤ category max.
- Judges are saved as the logged-in user; never trust a submitted `judge_id`.
- Passwords for Start, Close, Delete event, Set Top N, judge deletion: Laravel `current_password` rule.
- Event #1: name `PITON Pageant`, code `piton`, status `live`, 2 rounds, 3 finalists per group, finals from zero; groups `Female` (position 1), `Male` (position 2); categories in this order — round 1: Production Number 10, Sports Wear 25, Swim Wear 25, Formal Wear 25, Casual Interview 15; round 2: Beauty of the Face and Figure 50, Delivery 40, Over-all Appeal / X-factor 10. Never display "Closed Door Interview".
- Judge usernames `{code}-judge{n}`; generated passwords 8 chars from `ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789`.
- Uploads live under `public/uploads/candidates/{eventId}/`; existing photos stay under `public/candidates/`.
- Keep `.claude/agents/piton-dev.md` in sync with every behavior change (fold the doc edit into the task that changes the behavior).
- UI follows the existing dark PITON style (neutral-900 panels, yellow-400 accents, ≥ 44px tap targets, visible focus rings, text ≥ gray-400 on black).

## Review Focus

1. **Event closed while a judge is mid-scoring** — a stale page submits after Close; expect a validation error "This event isn't running right now." and no write (test in Task 8).
2. **Unsupported photo** (HEIC from an iPhone, PDF, > 5 MB) — browser can't decode HEIC; expect a clear client message and a server 422, never a broken image (tests in Task 15, client guard in Task 16).
3. **Duplicate names/numbers** — two candidates with the same number or two groups with the same name in one event; expect field errors, not a 500 (tests in Tasks 14–15).
4. **Starting an incomplete event** — no judges, no groups, or an empty round; Start must refuse with the reason (|J| = 0 would silently divide by 1) (test in Task 13).
5. **Tied candidates in a different stored order** on the real database — equivalence must compare ranks/totals strictly but not fail on the display order inside a tie (test in Task 5).

---

## File Structure

| Path | Responsibility |
|---|---|
| `database/migrations/2026_10_06_000001_create_multi_event_tables.php` | New tables + new columns on `candidates`, `users` |
| `app/Models/{Event,EventGroup,Category,Finalist,Score}.php` | Eloquent models; `Candidate`, `User` gain relations |
| `app/Results/Tabulator.php` | Pure formulas: category, round, weighted, rank |
| `app/Results/EventResults.php` | Loads an event from the DB and returns page-ready results |
| `app/Legacy/LegacyResults.php` | Frozen copy of today's formulas over the old tables (`DB::table` only) |
| `app/Legacy/LegacyImporter.php` | Copies old data into Event #1 |
| `app/Legacy/Snapshot.php` | Normalizes legacy/new results to one comparable shape; `diff()` |
| `app/Console/Commands/MigrateLegacyEvent.php` | `events:migrate-legacy` |
| `app/Support/LiveVersions.php` | Per-event live stamps (modified) |
| `app/Support/{EventFeed,JudgeCallFeed,ScoreSubmissionFeed}.php` | Per-event feeds (modified) |
| `app/Support/Navigation.php` | Builds the sidebar items for a user (shared `nav` prop) |
| `app/Support/EventLocks.php` | "Has scores" checks used by every edit-lock rule |
| `app/Support/AdminEventContext.php` | Which event the admin is looking at (route param → session → latest live) |
| `app/Support/JudgeAccounts.php` | Username/password generation |
| `app/Http/Controllers/Judge/{HomeController,ScoringController}.php` | Judge landing + scoring |
| `app/Http/Controllers/Admin/{EventController,GroupController,CategoryController,CandidateController,EventJudgeController,ResultsController,FinalistController,NotifyController,ScoreSubmissionController}.php` | Admin features |
| `resources/js/Pages/Judge/{Score,Waiting}.jsx` | Generic scoring page, landing states |
| `resources/js/Pages/Admin/Results/{Category,Round,Standings}.jsx` | Generic results pages |
| `resources/js/Pages/Admin/Events/{Index,Edit}.jsx` + `Admin/Events/Tabs/*.jsx` | Event list and setup tabs |
| `resources/js/Pages/Admin/Events/JudgeSlips.jsx` | Printable credential slips |
| `resources/js/lib/photoResize.js` | Browser-side resize to the three photo sizes |

---

## Phase 0 — Branch

### Task 0: Branch from a clean, green main

**Files:** none

- [ ] **Step 1:** `git status --short` — only `docs/` (this spec and plan) may be untracked; anything else: stop and ask the user. (Earlier session work was committed as `2a47cd8`.)
- [ ] **Step 2:** `git switch -c feature/multi-event`, then `git add docs && git commit -m "docs: multi-event spec and plan"`
- [ ] **Step 3:** `php artisan config:clear && php artisan test` — expected: all pass except `RegistrationTest > new users can register` (pre-existing; removed in Task 19). Record the count.

---

## Phase 1 — Schema, calculator, legacy migration

### Task 1: Schema and models

**Files:**
- Create: `database/migrations/2026_10_06_000001_create_multi_event_tables.php`, `app/Models/{Event,EventGroup,Category,Finalist,Score}.php`, `database/factories/{EventFactory,EventGroupFactory,CategoryFactory}.php`
- Modify: `app/Models/Candidate.php`, `app/Models/User.php`
- Test: `tests/Feature/MultiEvent/SchemaTest.php`

**Interfaces — Produces:**
- `Event`: fillable `name, code, status, rounds, finalists_per_group, finals_from_zero, round1_weight, finals_weight`; casts `finals_from_zero` bool, `finalists_set_at/started_at/closed_at` datetime; consts `SETUP='setup'`, `LIVE='live'`, `CLOSED='closed'`; relations `groups()` (ordered `position`), `categories()` (ordered `round, position`), `candidates()`, `judges()` (`User` where `role=judge`), `finalists()` (ordered `id`), `isLive(): bool`, `finalistsSet(): bool`.
- `EventGroup` (`event_groups`): `event()`, `candidates()`.
- `Category`: fillable `event_id, round, name, max_score, position`; cast `max_score` `float`; `event()`, `scores()`.
- `Finalist`: `event()`, `candidate()`.
- `Score`: fillable `category_id, candidate_id, judge_id, score`; cast `score` `float`.
- `Candidate` adds `event_id, group_id` to fillable, `event()`, `group()`; `User` adds `event_id, username` fillable, `password_plain_encrypted` hidden, `event()`.
- Columns per spec §3; `candidates.event_id/group_id` and `users.event_id/username` nullable (old rows exist until Task 5 runs); unique (`group_id`,`candidate_number`), (`event_id`,`name`) on groups, (`category_id`,`candidate_id`,`judge_id`) on scores, (`event_id`,`candidate_id`) on finalists, unique `events.code`, unique `users.username`.

- [ ] **Step 1: Write the failing test** — `SchemaTest`:
  - `test_event_has_ordered_groups_and_categories`: create event, groups positions 2,1, categories (round 2 pos 1), (round 1 pos 2), (round 1 pos 1) → `$event->groups->pluck('position')` is `[1,2]`; `$event->categories->map(fn($c)=>[$c->round,$c->position])->all()` is `[[1,1],[1,2],[2,1]]`.
  - `test_a_judge_score_is_unique_per_category_candidate_and_judge`: second `Score::create` with the same triple throws `Illuminate\Database\UniqueConstraintViolationException`.
  - `test_candidate_numbers_are_unique_per_event_but_reusable_across_events`.
- [ ] **Step 2:** `php artisan test --filter=SchemaTest` → FAIL (class/table missing).
- [ ] **Step 3:** Write the migration (down() drops new tables and columns) and models.
- [ ] **Step 4:** `php artisan test --filter=SchemaTest` → PASS; full suite still green.
- [ ] **Step 5:** `git add -A && git commit -m "feat(events): multi-event schema and models"`

### Task 2: Tabulator (pure formulas)

**Files:** Create `app/Results/Tabulator.php`; Test `tests/Unit/TabulatorTest.php`

**Interfaces — Produces** (all `public static`, no DB access; `$candidates` are arrays with at least `id`, passed through untouched as `candidate`):
- `category(array $candidates, array $judgeIds, array $scores): array` — `$scores[candidateId][judgeId] = float`. Rows `['candidate'=>…, 'scores'=>[judgeId=>float], 'total'=>float, 'rank'=>int]`, ranked.
- `round(array $candidates, array $categoryIds, array $judgeIds, array $scores): array` — `$scores[candidateId][categoryId][judgeId] = float`. Rows `['candidate', 'scores'=>[categoryId=>float rounded 2], 'total'=>float, 'raw_total'=>float, 'rank']`.
- `weighted(array $round1Rows, array $finalsRows, int $round1Weight, int $finalsWeight): array` — joins on `candidate['id']`, keeps finals rows' candidates only. Rows `['candidate','round1'=>float,'finals'=>float,'total'=>float,'rank']`.
- `rank(array $rows): array` — stable `usort` by `total` desc; tie when `===` equal; rank = 1-based index of the first of the tie.

- [ ] **Step 1: Write the failing test**

```php
public function test_category_averages_over_all_judges_and_ranks_ties_1_2_2_4(): void
{
    $c = fn ($id) => ['id' => $id];
    $rows = Tabulator::category([$c(10), $c(11), $c(12), $c(13)], [1, 2, 3], [
        10 => [1 => 20.0, 2 => 22.5],             // judge 3 missing -> 0
        11 => [1 => 25.0, 2 => 25.0, 3 => 25.0],
        12 => [1 => 20.0, 2 => 22.5, 3 => 0.0],
    ]);
    $this->assertSame([11, 10, 12, 13], array_map(fn ($r) => $r['candidate']['id'], $rows));
    $this->assertSame([25.0, 14.17, 14.17, 0.0], array_column($rows, 'total'));
    $this->assertSame([1, 2, 2, 4], array_column($rows, 'rank'));
    $this->assertSame([1 => 20.0, 2 => 22.5, 3 => 0.0], $rows[1]['scores']);
}

public function test_round_total_rounds_only_at_the_end(): void
{
    $rows = Tabulator::round([['id' => 1]], [100, 200], [1, 2, 3], [
        1 => [100 => [1 => 10.0, 2 => 10.0, 3 => 11.0], 200 => [1 => 10.0, 2 => 10.0, 3 => 11.0]],
    ]);
    $this->assertSame([100 => 10.33, 200 => 10.33], $rows[0]['scores']);
    $this->assertSame(20.67, $rows[0]['total']);   // not 20.66
}

public function test_weighted_total_uses_unrounded_round_totals(): void
{
    $r1 = [['candidate' => ['id' => 1], 'raw_total' => 85.4, 'total' => 85.4]];
    $f  = [['candidate' => ['id' => 1], 'raw_total' => 92.1, 'total' => 92.1]];
    $rows = Tabulator::weighted($r1, $f, 40, 60);
    $this->assertSame(89.42, $rows[0]['total']);
    $this->assertSame([85.4, 92.1], [$rows[0]['round1'], $rows[0]['finals']]);
}

public function test_no_judges_divides_by_one(): void
{
    $rows = Tabulator::category([['id' => 1]], [], []);
    $this->assertSame(0.0, $rows[0]['total']);
}
```

- [ ] **Step 2:** `php artisan test --filter=TabulatorTest` → FAIL.
- [ ] **Step 3:** Implement. Category: iterate `$judgeIds` in given order, `$score ?? 0.0`, `round(array_sum / max(1, count), 2)`. Round: per category `Σ / max(1,|J|)` unrounded; `scores` = `round(avg, 2)`; `raw_total = Σ avg`; `total = round(raw_total, 2)`. Weighted: `round($r1Raw * $w1 / 100 + $fRaw * $w2 / 100, 2)`.
- [ ] **Step 4:** → PASS.
- [ ] **Step 5:** `git commit -am "feat(results): pure tabulator"` (add the new files first).

### Task 3: EventResults loader

**Files:** Create `app/Results/EventResults.php`; Test `tests/Feature/MultiEvent/EventResultsTest.php`

**Interfaces:**
- Consumes: Task 1 models, Task 2 `Tabulator`.
- Produces: `new EventResults(Event $event)` with
  - `judges(): array` → `[['id'=>int,'name'=>string]]` ordered by id.
  - `category(Category $category): array` → `['category'=>['id','name','max_score','round'], 'judges'=>…, 'groups'=>[['id','name','rows'=>CategoryRow[]]]]`.
  - `round(int $round): array` → `['categories'=>[['id','name','max_score']], 'judges'=>…, 'groups'=>[['id','name','rows'=>RoundRow[]]]]`.
  - `standings(): array` → `['mode'=>'single'|'finals'|'weighted', 'weights'=>[int,int]|null, 'groups'=>[['id','name','rows'=>StandingRow[]]]]`.
  - `candidate` payload in every row: `['id','candidate_number','first_name','last_name','course','profile_img']`.
- Population: round 1 / round-1 categories = the group's candidates ordered by `candidate_number`, then `id`; round 2 / round-2 categories = the group's finalists in `finalists.id` order (matches today's `top_five_candidates` order). Groups in `position` order. Judge sums iterate judges by id.

- [ ] **Step 1: Write the failing test** — `EventResultsTest`:
  - `test_category_results_are_split_by_group_and_use_the_events_judges_only` (judge of another event with scores is ignored; |J| = this event's judges).
  - `test_round_two_lists_only_finalists_in_the_order_they_were_set`.
  - `test_standings_mode_single_finals_and_weighted`: 1-round event → `mode` `single`; 2-round from zero → `finals` with totals equal to `round(2)`; 2-round carry-over 40/60 → `weighted` and `total` equals `Tabulator::weighted` output.
- [ ] **Step 2:** → FAIL. **Step 3:** Implement with one query per table (scores via `Score::whereIn('category_id', …)`), no per-candidate queries. **Step 4:** → PASS.
- [ ] **Step 5:** `git commit -m "feat(results): event results loader"`

### Task 4: Frozen legacy formulas and snapshot

**Files:** Create `app/Legacy/LegacyResults.php`, `app/Legacy/Snapshot.php`; Test `tests/Feature/MultiEvent/LegacyResultsTest.php`

**Interfaces — Produces:**
- `LegacyResults::CATEGORY_MAP` — `legacyKey => ['round'=>int,'name'=>string,'max'=>int,'position'=>int]` for the 8 keys (`production_number`, `casual_wear`, `swim_wear`, `formal_wear`, `closed_door_interview`, `face_and_figure`, `delivery`, `overall_appeal`) with the Global Constraints names/max/order.
- `LegacyResults::snapshot(): array` — reads `candidates`, `users`, `top_five_selection_scores`, `top_five_scores`, `top_five_candidates` with `DB::table` only (must survive deletion of the old models/services in Task 19), reproducing today's services exactly (candidates by id grouped by `gender`; judges = `role=judge` by id; finalists by `top_five_candidates.id`).
- Snapshot shape (both legacy and new): `['category:{legacyKey}' => G, 'round1' => G, 'finals' => G]`, `G = ['female'=>Row[], 'male'=>Row[]]`, `Row = ['candidate_id'=>int, 'scores'=>array<string|int, float>, 'total'=>float, 'rank'=>int]`; category rows keyed by judge id, round rows keyed by legacy key; every number cast to `float`.
- `Snapshot::normalize(array $snapshot): array` — within each run of equal `rank`, sorts rows by `candidate_id` (tie order is display-only, see Review Focus 5).
- `Snapshot::diff(array $legacy, array $new): array` — list of human-readable differences (`"round1.female[2].total: 81.25 vs 81.24"`); empty = identical.

- [ ] **Step 1: Write the failing test** — seed old tables with: 2 female + 2 male candidates, 3 judges, decimals (17.5, 22.25), one missing cell, a tie, 2 finalists with finals scores. `test_legacy_snapshot_matches_todays_services`: for each of the 8 categories compare `LegacyResults::snapshot()['category:…']` to the normalized output of `TopFiveSelectionService::getResultsPerCategory()` / `TopFiveService::getResultsPerCategory()`; compare `round1` to `getTopFiveSelectionResults()` and `finals` to `getTotalResults()` (map their rows to the Row shape with the same helper). `test_diff_reports_a_changed_total`.
- [ ] **Step 2:** → FAIL. **Step 3:** Implement (copy the arithmetic from the current services verbatim; only the data access changes). **Step 4:** → PASS.
- [ ] **Step 5:** `git commit -m "feat(legacy): frozen legacy results and snapshot diff"`

### Task 5: Importer and `events:migrate-legacy`

**Files:** Create `app/Legacy/LegacyImporter.php`, `app/Console/Commands/MigrateLegacyEvent.php`; Test `tests/Feature/MultiEvent/MigrateLegacyEventTest.php`

**Interfaces:**
- Consumes: Task 3 `EventResults`, Task 4 `LegacyResults`, `Snapshot`.
- Produces: `LegacyImporter::import(): Event` (Event #1 per Global Constraints; groups from `gender`; judges get `event_id` and `username` `piton-judge{n}` by id order; finalists copied in `top_five_candidates.id` order with `finalists_set_at = now()` if any; one `scores` row per non-null legacy cell, copied in legacy row-id order); `LegacyImporter::newSnapshot(Event $event): array` (maps Event #1 back to the snapshot shape via `CATEGORY_MAP`, bound in the container so tests can swap it).
- Command `events:migrate-legacy`: refuses (FAILURE, message "Event #1 already exists.") if any event exists; runs `db:backup` and stops on failure; `$legacy = Snapshot::normalize(LegacyResults::snapshot())`; in `DB::transaction` imports, builds the new snapshot, throws on non-empty `diff` (rollback); prints each difference; on success prints "Event #1 migrated: N candidates, N judges, N scores — results identical." and bumps nothing else.

- [ ] **Step 1: Write the failing test** (tearDown deletes files the test created in `database/backups`):
  - `test_migrates_messy_legacy_data_with_identical_results` — same seed style as Task 4 plus a cutoff tie; command succeeds; `Event::count()===1`; `Score::count()` equals non-null legacy cells; `Snapshot::diff(legacy, new)` is `[]`.
  - `test_refuses_to_run_twice`.
  - `test_a_mismatch_rolls_everything_back` — bind a fake `newSnapshot` that changes one total; command fails, output contains the difference, `Event::count()===0`, `Score::count()===0`, `users.event_id` all null.
  - `test_tied_rows_stored_in_a_different_order_still_match` (Review Focus 5) — legacy ids order the tied pair opposite to candidate numbers; command succeeds.
- [ ] **Step 2:** → FAIL. **Step 3:** Implement. **Step 4:** → PASS.
- [ ] **Step 5:** Agent doc: add "Legacy migration" section (command, guarantees, run between events). `git commit -m "feat(legacy): events:migrate-legacy with equivalence check"`

---

## Phase 2 — Judges score through the new model

### Task 6: Per-event live stamps and feeds

**Files:** Modify `app/Support/LiveVersions.php`, `EventFeed.php`, `JudgeCallFeed.php`, `ScoreSubmissionFeed.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/lib/liveVersions.js`; Test `tests/Feature/MultiEvent/LiveVersionsTest.php`

**Interfaces — Produces:**
- `LiveVersions::bump(int $eventId, string ...$topics): void`, `LiveVersions::all(int $eventId): array` with topics `EVENT='event'`, `FINALISTS='finalists'`, `SCORES='scores'`; keys `live-version:{eventId}:{topic}`.
- `JudgeCallFeed::push(int $eventId, string $sender, ?int $categoryId, ?string $message, ?array $judgeIds)`, `forJudge(User $judge)`, `recent(int $eventId, int $count = 10)`; feed key `judge-call-feed:{eventId}`; events carry `category_id`, `label`, `url` (`route('score.show', $categoryId)`).
- `ScoreSubmissionFeed::push(Category $category, User $judge, array $candidateIds)`, `since(int $eventId, ?int $after)`; key `score-submission-feed:{eventId}`.
- Shared prop `live` = stamps of the judge's event, or of `AdminEventContext::current()` for admins (Task 10 provides it; until then admins get `null`). JS `JUDGE_TOPICS = ["event","finalists"]`, `ADMIN_TOPICS = ["event","finalists","scores"]`.

- [ ] **Step 1:** Test `test_bumping_one_event_does_not_change_another`, `test_judge_feed_only_returns_calls_for_the_judges_event`.
- [ ] **Step 2:** → FAIL. **Step 3:** Implement; old call sites keep working by passing Event #1's id only where they still exist (they're removed in Task 19). **Step 4:** → PASS (full suite).
- [ ] **Step 5:** Agent doc live-update section. Commit.

### Task 7: Server-built navigation

**Files:** Create `app/Support/Navigation.php`; Modify `HandleInertiaRequests.php`, `resources/js/Components/SidebarMain.jsx`; Test `tests/Feature/MultiEvent/NavigationTest.php`

**Interfaces — Produces:** `Navigation::for(?User $user, ?Event $adminEvent): array` → `['event'=>['id','name','status']|null, 'sections'=>[['label'=>string,'items'=>[['label','href','icon']]]]]`, shared as `nav`. Judge sections: `"Top {N} Selection"` (round 1; `"Categories"` for 1-round events) and `"Top {N} Finalist"` (round 2, only when `finalistsSet()`), items `route('score.show', $category)`, icon key `"category"`. Admin sections come in Task 10/13. `SidebarMain` renders `nav.sections` (lucide icon map: `category`→`ListChecks`, `trophy`→`Trophy`, `events`→`CalendarDays`, `bell`→`BellRing`, `users`→`Users`); tab title from the active item.

- [ ] **Step 1:** Tests: judge of a live 2-round event without finalists sees one section with round-1 items in position order; after finalists, two sections; 1-round event label `Categories`; judge of another event sees nothing of this one.
- [ ] **Step 2–4:** FAIL → implement (admin still gets the legacy hard-coded items until Task 10) → PASS; `npm run build`.
- [ ] **Step 5:** Commit.

### Task 8: Judge scoring endpoint

**Files:** Create `app/Http/Controllers/Judge/ScoringController.php`; Modify `routes/web.php`; Test `tests/Feature/MultiEvent/JudgeScoringTest.php`

**Interfaces — Produces:** routes `GET /score/{category}` `score.show` → Inertia `Judge/Score` with props `event` (`id,name`), `category` (`id,name,max_score,round`), `groups` (`[{id,name,candidates:[{id,candidate_number,first_name,last_name,course,profile_img,existing_score:float|null}]}]`), `roundClosed` (bool: round 1 and finalists set); `POST /score/{category}` `score.store` body `scores: {candidateId: number}`. Store bumps `LiveVersions::bump($eventId, 'scores')` and `ScoreSubmissionFeed::push`.

- [ ] **Step 1:** Tests (each asserts no `Score` written on failure):
  - `test_judge_saves_scores_for_their_live_event` (writes under logged-in id even if `judge_id` sent).
  - `test_event_not_live_is_rejected` → `assertSessionHasErrors(['scores' => "This event isn't running right now."])` (Review Focus 1).
  - `test_category_of_another_event_is_404`.
  - `test_candidate_of_another_event_or_non_finalist_in_round_two_is_rejected` → error on `scores`.
  - `test_score_above_max_or_negative_is_rejected` (max from `category.max_score`, e.g. 25.5 > 25).
  - `test_round_one_locked_after_finalists` → error "The Top 3 finalists have been set, so Top 3 Selection scores can no longer be changed." (N from the event).
  - `test_admins_get_403`.
  - `test_show_lists_groups_in_order_with_existing_scores_and_round_two_only_finalists`.
- [ ] **Step 2–4:** FAIL → implement (validate with `scores.*` `numeric|min:0|max:{max}` then the event checks; upsert via one query + transaction like today's repositories) → PASS.
- [ ] **Step 5:** Agent doc scoring rules. Commit.

### Task 9: Generic judge page and landing states

**Files:** Create `resources/js/Pages/Judge/Score.jsx`, `resources/js/Pages/Judge/Waiting.jsx`, `app/Http/Controllers/Judge/HomeController.php`; Modify `routes/web.php` (`dashboard` route → `HomeController`), `resources/js/Pages/Categories/Partials/{CandidateGrid,ScoreAlertDialog}.jsx` (accept a `maxScore` and use them from `Score.jsx`), `resources/js/lib/scoreDrafts.js` (key `score-draft:cat{categoryId}:{judgeId}`); Test `tests/Feature/MultiEvent/JudgeHomeTest.php`

**Interfaces:** `GET /dashboard`: admin → existing `Dashboard`; judge with live event → redirect to first category in nav order; judge with `setup` event → `Judge/Waiting` with `state: 'not_started'`; `closed` → `state: 'ended'`. `Waiting.jsx` polls via the existing `JudgeNotifications` live check (event topic) so it reloads when the event starts. `Score.jsx` = today's page behavior (tabs per group, drafts, review dialog, `alreadySubmitted` lock, `RoundClosedNotice` when `roundClosed`, `onError` toasts `errors.scores`) driven by props.

- [ ] **Step 1:** Tests for the three landing outcomes.
- [ ] **Step 2–4:** FAIL → implement → PASS; `npm run build` succeeds.
- [ ] **Step 5:** Manual check (record in the commit message body): log in as a judge on a migrated copy, score one category, see the tab lock. Commit.

---

## Phase 3 — Admin results

### Task 10: Admin context, results pages, admin-only routes

**Files:** Create `app/Support/AdminEventContext.php`, `app/Http/Controllers/Admin/ResultsController.php`, `resources/js/Pages/Admin/Results/{Category,Round,Standings}.jsx`; Modify `Navigation.php`, `routes/web.php`, `resources/js/Pages/Admin/Partials/{TopFiveSelectionTable,PrintButton,ResultTable}.jsx`; Test `tests/Feature/MultiEvent/AdminResultsTest.php`

**Interfaces — Produces:**
- `AdminEventContext::current(Request $request): ?Event` — route `{event}` (or `{category}`'s event) → stored in session `admin_event_id`; else session; else most recently started live event; else latest event.
- Routes (middleware `auth, verified, admin`): `GET /admin/events/{event}/results/categories/{category}` `admin.results.category` (404 if category not in event), `GET /admin/events/{event}/results/round1` `admin.results.round1`, `GET /admin/events/{event}/results/standings` `admin.results.standings`.
- Pages receive `event` + the matching `EventResults` array. `TopFiveSelectionTable` takes `categories: [{id,name,max_score}]` (drop `CATEGORY_LABELS`/`CATEGORY_MAX`); `PrintButton` adds `event.name` to the report title.
- Admin nav: event picker (`nav.event`, list `nav.events` of `[{id,name,status}]`), sections "Top N Selection" (round-1 categories + "Top N Selection Results"), "Top N Finalist" (round-2 categories + "Final Standings"; 1-round events: categories + "Final Standings"), "Management" (Events, Notify Judges).

- [ ] **Step 1:** Tests: admin sees Event #1 category page with the same totals as `LegacyResults` for that category (seed via importer); judges get 403 on all three routes; category of another event 404; standings `mode` matches event settings.
- [ ] **Step 2–4:** FAIL → implement → PASS; build.
- [ ] **Step 5:** Agent doc (admin pages, admin-only results). Commit.

### Task 11: Set Top N per event

**Files:** Create `app/Http/Controllers/Admin/FinalistController.php`; Modify `resources/js/Pages/Admin/Results/Round.jsx` (reuse `TieBreakDialog`, `ConfirmFinalistsDialog` with `FINALIST_COUNT` from `event.finalists_per_group`); Test `tests/Feature/MultiEvent/SetFinalistsTest.php`

**Interfaces:** `POST /admin/events/{event}/finalists` `admin.finalists.set` body `candidate_ids[]`, `password`. Requires 2-round event, exactly `finalists_per_group` per group (message "Select exactly 3 per group." with the event's N), candidates of this event; keeps existing finalists who stay; sets `finalists_set_at`; bumps `finalists`+`scores`.

- [ ] **Step 1:** Tests: wrong/missing password; wrong count per group (3 groups, N=2); candidate of another event; 1-round event 422; success sets lock and stamps; resaving keeps finals scores of finalists who stay.
- [ ] **Step 2–5:** FAIL → implement → PASS → build → commit.

### Task 12: Notify Judges and submission toasts per event

**Files:** Create `app/Http/Controllers/Admin/{NotifyController,ScoreSubmissionController}.php`; Modify `resources/js/Pages/Admin/NotifyJudges.jsx`, `resources/js/Components/{JudgeNotifications,ScoreSubmissionToasts}.jsx`, `routes/web.php`; Test `tests/Feature/MultiEvent/NotifyJudgesTest.php`

**Interfaces:** `GET|POST /admin/events/{event}/notify` (`admin.notify`, `admin.notify.send`; body `category_id` nullable in event, `message` ≤ 200, `judge_ids[]` of this event's judges); `GET /admin/events/{event}/score-submissions?after=` `admin.score_submissions` → `{seq, events, live}`; `GET /judge/notifications` → `{seq, events, live}` for the judge's event. Progress = per category, per judge count of scores; total = group candidates (round 1) or finalists (round 2).

- [ ] **Step 1:** Tests: judge of event B never receives event A's call; selecting a judge of another event is a validation error; progress counts.
- [ ] **Step 2–5:** FAIL → implement → PASS → build → commit.

---

## Phase 4 — Event management

### Task 13: Events: create, edit settings, start/close, duplicate, delete

**Files:** Create `app/Http/Controllers/Admin/EventController.php`, `app/Support/EventLocks.php`, `resources/js/Pages/Admin/Events/Index.jsx`, `resources/js/Pages/Admin/Events/Edit.jsx`, `resources/js/Pages/Admin/Events/Tabs/Settings.jsx`; Test `tests/Feature/MultiEvent/EventLifecycleTest.php`

**Interfaces — Produces:**
- `EventLocks::hasScores(Event $e): bool`, `roundHasScores(Event $e, int $round): bool`, `candidateHasScores(Candidate $c): bool`, `judgeHasScores(User $j): bool`.
- Routes (admin): `admin.events.index|store|edit|update|destroy|start|close|duplicate` per the URL list in the spec (`/admin/events`, `/admin/events/{event}/edit`, `PUT|DELETE /admin/events/{event}`, `POST /admin/events/{event}/{start|close|duplicate}`).
- Validation: `name` required ≤ 120; `code` required, `alpha_dash`, ≤ 20, unique, immutable once the event has judges; `rounds` in 1,2; `finalists_per_group` required if rounds=2, 1–50; `finals_from_zero` bool; weights integers 0–100 summing to 100 when not from zero. Locked once `hasScores`: `rounds`, `finals_from_zero`, weights; `finalists_per_group` once finalists set (error "This can't change after scoring has started.").
- Start (password): needs ≥ 1 judge, ≥ 1 group, ≥ 1 round-1 category, and ≥ 1 round-2 category if 2 rounds; error lists what's missing (Review Focus 4). Sets `live`, `started_at`; bumps `event`. Close (password): `closed`, `closed_at`; bumps `event`. Delete (password): only without scores. Duplicate: new `setup` event "Copy of {name}", code `{code}-copy` (suffix `-2`, `-3` if taken), groups and categories copied.

- [ ] **Step 1:** Tests for every rule above (one test per rule; include "starting B leaves A live").
- [ ] **Step 2–4:** FAIL → implement controller + Settings tab + Index (status badges, actions with password dialogs reusing the `ConfirmFinalistsDialog` password pattern) → PASS; build.
- [ ] **Step 5:** Agent doc (lifecycle, locks). Commit.

### Task 14: Groups and categories

**Files:** Create `app/Http/Controllers/Admin/{GroupController,CategoryController}.php`, `resources/js/Pages/Admin/Events/Tabs/{Groups,Categories}.jsx`; Test `tests/Feature/MultiEvent/GroupsAndCategoriesTest.php`

**Interfaces:** `POST /admin/events/{event}/groups` `admin.groups.store`, `PUT|DELETE /admin/groups/{group}` (`name` required ≤ 60 unique in event, `position` int); `POST /admin/events/{event}/categories` `admin.categories.store`, `PUT|DELETE /admin/categories/{category}` (`name` ≤ 80, `round` ≤ event rounds, `max_score` numeric 0.01–999.99, `position`). Locks: delete group with candidates; delete category / change `max_score` / change `round` once it has scores; add category to a round with scores. All bump `event`. Categories tab shows each round's max total.

- [ ] **Step 1:** Tests per rule incl. duplicate group name → field error (Review Focus 3), round 2 category on a 1-round event rejected.
- [ ] **Step 2–5:** FAIL → implement → PASS → build → commit.

### Task 15: Candidates with photo upload (server)

**Files:** Create `app/Http/Controllers/Admin/CandidateController.php`; Modify `resources/js/Components/CandidatePhoto.jsx` (also map `uploads/candidates/{event}/{file}.jpg` → `{file}.webp` / `{file}-thumb.webp`); Test `tests/Feature/MultiEvent/CandidatesTest.php`

**Interfaces:** `POST /admin/events/{event}/candidates` `admin.candidates.store` and `POST /admin/candidates/{candidate}` (`_method=PUT`) `admin.candidates.update` multipart: `candidate_number` int ≥ 1 unique in the group, `first_name`, `last_name` ≤ 80, `course` ≤ 160 nullable, `group_id` in event, `photo` (jpeg ≤ 5 MB), `photo_card` (webp ≤ 1 MB), `photo_thumb` (webp ≤ 200 KB) — the three are required together on create and optional on update. Stored as `public/uploads/candidates/{event}/{uuid}.jpg|.webp|-thumb.webp`; `profile_img` = `uploads/candidates/{event}/{uuid}.jpg`; old upload files deleted on replace/delete (never files under `public/candidates/`). `DELETE /admin/candidates/{candidate}` blocked if scored; `group_id` change blocked if scored. Bumps `event`.

- [ ] **Step 1:** Tests (use `UploadedFile::fake()->image(...)` and a temp public path via `config(['filesystems.disks.uploads.root' => …])` with a disk named `uploads` rooted at `public_path('uploads')`): create stores 3 files and sets `profile_img`; PDF / > 5 MB rejected (Review Focus 2); duplicate number → field error (Review Focus 3); scored candidate delete/regroup blocked; legacy photo files never deleted.
- [ ] **Step 2–5:** FAIL → implement → PASS → commit.

### Task 16: Candidates tab and browser photo resizing

**Files:** Create `resources/js/lib/photoResize.js`, `resources/js/Pages/Admin/Events/Tabs/Candidates.jsx`

**Interfaces:** `resizePhoto(file: File): Promise<{photo: Blob, card: Blob, thumb: Blob}>` — decode via `createImageBitmap`; `photo` JPEG q0.82 fit inside 1365×2048; `card` WebP q0.75 width 480; `thumb` WebP q0.70 96×96 center crop; rejects with `Error("This photo format isn't supported. Use a JPG or PNG.")` when decoding fails (HEIC, Review Focus 2). Tab: table (number, photo thumb, name, course, group, actions), add/edit modal with photo preview, delete with confirm; `useForm` with `forceFormData`.

- [ ] **Step 1:** `npm run build` passes. **Step 2:** Manual check: add a candidate with a PNG, see the card image on the judge page; try a `.heic` → message shown, nothing uploaded. **Step 3:** Commit.

---

## Phase 5 — Per-event judges, login, legacy removal

### Task 17: Judge accounts per event

**Files:** Create `app/Support/JudgeAccounts.php`, `app/Http/Controllers/Admin/EventJudgeController.php`, `resources/js/Pages/Admin/Events/Tabs/Judges.jsx`, `resources/js/Pages/Admin/Events/JudgeSlips.jsx`; Modify `app/Http/Controllers/ProfileController.php`/`Auth/PasswordController.php` (clear `password_plain_encrypted` on self-change); Remove: old `JudgeController`, `Admin/Judges/Index.jsx`, `admin.judges.*` routes; Test `tests/Feature/MultiEvent/EventJudgesTest.php`

**Interfaces:**
- `JudgeAccounts::password(): string` (8 chars, alphabet from Global Constraints, `random_int`); `JudgeAccounts::create(Event $event, int $count): Collection` (names `Judge {n}`, n continues after the event's highest; username `{code}-judge{n}`; email `null`-safe: users.email stays required → use `{username}@judges.local`; `email_verified_at` now; `role` judge; stores `Hash` + `Crypt::encryptString`).
- Routes (admin): `POST /admin/events/{event}/judges` `admin.event-judges.store` (`count` 1–30); `PUT /admin/judges/{judge}` `admin.event-judges.update` (`name`); `POST /admin/judges/{judge}/reset-password` `admin.event-judges.reset`; `DELETE /admin/judges/{judge}` `admin.event-judges.destroy` (password; blocked if `judgeHasScores`); `GET /admin/events/{event}/judges/slips` `admin.event-judges.slips`. Edit page props include judges `[{id,name,username,password (decrypted or null),score_count}]`. All bump `event`.

- [ ] **Step 1:** Tests: creating 5 makes `pageant26-judge1..5`, continuing numbering after deletion; passwords match the alphabet and length; reveal returns the plaintext that logs in; reset changes it; delete blocked with scores, allowed without (password required); judge of event B can't open event A's scoring page (404); self password change clears the encrypted copy.
- [ ] **Step 2–5:** FAIL → implement → PASS → build → agent doc → commit.

### Task 18: Username-or-email login

**Files:** Modify `app/Http/Requests/Auth/LoginRequest.php`, `resources/js/Pages/Auth/Login.jsx`, `tests/Feature/Auth/AuthenticationTest.php`

**Interfaces:** field `login` (required string ≤ 255) — contains `@` → attempt with `email`, else `username`; errors and throttling keyed on `login`; label "Username or email", `type="text"`, `autoComplete="username"`, `inputMode` removed.

- [ ] **Step 1:** Tests: login by username; login by email; wrong password error on `login`; 5 failures throttle.
- [ ] **Step 2–5:** FAIL → implement → PASS → build → commit.

### Task 19: Remove the legacy code paths

**Files:** Remove: `app/Support/Criteria.php`, `app/Services/*`, `app/Repositories/*`, controllers `CandidateController` (root), `TopFive*Controller`, `ResultController/*`, `JudgeNotificationController`, root `ScoreSubmissionController`, `RegisteredUserController` + `register` routes + `Pages/Auth/Register.jsx`, models `TopFive*`, pages `Pages/Categories/*` (keep the `Partials` used by `Judge/Score.jsx` by moving them to `Pages/Judge/Partials/`), `Pages/Admin/*Result.jsx`, `Pages/Admin/TopFiveCategories/*`, `Pages/Admin/TopFiveSelectionResult.jsx`; their routes; tests that only covered removed code (replace any still-relevant assertion with a MultiEvent test). Modify `database/seeders/DatabaseSeeder.php` + `CandidateSeeder.php` to build Event #1 (same data, via models, groups Female/Male, categories per Global Constraints, 5 judges `piton-judge1..5` password `123`).

- [ ] **Step 1:** `grep -rn "Criteria::\|TopFiveSelectionScore\|TopFiveScore\|TopFiveCandidates\|top_five" app resources routes tests database/seeders` → only `app/Legacy/*`, the old migrations and `MigrateLegacyEventTest` remain.
- [ ] **Step 2:** Add `test_seeder_builds_event_one` in `tests/Feature/MultiEvent/SeederTest.php` (runs `DatabaseSeeder` on the in-memory DB; asserts 1 event, 2 groups, 8 categories with the Global Constraints names/max/order, 22 candidates, 5 judges). Never run `migrate:fresh` or `db:seed` against the real database.
- [ ] **Step 3:** `php artisan config:clear && php artisan test` → all green (registration test gone); `npm run build`.
- [ ] **Step 4:** Agent doc rewrite of "Domain model"/"Frontend map" for the new structure. Commit.

### Task 20: Drop-legacy-tables migration (gated)

**Files:** Create `database/migrations/2026_10_20_000001_drop_legacy_score_tables.php` **inside `database/migrations/pending/`** (not auto-run).

- [ ] **Step 1:** The migration drops `top_five_scores`, `top_five_selection_scores`, `top_five_candidates` and `candidates.gender`; `down()` throws ("restore from a backup"). Document in the agent file: move it into `database/migrations/` only after the user's go-ahead and a fresh `db:backup`.
- [ ] **Step 2:** Commit.

### Task 21: Upgrade the real database (with the user)

**Files:** none (operations). Do this only between events, with the user present.

- [ ] **Step 1:** Ask the user to confirm no event is running and judges have submitted.
- [ ] **Step 2:** `php artisan db:backup` → note the file. `php artisan migrate` (Task 1 migration only). `php artisan events:migrate-legacy` → expected "results identical".
- [ ] **Step 3:** `php artisan optimize`; open admin results for Event #1 and compare a few totals with a pre-upgrade PDF.
- [ ] **Step 4:** Update memory (`multi-event-redesign`: done) and report. Merge to `main` only when the user asks.
