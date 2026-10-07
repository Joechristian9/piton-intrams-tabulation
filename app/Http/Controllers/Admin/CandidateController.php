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
 * An event's candidates. The browser resizes each photo into the sizes
 * CandidatePhoto.jsx uses (JPEG original; card WebP 360px + 240px; thumb WebP 96px +
 * 48px — the small ones for desktop 1x screens); they're stored under
 * public/uploads/candidates/{event}/ on the `uploads` disk.
 * Files under public/candidates/ (the original pageant) are never deleted.
 */
class CandidateController extends Controller
{
    public function store(Request $request, Event $event)
    {
        $data = $this->validated($request, $event, null);
        // No photo: an empty path, which CandidatePhoto.jsx shows as a placeholder.
        $photo = $request->hasFile('photo') ? $this->storePhotos($request, $event) : '';

        try {
            Candidate::create([...$data, 'event_id' => $event->id, 'profile_img' => $photo]);
        } catch (\Throwable $e) {
            $this->deletePhotos($photo);   // don't leave files for a candidate that wasn't saved
            throw $e;
        }
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

        $old = $candidate->profile_img;
        if ($request->hasFile('photo')) {
            $data['profile_img'] = $this->storePhotos($request, $event);
        }

        try {
            $candidate->update($data);
        } catch (\Throwable $e) {
            if (isset($data['profile_img'])) {
                $this->deletePhotos($data['profile_img']);   // keep the old photo, drop the new files
            }
            throw $e;
        }

        // Only now that the new photo is saved, remove the old files.
        if (isset($data['profile_img'])) {
            $this->deletePhotos($old);
        }
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
        $data = $request->validate([
            'group_id' => ['required', 'integer', Rule::exists('event_groups', 'id')->where('event_id', $event->id)],
            'candidate_number' => [
                'required', 'integer', 'min:1', 'max:9999',
                Rule::unique('candidates')->where('group_id', $request->input('group_id'))->ignore($candidate?->id),
            ],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'name_suffix' => ['nullable', 'string', 'max:20'],
            'course' => ['nullable', 'string', 'max:160'],
            // The photo is optional; when given, all five sizes come together.
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg', 'max:5120'],
            'photo_card' => ['nullable', 'required_with:photo', 'file', 'mimes:webp', 'max:1024'],
            'photo_card_small' => ['nullable', 'required_with:photo', 'file', 'mimes:webp', 'max:512'],
            'photo_thumb' => ['nullable', 'required_with:photo', 'file', 'mimes:webp', 'max:200'],
            'photo_thumb_small' => ['nullable', 'required_with:photo', 'file', 'mimes:webp', 'max:100'],
        ], [
            'photo.mimes' => "This photo format isn't supported. Use a JPG or PNG.",
            'photo.max' => 'The photo is too large (5 MB at most).',
        ]);

        return collect($data)->only(['group_id', 'candidate_number', 'first_name', 'last_name', 'name_suffix', 'course'])->all();
    }

    /** File name suffix of each stored size, next to "<uuid>". */
    private const SIZES = [
        'photo' => '.jpg',
        'photo_card' => '.webp',
        'photo_card_small' => '-sm.webp',
        'photo_thumb' => '-thumb.webp',
        'photo_thumb_small' => '-thumb-sm.webp',
    ];

    /** Stores every size; returns the path saved in `profile_img`. */
    private function storePhotos(Request $request, Event $event): string
    {
        $disk = Storage::disk('uploads');
        $dir = "candidates/{$event->id}";
        $stem = (string) Str::uuid();

        foreach (self::SIZES as $field => $suffix) {
            $disk->putFileAs($dir, $request->file($field), $stem . $suffix);
        }

        return "uploads/{$dir}/{$stem}.jpg";
    }

    /** Deletes an uploaded photo's files; ignores the original pageant's photos. */
    private function deletePhotos(?string $profileImg): void
    {
        if (! $profileImg || ! str_starts_with($profileImg, 'uploads/candidates/')) {
            return;
        }

        $stem = substr($profileImg, strlen('uploads/'), -strlen('.jpg'));
        Storage::disk('uploads')->delete(array_map(fn ($suffix) => $stem . $suffix, array_values(self::SIZES)));
    }
}
