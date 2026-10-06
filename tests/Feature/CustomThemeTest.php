<?php

namespace Tests\Feature;

use App\Models\CustomTheme;
use App\Models\User;
use App\Support\AppTheme;
use App\Support\ThemeColors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CustomThemeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function create(array $fields = [])
    {
        return $this->actingAs($this->admin)->post(route('admin.themes.store'), [
            'name' => 'School Colors', 'accent' => '#3B82F6', 'surface' => '#0F172A', ...$fields,
        ]);
    }

    /** Same numbers as tests/js/themeColors.test.mjs (the editor's live preview). */
    public function test_colors_match_the_editor_preview(): void
    {
        $v = ThemeColors::variables('#3b82f6', '#0f172a');

        $this->assertCount(33, $v);
        $this->assertSame('59 130 246', $v['--accent-400']);
        $this->assertSame('59 130 246', $v['--accent2-400']);
        $this->assertSame('239 245 254', $v['--accent-50']);
        $this->assertSame('19 42 79', $v['--accent-900']);
        $this->assertSame('15 23 42', $v['--surface-900']);
        $this->assertSame('32 39 57', $v['--surface-800']);
        $this->assertSame('8 13 23', $v['--surface-950']);
    }

    public function test_admin_creates_a_theme_and_everyone_gets_its_colors(): void
    {
        $this->create(['apply' => true])->assertSessionHasNoErrors();

        $theme = CustomTheme::sole();
        $this->assertSame(['School Colors', '#3b82f6', '#0f172a'], [$theme->name, $theme->accent, $theme->surface]);
        $this->assertSame("custom-{$theme->id}", AppTheme::current());

        $judge = User::factory()->create(['role' => 'judge']);
        $this->actingAs($judge)->get(route('dashboard'))
            ->assertSee("data-theme=\"custom-{$theme->id}\"", false)
            ->assertSee('--accent-400: 59 130 246', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('theme', "custom-{$theme->id}")
                ->where('themeVars.--surface-900', '15 23 42'));
    }

    public function test_creating_without_apply_keeps_the_current_theme(): void
    {
        $this->create()->assertSessionHasNoErrors();

        $this->assertSame('gold', AppTheme::current());
        $this->actingAs($this->admin)->get(route('admin.theme.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('themeVars', null)
                ->where('customThemes.0.name', 'School Colors')
                ->where('customThemes.0.vars.--accent-400', '59 130 246'));

        // It can be picked like a preset.
        $key = CustomTheme::sole()->key();
        $this->actingAs($this->admin)->put(route('admin.theme.update'), ['theme' => $key])->assertSessionHasNoErrors();
        $this->assertSame($key, AppTheme::current());
    }

    public function test_unreadable_or_malformed_colors_and_duplicate_names_are_rejected(): void
    {
        $this->create(['accent' => '#1e3a8a'])->assertSessionHasErrors('accent');   // too dark for black button text
        $this->create(['surface' => '#9ca3af'])->assertSessionHasErrors('surface'); // too light for white text
        $this->create(['accent' => '#8a8a8a', 'surface' => '#333333'])->assertSessionHasErrors('accent'); // too close
        $this->create(['accent' => 'blue'])->assertSessionHasErrors('accent');
        $this->create(['name' => ''])->assertSessionHasErrors('name');
        $this->assertSame(0, CustomTheme::count());

        $this->create()->assertSessionHasNoErrors();
        $this->create()->assertSessionHasErrors('name');
    }

    public function test_editing_changes_the_colors_in_use(): void
    {
        $this->create(['apply' => true]);
        $theme = CustomTheme::sole();

        $this->actingAs($this->admin)->put(route('admin.themes.update', $theme), ['name' => 'School Colors', 'accent' => '#facc15', 'surface' => '#0f172a'])
            ->assertSessionHasNoErrors();

        $this->assertSame('250 204 21', AppTheme::resolve()['vars']['--accent-400']);
    }

    public function test_the_theme_in_use_cannot_be_deleted_and_a_gone_theme_falls_back(): void
    {
        $this->create(['apply' => true]);
        $theme = CustomTheme::sole();

        $this->actingAs($this->admin)->delete(route('admin.themes.destroy', $theme))->assertSessionHasErrors('theme');
        $this->assertSame(1, CustomTheme::count());

        $this->actingAs($this->admin)->put(route('admin.theme.update'), ['theme' => 'blue']);
        $this->actingAs($this->admin)->delete(route('admin.themes.destroy', $theme))->assertSessionHasNoErrors();
        $this->assertSame(0, CustomTheme::count());

        // Picking a deleted theme is refused; a saved key whose theme is gone shows the default.
        $this->actingAs($this->admin)->put(route('admin.theme.update'), ['theme' => $theme->key()])->assertSessionHasErrors('theme');
        AppTheme::set($theme->key());
        $this->assertSame(['key' => 'gold', 'vars' => null], AppTheme::resolve());
    }

    public function test_judges_cannot_manage_themes(): void
    {
        $judge = User::factory()->create(['role' => 'judge']);
        $theme = CustomTheme::create(['name' => 'X', 'accent' => '#3b82f6', 'surface' => '#0f172a']);

        $this->actingAs($judge)->post(route('admin.themes.store'), ['name' => 'Y', 'accent' => '#3b82f6', 'surface' => '#0f172a'])->assertForbidden();
        $this->actingAs($judge)->put(route('admin.themes.update', $theme), ['name' => 'Z', 'accent' => '#3b82f6', 'surface' => '#0f172a'])->assertForbidden();
        $this->actingAs($judge)->delete(route('admin.themes.destroy', $theme))->assertForbidden();
        $this->assertSame('X', $theme->fresh()->name);
    }
}
