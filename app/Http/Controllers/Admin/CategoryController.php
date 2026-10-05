<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Event;
use App\Support\EventLocks;
use App\Support\LiveVersions;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * An event's scored categories per round. Once a category has scores its max
 * points and round are locked; a round with scores takes no new categories.
 */
class CategoryController extends Controller
{
    public function store(Request $request, Event $event)
    {
        $data = $this->validated($request, $event);

        if (EventLocks::roundHasScores($event, $data['round'])) {
            throw ValidationException::withMessages([
                'round' => "Round {$data['round']} already has scores, so a new category would change everyone's totals.",
            ]);
        }

        Category::create([
            ...$data,
            'event_id' => $event->id,
            'position' => (int) $event->categories()->where('round', $data['round'])->max('position') + 1,
        ]);
        LiveVersions::bump($event->id, LiveVersions::EVENT);

        return back();
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category->event, withPosition: true);

        if ($category->scores()->exists()) {
            $locked = [];
            if ((float) $data['max_score'] !== (float) $category->max_score) {
                $locked['max_score'] = EventLocks::SCORING_STARTED;
            }
            if ($data['round'] !== $category->round) {
                $locked['round'] = EventLocks::SCORING_STARTED;
            }
            if ($locked) {
                throw ValidationException::withMessages($locked);
            }
        }

        // Moving a category into a round that already has scores is the same as
        // adding one there: everyone's totals would change.
        if ($data['round'] !== $category->round && EventLocks::roundHasScores($category->event, $data['round'])) {
            throw ValidationException::withMessages([
                'round' => "Round {$data['round']} already has scores, so a new category would change everyone's totals.",
            ]);
        }

        $category->update($data);
        LiveVersions::bump($category->event_id, LiveVersions::EVENT);

        return back();
    }

    public function destroy(Category $category)
    {
        if ($category->scores()->exists()) {
            throw ValidationException::withMessages(['category' => "This category has scores, so it can't be deleted."]);
        }

        $category->delete();
        LiveVersions::bump($category->event_id, LiveVersions::EVENT);

        return back();
    }

    private function validated(Request $request, Event $event, bool $withPosition = false): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'round' => ['required', 'integer', 'min:1', 'max:' . $event->rounds],
            'max_score' => ['required', 'numeric', 'min:0.01', 'max:999.99'],
            ...($withPosition ? ['position' => ['required', 'integer', 'min:1', 'max:999']] : []),
        ]);
        $data['round'] = (int) $data['round'];

        return $data;
    }
}
