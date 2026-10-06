<?php

namespace App\Results;

use App\Models\Candidate;
use App\Models\Category;
use App\Models\Event;
use App\Models\Score;

/**
 * Page-ready results for one event, computed by the Tabulator. Loads each table
 * once; only this event's judges' scores count, and averages divide by the
 * event's number of judges.
 *
 * Round 1 lists a group's candidates by number; the final round lists the
 * group's finalists in the order they were set.
 */
class EventResults
{
    private ?array $judges = null;
    private ?array $scores = null;

    public function __construct(private Event $event) {}

    /** @return array<int, array{id:int, name:string}> */
    public function judges(): array
    {
        return $this->judges ??= $this->event->judges()
            ->get(['id', 'name'])
            ->map(fn ($j) => ['id' => $j->id, 'name' => $j->name])
            ->all();
    }

    public function category(Category $category): array
    {
        $judgeIds = array_column($this->judges(), 'id');
        $scores = $this->scores();

        return [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'max_score' => (float) $category->max_score,
                'round' => $category->round,
            ],
            'judges' => $this->judges(),
            'groups' => $this->perGroup($category->round, function (array $candidates) use ($category, $judgeIds, $scores) {
                $byCandidate = [];
                foreach ($candidates as $candidate) {
                    $byCandidate[$candidate['id']] = $scores[$candidate['id']][$category->id] ?? [];
                }

                return Tabulator::category($candidates, $judgeIds, $byCandidate);
            }),
        ];
    }

    public function round(int $round): array
    {
        $categories = $this->event->categories->where('round', $round)->values();
        $categoryIds = $categories->pluck('id')->all();
        $judgeIds = array_column($this->judges(), 'id');
        $scores = $this->scores();

        return [
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name, 'max_score' => (float) $c->max_score,
            ])->all(),
            'judges' => $this->judges(),
            'groups' => $this->perGroup($round, fn (array $candidates) => Tabulator::round($candidates, $categoryIds, $judgeIds, $scores)),
        ];
    }

    public function standings(): array
    {
        if ($this->event->rounds === 1) {
            return ['mode' => 'single', 'weights' => null, 'groups' => $this->round(1)['groups']];
        }

        if ($this->event->finals_from_zero) {
            return ['mode' => 'finals', 'weights' => null, 'groups' => $this->round(2)['groups']];
        }

        $round1 = $this->round(1)['groups'];
        $finals = $this->round(2)['groups'];
        $weights = [(int) $this->event->round1_weight, (int) $this->event->finals_weight];

        $groups = [];
        foreach ($finals as $i => $group) {
            $groups[] = [
                'id' => $group['id'],
                'name' => $group['name'],
                'rows' => Tabulator::weighted($round1[$i]['rows'], $group['rows'], ...$weights),
            ];
        }

        return ['mode' => 'weighted', 'weights' => $weights, 'groups' => $groups];
    }

    /** Runs $compute for each group's population of the given round. */
    private function perGroup(int $round, callable $compute): array
    {
        $finalistOrder = $round === 2
            ? $this->event->finalists()->pluck('candidate_id')->flip()->all()
            : null;

        $candidates = $this->event->candidates()
            ->orderBy('candidate_number')->orderBy('id')
            ->get();

        $groups = [];
        foreach ($this->event->groups as $group) {
            $members = $candidates->where('group_id', $group->id);

            if ($finalistOrder !== null) {
                $members = $members
                    ->filter(fn ($c) => isset($finalistOrder[$c->id]))
                    ->sortBy(fn ($c) => $finalistOrder[$c->id]);
            }

            $groups[] = [
                'id' => $group->id,
                'name' => $group->name,
                'rows' => $compute($members->values()->map(fn ($c) => self::candidatePayload($c))->all()),
            ];
        }

        return $groups;
    }

    /** @return array<int, array<int, array<int, float>>> [candidateId][categoryId][judgeId] => score */
    private function scores(): array
    {
        if ($this->scores !== null) {
            return $this->scores;
        }

        $judgeIds = array_column($this->judges(), 'id');
        $map = [];

        Score::whereIn('category_id', $this->event->categories->pluck('id'))
            ->whereIn('judge_id', $judgeIds)
            ->orderBy('id')
            ->get(['candidate_id', 'category_id', 'judge_id', 'score'])
            ->each(function ($s) use (&$map) {
                $map[$s->candidate_id][$s->category_id][$s->judge_id] = (float) $s->score;
            });

        return $this->scores = $map;
    }

    public static function candidatePayload(Candidate $candidate): array
    {
        return [
            'id' => $candidate->id,
            'candidate_number' => $candidate->candidate_number,
            'first_name' => $candidate->first_name,
            'last_name' => $candidate->last_name,
            'name_suffix' => $candidate->name_suffix,
            'course' => $candidate->course,
            'profile_img' => $candidate->profile_img,
        ];
    }
}
