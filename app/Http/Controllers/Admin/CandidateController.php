<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Event;
use App\Support\EventLocks;
use App\Support\LiveVersions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * An event's candidates. The browser resizes each photo into the three sizes
 * CandidatePhoto.jsx uses (JPEG original, 480px card WebP, 96px thumb WebP);
 * they're stored under public/uploads/candidates/{event}/ on the `uploads` disk.
 * Files under public/candidates/ (the original pageant) are never deleted.
 */
class CandidateController extends Controller
{
    public function store(Request $request, Event $event)
    {
        $data = $this->validated($request, $event, null);

        Candidate::create([
            ...$data,
            'event_id' => $event->id,
            'profile_img' => $this->storePhotos($request, $event),
        ]);
        LiveVersions::bump($event->id, LiveVersions::EVENT);

        return back();
    }

    public function update(Request $request, Candidate $candidate)
    {
        $event = $candidate->event;
        $data = $this->validated($request, $event, $candidate);

        if ((int) $data['group_id'] !== $candidate->group_id && EventLocks::candidateHasScores($candidate)) {
            throw ValidationException::withMessages(['group_id' => "This candidate has scores, so their group can't change."]);
        }

        if ($request->hasFile('photo')) {
            $old = $candidate->profile_img;
            $data['profile_img'] = $this->storePhotos($request, $event);
            $this->deletePhotos($old);
        }

        $candidate->update($data);
        LiveVersions::bump($event->id, LiveVersions::EVENT);

        return back();
    }

    public function destroy(Candidate $candidate)
    {
        if (EventLocks::candidateHasScores($candidate)) {
            throw ValidationException::withMessages(['candidate' => "This candidate has scores, so they can't be deleted."]);
        }

        $candidate->delete();
        $this->deletePhotos($candidate->profile_img);
        LiveVersions::bump($candidate->event_id, LiveVersions::EVENT);

        return back();
    }

    private function validated(Request $request, Event $event, ?Candidate $candidate): array
    {
        $photoRule = $candidate ? 'nullable' : 'required';

        $data = $request->validate([
            'group_id' => ['required', 'integer', Rule::exists('event_groups', 'id')->where('event_id', $event->id)],
            'candidate_number' => [
                'required', 'integer', 'min:1', 'max:9999',
                Rule::unique('candidates')->where('group_id', $request->input('group_id'))->ignore($candidate?->id),
            ],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'course' => ['nullable', 'string', 'max:160'],
            'photo' => [$photoRule, 'file', 'mimes:jpg,jpeg', 'max:5120'],
            'photo_card' => [$photoRule, 'required_with:photo', 'file', 'mimes:webp', 'max:1024'],
            'photo_thumb' => [$photoRule, 'required_with:photo', 'file', 'mimes:webp', 'max:200'],
        ], [
            'photo.mimes' => "This photo format isn't supported. Use a JPG or PNG.",
            'photo.max' => 'The photo is too large (5 MB at most).',
        ]);

        return collect($data)->only(['group_id', 'candidate_number', 'first_name', 'last_name', 'course'])->all();
    }

    /** Stores the three sizes; returns the path saved in `profile_img`. */
    private function storePhotos(Request $request, Event $event): string
    {
        $disk = Storage::disk('uploads');
        $dir = "candidates/{$event->id}";
        $stem = (string) Str::uuid();

        $disk->putFileAs($dir, $request->file('photo'), "{$stem}.jpg");
        $disk->putFileAs($dir, $request->file('photo_card'), "{$stem}.webp");
        $disk->putFileAs($dir, $request->file('photo_thumb'), "{$stem}-thumb.webp");

        return "uploads/{$dir}/{$stem}.jpg";
    }

    /** Deletes an uploaded photo's three files; ignores the original pageant's photos. */
    private function deletePhotos(?string $profileImg): void
    {
        if (! $profileImg || ! str_starts_with($profileImg, 'uploads/candidates/')) {
            return;
        }

        $stem = substr($profileImg, strlen('uploads/'), -strlen('.jpg'));
        Storage::disk('uploads')->delete(["{$stem}.jpg", "{$stem}.webp", "{$stem}-thumb.webp"]);
    }
}
