<?php

namespace App\Support;

use App\Models\CustomTheme;
use App\Models\Event;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Color themes. A theme is a preset from resources/js/lib/themes.json (the Tailwind
 * build turns each into CSS variables; the first, PITON Gold, is the fallback) or an
 * admin-made CustomTheme ("custom-{id}", whose variables are sent with the page and
 * set on <html>). Each event can pick its own (`events.theme`); pages outside an
 * event, and events left on "Default", use the app's default theme set on the Theme page.
 */
class AppTheme
{
    private const KEY = 'theme';

    /** @return list<array{key: string, label: string, description: string}> */
    public static function presets(): array
    {
        return once(fn () => json_decode(file_get_contents(resource_path('js/lib/themes.json')), true));
    }

    /** @return list<string> the preset keys */
    public static function keys(): array
    {
        return array_column(self::presets(), 'key');
    }

    /** The fallback when no default is saved: PITON Gold. */
    public static function default(): string
    {
        return self::keys()[0];
    }

    /** A preset key or the key of an existing custom theme. */
    public static function exists(?string $key): bool
    {
        return self::lookup($key) !== null;
    }

    /**
     * The theme a page uses: ['key' => ..., 'vars' => CSS variables for a custom theme,
     * else null]. The event's own theme if it has one, else the app default. Falls back
     * to PITON Gold when a saved theme is gone or the tables are missing (code updated
     * before `php artisan migrate` must not break pages).
     */
    public static function resolve(?Event $event = null): array
    {
        try {
            return self::lookup($event?->theme)
                ?? self::lookup(DB::table('settings')->where('key', self::KEY)->value('value'))
                ?? ['key' => self::default(), 'vars' => null];
        } catch (QueryException) {
            return ['key' => self::default(), 'vars' => null];
        }
    }

    /** The app's default theme key (outside events, and for events left on "Default"). */
    public static function current(): string
    {
        return self::resolve()['key'];
    }

    public static function set(string $theme): void
    {
        DB::table('settings')->updateOrInsert(['key' => self::KEY], ['value' => $theme, 'updated_at' => now()]);
    }

    /**
     * Every theme an event can pick, for the event settings form:
     * ['default' => key, 'options' => [['key', 'label', 'vars']]].
     */
    public static function options(): array
    {
        return [
            'default' => self::current(),
            'options' => [
                ...array_map(fn ($p) => ['key' => $p['key'], 'label' => $p['label'], 'vars' => null], self::presets()),
                ...CustomTheme::orderBy('name')->get()->map(fn (CustomTheme $t) => [
                    'key' => $t->key(), 'label' => $t->name, 'vars' => $t->variables(),
                ])->all(),
            ],
        ];
    }

    /** "--accent-50: 254 252 232; ..." for a style attribute. */
    public static function inlineStyle(?array $vars): string
    {
        return collect($vars ?? [])->map(fn ($value, $name) => "{$name}: {$value}")->implode('; ');
    }

    /** @return array{key: string, vars: ?array}|null */
    private static function lookup(?string $key): ?array
    {
        if (in_array($key, self::keys(), true)) {
            return ['key' => $key, 'vars' => null];
        }
        $custom = CustomTheme::findByKey($key);

        return $custom ? ['key' => $custom->key(), 'vars' => $custom->variables()] : null;
    }
}
