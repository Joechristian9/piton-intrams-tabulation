<?php

namespace App\Legacy;

use App\Models\Category;
use App\Models\Event;
use App\Models\EventGroup;
use App\Models\Finalist;
use App\Models\Score;
use App\Results\EventResults;
use Illuminate\Support\Facades\DB;

/**
 * Copies the original single pageant (old column-per-category tables) into
 * Event #1. Run only through `events:migrate-legacy`, which wraps it in a
 * transaction and keeps the result only if every number matches.
 */
class LegacyImporter
{
    public function import(): Event
    {
        $event = Event::create([
            'name' => 'PITON Pageant',
            'code' => 'piton',
            'status' => Event::LIVE,
            'rounds' => 2,
            'finalists_per_group' => 3,
            'finals_from_zero' => true,
        ]);
        $event->forceFill(['started_at' => now()])->save();

        $groups = [
            'female' => EventGroup::create(['event_id' => $event->id, 'name' => 'Female', 'position' => 1])->id,
            'male' => EventGroup::create(['event_id' => $event->id, 'name' => 'Male', 'position' => 2])->id,
        ];

        $categories = [];
        foreach (LegacyResults::CATEGORY_MAP as $key => $meta) {
            $categories[$key] = Category::create([
                'event_id' => $event->id, 'round' => $meta['round'], 'name' => $meta['name'],
                'max_score' => $meta['max'], 'position' => $meta['position'],
            ])->id;
        }

        foreach ($groups as $gender => $groupId) {
            // Only the old pageant's candidates: other events' candidates already have an event.
            DB::table('candidates')->whereNull('event_id')->where('gender', $gender)->update(['event_id' => $event->id, 'group_id' => $groupId]);
        }

        DB::table('users')->where('role', 'judge')->whereNull('event_id')->orderBy('id')->pluck('id')
            ->each(fn ($id, $i) => DB::table('users')->where('id', $id)->update([
                'event_id' => $event->id,
                'username' => 'piton-judge' . ($i + 1),
            ]));

        $finalistCandidates = DB::table('top_five_candidates')->orderBy('id')->pluck('candidate_id', 'id');
        foreach ($finalistCandidates as $candidateId) {
            Finalist::create(['event_id' => $event->id, 'candidate_id' => $candidateId]);
        }
        if ($finalistCandidates->isNotEmpty()) {
            $event->forceFill(['finalists_set_at' => now()])->save();
        }

        $rows = [];
        $now = now();
        $add = function ($key, $candidateId, $judgeId, $value) use (&$rows, $categories, $now) {
            if ($value !== null) {
                $rows[] = [
                    'category_id' => $categories[$key], 'candidate_id' => $candidateId, 'judge_id' => $judgeId,
                    'score' => $value, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        };

        foreach (DB::table('top_five_selection_scores')->orderBy('id')->get() as $row) {
            foreach (LegacyResults::CATEGORY_MAP as $key => $meta) {
                if ($meta['round'] === 1) {
                    $add($key, $row->candidate_id, $row->judge_id, $row->{$key});
                }
            }
        }
        foreach (DB::table('top_five_scores')->orderBy('id')->get() as $row) {
            foreach (LegacyResults::CATEGORY_MAP as $key => $meta) {
                if ($meta['round'] === 2) {
                    $add($key, $finalistCandidates[$row->top_five_id], $row->judge_id, $row->{$key});
                }
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            Score::insert($chunk);
        }

        return $event->fresh();
    }

    /** Event #1's results in LegacyResults' snapshot shape, for comparison. */
    public function newSnapshot(Event $event): array
    {
        $results = new EventResults($event);
        $keyByCategory = [];
        foreach ($event->categories as $category) {
            foreach (LegacyResults::CATEGORY_MAP as $key => $meta) {
                if ($meta['round'] === $category->round && $meta['name'] === $category->name) {
                    $keyByCategory[$category->id] = $key;
                }
            }
        }

        $shape = function (array $groups, bool $scoresByCategory) use ($keyByCategory) {
            $out = [];
            foreach ($groups as $group) {
                $out[strtolower($group['name'])] = array_map(function ($row) use ($scoresByCategory, $keyByCategory) {
                    $scores = [];
                    foreach ($row['scores'] as $id => $value) {
                        $scores[$scoresByCategory ? $keyByCategory[$id] : $id] = (float) $value;
                    }

                    return [
                        'candidate_id' => $row['candidate']['id'],
                        'scores' => $scores,
                        'total' => (float) $row['total'],
                        'rank' => $row['rank'],
                    ];
                }, $group['rows']);
            }

            return $out;
        };

        $snapshot = [];
        foreach ($event->categories as $category) {
            $snapshot['category:' . $keyByCategory[$category->id]] = $shape($results->category($category)['groups'], false);
        }
        $snapshot['round1'] = $shape($results->round(1)['groups'], true);
        $snapshot['finals'] = $shape($results->round(2)['groups'], true);

        return $snapshot;
    }
}
