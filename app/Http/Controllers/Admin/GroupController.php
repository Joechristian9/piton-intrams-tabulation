<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventGroup;
use App\Support\LiveVersions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** An event's groups (e.g. Female, Male); each is ranked separately. */
class GroupController extends Controller
{
    public function store(Request $request, Event $event)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('event_groups')->where('event_id', $event->id)],
        ]);

        EventGroup::create([
            'event_id' => $event->id,
            'name' => $data['name'],
            'position' => (int) $event->groups()->max('position') + 1,
        ]);
        LiveVersions::bump($event->id, LiveVersions::EVENT);

        return back();
    }

    public function update(Request $request, EventGroup $group)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('event_groups')->where('event_id', $group->event_id)->ignore($group->id)],
            'position' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $group->update($data);
        LiveVersions::bump($group->event_id, LiveVersions::EVENT);

        return back();
    }

    public function destroy(EventGroup $group)
    {
        if ($group->candidates()->exists()) {
            throw ValidationException::withMessages(['group' => 'Move or delete its candidates first.']);
        }

        $group->delete();
        LiveVersions::bump($group->event_id, LiveVersions::EVENT);

        return back();
    }
}
