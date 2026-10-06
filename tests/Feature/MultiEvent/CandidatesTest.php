<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Candidate;
use App\Models\Event;
use App\Models\User;
use App\Support\LiveVersions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CandidatesTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private User $admin;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->event = $this->makeEvent();
    }

    /** A real copy of a fixture image as an upload (PHP here can't generate WebP). */
    private function fixture(string $name, string $mime): UploadedFile
    {
        $copy = tempnam(sys_get_temp_dir(), 'up');
        copy(base_path("tests/fixtures/{$name}"), $copy);

        return new UploadedFile($copy, $name, $mime, null, true);
    }

    private function photos(): array
    {
        return [
            'photo' => $this->fixture('photo.jpg', 'image/jpeg'),
            'photo_card' => $this->fixture('card.webp', 'image/webp'),
            'photo_thumb' => $this->fixture('thumb.webp', 'image/webp'),
        ];
    }

    private function fields(array $overrides = []): array
    {
        return [
            'candidate_number' => 1, 'first_name' => 'Ana', 'last_name' => 'Reyes', 'course' => 'BSIT',
            'group_id' => $this->group($this->event, 'Female')->id, ...$overrides,
        ];
    }

    private function create(array $overrides = [], ?array $photos = null)
    {
        return $this->actingAs($this->admin)->post(route('admin.candidates.store', $this->event), [
            ...$this->fields($overrides), ...($photos ?? $this->photos()),
        ]);
    }

    public function test_create_stores_the_three_photo_sizes(): void
    {
        $before = LiveVersions::all($this->event->id)['event'];

        $this->create()->assertSessionHasNoErrors();

        $candidate = Candidate::sole();
        $this->assertSame([1, 'Ana', 'Reyes', 'BSIT', $this->event->id], [
            $candidate->candidate_number, $candidate->first_name, $candidate->last_name, $candidate->course, $candidate->event_id,
        ]);
        $this->assertMatchesRegularExpression("#^uploads/candidates/{$this->event->id}/[0-9a-f-]{36}\\.jpg$#", $candidate->profile_img);
        $stem = substr($candidate->profile_img, strlen('uploads/'), -4);
        Storage::disk('uploads')->assertExists(["{$stem}.jpg", "{$stem}.webp", "{$stem}-thumb.webp"]);
        $this->assertNotSame($before, LiveVersions::all($this->event->id)['event']);
    }

    /** Review Focus 2: unsupported or huge files are rejected, never stored. */
    public function test_unsupported_or_oversized_photos_are_rejected(): void
    {
        $this->create([], [...$this->photos(), 'photo' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf')])
            ->assertSessionHasErrors('photo');
        $this->create([], [...$this->photos(), 'photo' => UploadedFile::fake()->create('huge.jpg', 6000, 'image/jpeg')])
            ->assertSessionHasErrors('photo');
        $this->create([], ['photo' => $this->fixture('photo.jpg', 'image/jpeg')])
            ->assertSessionHasErrors(['photo_card', 'photo_thumb']);

        $this->assertSame(0, Candidate::count());
        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    /** Review Focus 3: duplicate numbers are a field error per group. */
    public function test_candidate_numbers_are_unique_within_a_group(): void
    {
        $this->create()->assertSessionHasNoErrors();

        $this->create(['first_name' => 'Bea'])->assertSessionHasErrors('candidate_number');
        $this->create(['group_id' => $this->group($this->event, 'Male')->id])->assertSessionHasNoErrors();
        $this->assertSame(2, Candidate::count());
    }

    public function test_group_must_belong_to_the_event(): void
    {
        $other = $this->makeEvent();

        $this->create(['group_id' => $this->group($other, 'Female')->id])->assertSessionHasErrors('group_id');
    }

    public function test_update_without_photos_keeps_them_and_replacing_deletes_the_old_files(): void
    {
        $this->create();
        $candidate = Candidate::sole();
        $old = substr($candidate->profile_img, strlen('uploads/'), -4);

        $this->actingAs($this->admin)->post(route('admin.candidates.update', $candidate), ['_method' => 'PUT', ...$this->fields(['last_name' => 'Cruz'])])
            ->assertSessionHasNoErrors();
        $this->assertSame('Cruz', $candidate->fresh()->last_name);
        Storage::disk('uploads')->assertExists("{$old}.jpg");

        $this->actingAs($this->admin)->post(route('admin.candidates.update', $candidate), ['_method' => 'PUT', ...$this->fields(), ...$this->photos()])
            ->assertSessionHasNoErrors();
        Storage::disk('uploads')->assertMissing(["{$old}.jpg", "{$old}.webp", "{$old}-thumb.webp"]);
        $this->assertNotSame("uploads/{$old}.jpg", $candidate->fresh()->profile_img);
    }

    public function test_a_failed_update_keeps_the_old_photo_and_leaves_no_new_files(): void
    {
        $this->create();
        $candidate = Candidate::sole();
        $old = substr($candidate->profile_img, strlen('uploads/'), -4);
        Candidate::updating(fn () => throw new \RuntimeException('database hiccup'));

        $this->actingAs($this->admin)->post(route('admin.candidates.update', $candidate), ['_method' => 'PUT', ...$this->fields(), ...$this->photos()])
            ->assertStatus(500);

        Storage::disk('uploads')->assertExists(["{$old}.jpg", "{$old}.webp", "{$old}-thumb.webp"]);
        $this->assertCount(3, Storage::disk('uploads')->allFiles());
    }

    public function test_a_failed_create_leaves_no_files(): void
    {
        Candidate::creating(fn () => throw new \RuntimeException('database hiccup'));

        $this->create()->assertStatus(500);

        $this->assertSame([], Storage::disk('uploads')->allFiles());
    }

    public function test_scored_candidate_cannot_be_deleted_or_regrouped(): void
    {
        $candidate = $this->addCandidate($this->event, 'Female', 1);
        $judge = $this->addJudges($this->event, 1)->first();
        $this->score($this->category($this->event, 'Sports Wear'), $candidate, $judge, 20);

        $this->actingAs($this->admin)->delete(route('admin.candidates.destroy', $candidate))
            ->assertSessionHasErrors(['candidate' => "This candidate has scores, so they can't be deleted."]);
        $this->actingAs($this->admin)->post(route('admin.candidates.update', $candidate), [
            '_method' => 'PUT', ...$this->fields(['group_id' => $this->group($this->event, 'Male')->id]),
        ])->assertSessionHasErrors('group_id');

        $this->assertModelExists($candidate);
    }

    public function test_deleting_a_candidate_never_touches_the_original_photo_folder(): void
    {
        $this->assertTrue(File::exists(public_path('candidates/female/1.JPEG')));
        $candidate = $this->addCandidate($this->event, 'Female', 1, ['profile_img' => 'candidates/female/1.JPEG']);

        $this->actingAs($this->admin)->delete(route('admin.candidates.destroy', $candidate))->assertSessionHasNoErrors();

        $this->assertModelMissing($candidate);
        $this->assertTrue(File::exists(public_path('candidates/female/1.JPEG')));
    }

    public function test_judges_cannot_manage_candidates(): void
    {
        $judge = $this->addJudges($this->event, 1)->first();

        $this->actingAs($judge)->post(route('admin.candidates.store', $this->event), [...$this->fields(), ...$this->photos()])
            ->assertForbidden();
        $this->assertSame(0, Candidate::count());
    }
}
