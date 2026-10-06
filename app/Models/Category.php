<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One scored category of an event's round; judges give one score up to max_score. */
class Category extends Model
{
    /** @use HasFactory<\Database\Factories\CategoryFactory> */
    use HasFactory;

    protected $fillable = ['event_id', 'round', 'name', 'icon', 'max_score', 'position'];

    /** Icon keys an admin can pick (shared with the sidebar's icon map); null icon = from the name. */
    public static function iconKeys(): array
    {
        static $keys;

        return $keys ??= json_decode(file_get_contents(resource_path('js/lib/categoryIcons.json')), true);
    }

    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'max_score' => 'float',
            'position' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
