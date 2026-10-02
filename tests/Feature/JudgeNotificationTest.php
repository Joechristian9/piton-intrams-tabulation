<?php

namespace Tests\Feature;

use App\Models\Candidate;
use App\Models\TopFiveSelectionScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JudgeNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'name' => 'Darryl']);
    }

    private function judge(string $name = 'Judge'): User
    {
        return User::factory()->create(['role' => 'judge', 'name' => $name]);
    }

    private function feedFor(User $judge): array
    {
        return $this->actingAs($judge)->getJson('/judge/notifications')->assertOk()->json();
    }

    public function test_judges_cannot_open_or_send_from_the_notify_page(): void
    {
        $judge = $this->judge();

        $this->actingAs($judge)->get('/admin/notify-judges')->assertForbidden();
        $this->actingAs($judge)->post('/admin/notify-judges', ['category' => 'swim_wear'])->assertForbidden();
    }

    public function test_admin_can_notify_all_judges_about_a_category(): void
    {
        $admin = $this->admin();
        [$a, $b] = [$this->judge('A'), $this->judge('B')];

        $this->actingAs($admin)
            ->post('/admin/notify-judges', ['category' => 'casual_wear'])
            ->assertSessionHasNoErrors();

        foreach ([$a, $b] as $judge) {
            $events = $this->feedFor($judge)['events'];
            $this->assertCount(1, $events);
            $this->assertSame('Please score the candidates for Sports Wear.', $events[0]['message']);
            $this->assertSame('casual_wear', $events[0]['route']);
            $this->assertSame('Darryl', $events[0]['sender']);
            $this->assertArrayNotHasKey('judge_ids', $events[0]);
        }
    }

    public function test_only_selected_judges_receive_a_targeted_notification(): void
    {
        $admin = $this->admin();
        [$a, $b] = [$this->judge('A'), $this->judge('B')];

        $this->actingAs($admin)->post('/admin/notify-judges', [
            'category' => 'delivery',
            'message' => 'Finals are starting, please score Delivery now.',
            'judge_ids' => [$a->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            'Finals are starting, please score Delivery now.',
            $this->feedFor($a)['events'][0]['message']
        );
        $this->assertSame('delivery', $this->feedFor($a)['events'][0]['route']);
        $this->assertCount(0, $this->feedFor($b)['events']);
    }

    public function test_general_message_without_a_category(): void
    {
        $admin = $this->admin();
        $judge = $this->judge();

        $this->actingAs($admin)->post('/admin/notify-judges', [])->assertSessionHasNoErrors();

        $event = $this->feedFor($judge)['events'][0];
        $this->assertSame('Please check your scoring sheets.', $event['message']);
        $this->assertNull($event['route']);
    }

    public function test_invalid_category_and_non_judge_recipients_are_rejected(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post('/admin/notify-judges', ['category' => 'talent'])
            ->assertSessionHasErrors('category');

        $this->actingAs($admin)
            ->post('/admin/notify-judges', ['judge_ids' => [$admin->id]])
            ->assertSessionHasErrors('judge_ids.0');
    }

    public function test_admins_cannot_read_the_judge_feed(): void
    {
        $this->actingAs($this->admin())->getJson('/judge/notifications')->assertForbidden();
    }

    public function test_notify_page_shows_each_judges_progress(): void
    {
        $admin = $this->admin();
        [$done, $partial] = [$this->judge('Done'), $this->judge('Partial')];

        $candidates = collect(range(1, 2))->map(fn ($n) => Candidate::create([
            'candidate_number' => $n, 'profile_img' => 'x.jpg', 'first_name' => 'C',
            'last_name' => (string) $n, 'course' => 'BSIT', 'gender' => 'female',
        ]));

        foreach ($candidates as $c) {
            TopFiveSelectionScore::create(['candidate_id' => $c->id, 'judge_id' => $done->id, 'swim_wear' => 20]);
        }
        TopFiveSelectionScore::create(['candidate_id' => $candidates[0]->id, 'judge_id' => $partial->id, 'swim_wear' => 15]);

        $props = $this->actingAs($admin)->get('/admin/notify-judges')->assertOk()
            ->viewData('page')['props'];

        $this->assertSame(2, $props['progress']['swim_wear']['total']);
        $this->assertSame(2, $props['progress']['swim_wear']['scored'][$done->id]);
        $this->assertSame(1, $props['progress']['swim_wear']['scored'][$partial->id]);
        $this->assertSame(0, $props['progress']['casual_wear']['scored'][$done->id]);
        $this->assertCount(8, $props['categories']);
    }
}
