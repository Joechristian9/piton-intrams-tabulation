<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;

/**
 * Sidebar items for the current user, shared with every page as `nav`:
 * ['event' => ['id', 'name', 'status'] | null, 'sections' => [['label', 'items' => [['label', 'href', 'icon']]]]].
 * Category items also carry `iconKey`: the icon the admin picked, or null (picked from the name).
 * Hrefs are relative paths, so the sidebar can mark the active item by URL.
 */
class Navigation
{
    public static function for(?User $user, ?Event $adminEvent): array
    {
        if ($user?->role === 'judge' && $user->event) {
            return self::judge($user->event);
        }

        if ($user?->role === 'admin') {
            return $adminEvent ? self::admin($adminEvent) : [
                'event' => null,
                'sections' => [['label' => 'Management', 'items' => [self::eventsItem()]]],
            ];
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
            'iconKey' => $category->icon,
        ])->all();
        $standings = ['label' => 'Final Standings', 'href' => route('admin.results.standings', $event, false), 'icon' => 'trophy'];

        $sections = $event->rounds === 1
            ? [['label' => 'Categories', 'items' => [...$resultItems(1), $standings]]]
            : [
                ['label' => "Top {$n} Selection", 'items' => [
                    ...$resultItems(1),
                    ['label' => "Top {$n} Selection Results", 'href' => route('admin.results.round1', $event, false), 'icon' => 'medal'],
                ]],
                ['label' => "Top {$n} Finalist", 'items' => [...$resultItems(2), $standings]],
            ];

        // Judges are managed inside each event (Events → Set up → Judges).
        $sections[] = ['label' => 'Management', 'items' => [
            self::eventsItem(),
            ['label' => 'Notify Judges', 'href' => route('admin.notify', $event, false), 'icon' => 'bell'],
        ]];

        return [
            'event' => ['id' => $event->id, 'name' => $event->name, 'status' => $event->status],
            'sections' => $sections,
        ];
    }

    private static function eventsItem(): array
    {
        return ['label' => 'Events', 'href' => route('admin.events.index', [], false), 'icon' => 'events'];
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
            'iconKey' => $category->icon,
        ])->all();
    }
}
