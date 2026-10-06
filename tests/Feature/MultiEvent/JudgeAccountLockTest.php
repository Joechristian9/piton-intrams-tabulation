<?php

namespace Tests\Feature\MultiEvent;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Judge accounts are managed by the admin only: judges can't edit or delete
 * their own account (deleting would also delete their scores).
 */
class JudgeAccountLockTest extends TestCase
{
    use BuildsEvents, RefreshDatabase;

    public function test_judges_cannot_open_edit_or_delete_their_account(): void
    {
        $this->withoutVite();
        $event = $this->makeEvent();
        $judge = $this->addJudges($event, 1)->first();

        $this->actingAs($judge)->get(route('profile.edit'))->assertForbidden();
        $this->actingAs($judge)->patch(route('profile.update'), ['name' => 'X', 'email' => 'x@example.test'])->assertForbidden();
        $this->actingAs($judge)->delete(route('profile.destroy'), ['password' => 'password'])->assertForbidden();
        $this->actingAs($judge)->put(route('password.update'), [
            'current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
        ])->assertForbidden();

        $this->assertModelExists($judge);
        $this->assertSame($judge->email, $judge->fresh()->email);
    }

    public function test_admins_keep_their_account_page(): void
    {
        $this->withoutVite();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('profile.edit'))->assertOk();
    }
}
