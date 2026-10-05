<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;

/**
 * Sidebar items for the current user, shared with every page as `nav`:
 * ['event' => ['id', 'name', 'status'] | null, 'sections' => [['label', 'items' => [['label', 'href', 'icon']]]]].
 * Hrefs are relative paths, so the sidebar can mark the active item by URL.
 */
class Navigation
{
    public static function for(?User $user, ?Event $adminEvent): array
    {
        if ($user?->role === 'judge' && $user->event) {
            return self::judge($user->event);
        }

        if ($user?->role === 'admin' && $adminEvent) {
            return self::admin($adminEvent);
        }

        return ['event' => null, 'sections' => []];
    }

    private static function admin(Event $event): array
    {
        $n = $event->finalists_per_group;
        $resultItems = fn (int $round) => $event->categories->where('round', $round)->values()->map(fn ($category) => [
            'label' => $category->name,
            'href' => route('admin.results.category', [$event, $category], false),
            'icon' => 'category',
        ])->all();
        $standings = ['label' => 'Final Standings', 'href' => route('admin.results.standings', $event, false), 'icon' => 'trophy'];

        $sections = $event->rounds === 1
            ? [['label' => 'Categories', 'items' => [...$resultItems(1), $standings]]]
            : [
                ['label' => "Top {$n} Selection", 'items' => [
                    ...$resultItems(1),
                    ['label' => "Top {$n} Selection Results", 'href' => route('admin.results.round1', $event, false), 'icon' => 'trophy'],
                ]],
                ['label' => "Top {$n} Finalist", 'items' => [...$resultItems(2), $standings]],
            ];

        // Judges: the old single-pageant page until per-event judges replace it.
        $sections[] = ['label' => 'Management', 'items' => [
            ['label' => 'Judges', 'href' => route('admin.judges.index', [], false), 'icon' => 'users'],
            ['label' => 'Notify Judges', 'href' => route('admin.notify', $event, false), 'icon' => 'bell'],
        ]];

        return [
            'event' => ['id' => $event->id, 'name' => $event->name, 'status' => $event->status],
            'events' => Event::orderBy('id')->get(['id', 'name', 'status', 'rounds'])
                ->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'status' => $e->status, 'rounds' => $e->rounds])->all(),
            'sections' => $sections,
        ];
    }

    private static function judge(Event $event): array
    {
        $sections = [];

        if ($event->isLive()) {
            $n = $event->finalists_per_group;
            $sections[] = [
                'label' => $event->rounds === 1 ? 'Categories' : "Top {$n} Selection",
                'items' => self::categoryItems($event, 1),
            ];

            if ($event->rounds === 2 && $event->finalistsSet()) {
                $sections[] = ['label' => "Top {$n} Finalist", 'items' => self::categoryItems($event, 2)];
            }
        }

        return [
            'event' => ['id' => $event->id, 'name' => $event->name, 'status' => $event->status],
            'sections' => $sections,
        ];
    }

    private static function categoryItems(Event $event, int $round): array
    {
        return $event->categories->where('round', $round)->values()->map(fn ($category) => [
            'label' => $category->name,
            'href' => route('score.show', $category, false),
            'icon' => 'category',
        ])->all();
    }
}
