<?php

namespace Tests\Feature\MultiEvent;

use App\Models\Event;
use App\Models\User;
use App\Support\JudgeAccounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class EventJudgesTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    private User $admin;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->event = $this->makeEvent(['code' => 'pageant26']);
    }

    private function addViaAdmin(int $count)
    {
        return $this->actingAs($this->admin)->post(route('admin.event-judges.store', $this->event), ['count' => $count]);
    }

    private function plain(User $judge): string
    {
        return Crypt::decryptString($judge->fresh()->password_plain_encrypted);
    }

    public function test_admin_creates_judges_by_count(): void
    {
        $this->addViaAdmin(5)->assertSessionHasNoErrors();

        $judges = $this->event->judges()->get();
        $this->assertSame(['pageant26-judge1', 'pageant26-judge2', 'pageant26-judge3', 'pageant26-judge4', 'pageant26-judge5'], $judges->pluck('username')->all());
        $this->assertSame('Judge 1', $judges[0]->name);
        $this->assertSame('pageant26-judge1@judges.local', $judges[0]->email);
        $this->assertNotNull($judges[0]->email_verified_at);
    }

    public function test_generated_passwords_are_8_readable_characters_that_work(): void
    {
        $this->addViaAdmin(3);

        foreach ($this->event->judges()->get() as $judge) {
            $plain = $this->plain($judge);
            $this->assertMatchesRegularExpression('/^[' . preg_quote(JudgeAccounts::ALPHABET, '/') . ']{8}$/', $plain);
            $this->assertTrue(Hash::check($plain, $judge->password));
        }
    }

    public function test_count_is_validated(): void
    {
        $this->addViaAdmin(0)->assertSessionHasErrors('count');
        $this->addViaAdmin(31)->assertSessionHasErrors('count');
        $this->assertSame(0, $this->event->judges()->count());
    }

    /** New judges number on from the highest existing one; gaps aren't refilled. */
    public function test_numbering_continues_after_the_highest_judge(): void
    {
        $this->addViaAdmin(2);
        $first = User::where('username', 'pageant26-judge1')->sole();
        $this->actingAs($this->admin)->delete(route('admin.event-judges.destroy', $first), ['password' => 'password'])->assertSessionHasNoErrors();

        $this->addViaAdmin(1);

        $this->assertSame(['pageant26-judge2', 'pageant26-judge3'], $this->event->judges()->pluck('username')->all());
    }

    public function test_reset_password_makes_a_new_working_one(): void
    {
        $this->addViaAdmin(1);
        $judge = $this->event->judges()->first();
        $old = $this->plain($judge);

        $this->actingAs($this->admin)->post(route('admin.event-judges.reset', $judge))->assertSessionHasNoErrors();

        $new = $this->plain($judge);
        $this->assertNotSame($old, $new);
        $this->assertTrue(Hash::check($new, $judge->fresh()->password));
    }

    public function test_rename(): void
    {
        $this->addViaAdmin(1);
        $judge = $this->event->judges()->first();

        $this->actingAs($this->admin)->put(route('admin.event-judges.update', $judge), ['name' => 'Dr. Santos'])->assertSessionHasNoErrors();

        $this->assertSame('Dr. Santos', $judge->fresh()->name);
    }

    public function test_delete_needs_password_and_no_scores(): void
    {
        $judge = $this->addJudges($this->event, 1)->first();
        $this->score($this->category($this->event, 'Sports Wear'), $this->addCandidate($this->event, 'Female', 1), $judge, 20);

        $this->actingAs($this->admin)->delete(route('admin.event-judges.destroy', $judge), ['password' => 'password'])
            ->assertSessionHasErrors(['judge' => "This judge has scores, so they can't be deleted."]);

        $fresh = $this->addJudges($this->event, 1)->first();
        $this->actingAs($this->admin)->delete(route('admin.event-judges.destroy', $fresh), ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->delete(route('admin.event-judges.destroy', $fresh), ['password' => 'password'])->assertSessionHasNoErrors();

        $this->assertModelExists($judge);
        $this->assertModelMissing($fresh);
    }

    public function test_edit_page_lists_judges_with_their_passwords_and_score_counts(): void
    {
        $this->addViaAdmin(1);
        $judge = $this->event->judges()->first();
        $this->score($this->category($this->event, 'Sports Wear'), $this->addCandidate($this->event, 'Female', 1), $judge, 20);

        $this->actingAs($this->admin)->get(route('admin.events.edit', $this->event))
            ->assertInertia(fn (Assert $page) => $page
                ->where('judges.0.username', 'pageant26-judge1')
                ->where('judges.0.password', $this->plain($judge))
                ->where('judges.0.score_count', 1));
    }

    public function test_slips_page_lists_credentials(): void
    {
        $this->addViaAdmin(2);

        $this->actingAs($this->admin)->get(route('admin.event-judges.slips', $this->event))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Events/JudgeSlips')
                ->where('event.name', $this->event->name)
                ->has('judges', 2)
                ->where('judges.1.username', 'pageant26-judge2'));
    }

    public function test_changing_your_own_password_clears_the_stored_copy(): void
    {
        $this->addViaAdmin(1);
        $judge = $this->event->judges()->first();
        $plain = $this->plain($judge);

        $this->actingAs($judge)->put(route('password.update'), [
            'current_password' => $plain, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertSessionHasNoErrors();

        $this->assertNull($judge->fresh()->password_plain_encrypted);
    }

    public function test_judges_cannot_manage_judges(): void
    {
        $judge = $this->addJudges($this->event, 1)->first();

        $this->actingAs($judge)->post(route('admin.event-judges.store', $this->event), ['count' => 1])->assertForbidden();
        $this->actingAs($judge)->post(route('admin.event-judges.reset', $judge))->assertForbidden();
    }
}
