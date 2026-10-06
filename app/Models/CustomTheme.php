<?php

namespace App\Models;

use App\Support\ThemeColors;
use Illuminate\Database\Eloquent\Model;

/** A color theme an admin made (accent + background); picked as key "custom-{id}". */
class CustomTheme extends Model
{
    public const KEY_PREFIX = 'custom-';

    protected $fillable = ['name', 'accent', 'surface'];

    public function key(): string
    {
        return self::KEY_PREFIX . $this->id;
    }

    public static function findByKey(?string $key): ?self
    {
        if (! $key || ! preg_match('/^' . self::KEY_PREFIX . '(\d+)$/', $key, $m)) {
            return null;
        }

        return self::find((int) $m[1]);
    }

    /** @return array<string, string> the CSS variables for this theme */
    public function variables(): array
    {
        return ThemeColors::variables($this->accent, $this->surface);
    }
}
