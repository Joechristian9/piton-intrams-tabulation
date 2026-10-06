<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AppTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_the_default_is_piton_gold_and_every_page_shares_the_theme(): void
    {
        $this->assertSame('gold', AppTheme::current());

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.theme.edit'))
            ->assertOk()
            ->assertSee('data-theme="gold"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Theme')
                ->where('theme', 'gold')
                ->where('presets.0.key', 'gold'));
    }

    public function test_admin_picks_a_preset_for_everyone(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->put(route('admin.theme.update'), ['theme' => 'blue'])->assertSessionHasNoErrors();
        $this->assertSame('blue', AppTheme::current());

        // Judges see it too.
        $judge = User::factory()->create(['role' => 'judge']);
        $this->actingAs($judge)->get(route('dashboard'))
            ->assertSee('data-theme="blue"', false)
            ->assertInertia(fn (Assert $page) => $page->where('theme', 'blue'));

        // Switching back updates the same row.
        $this->actingAs($admin)->put(route('admin.theme.update'), ['theme' => 'gold']);
        $this->assertSame('gold', AppTheme::current());
        $this->assertSame(1, DB::table('settings')->count());
    }

    public function test_unknown_themes_are_rejected_and_a_removed_one_falls_back_to_the_default(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->put(route('admin.theme.update'), ['theme' => 'neon'])->assertSessionHasErrors('theme');

        DB::table('settings')->insert(['key' => 'theme', 'value' => 'retired']);
        $this->assertSame('gold', AppTheme::current());

        // Code deployed before the migration ran: pages still load with the default.
        \Illuminate\Support\Facades\Schema::drop('settings');
        $this->assertSame('gold', AppTheme::current());
    }

    public function test_only_admins_change_the_theme(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);

        $this->actingAs($judge)->get(route('admin.theme.edit'))->assertForbidden();
        $this->actingAs($judge)->put(route('admin.theme.update'), ['theme' => 'blue'])->assertForbidden();
        $this->assertSame('gold', AppTheme::current());
    }
}
