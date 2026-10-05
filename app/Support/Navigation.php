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

        return ['event' => null, 'sections' => []];
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
