<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * A pageant or contest: its groups, categories (per round), candidates, judges
 * and finalists. Several events can be live at once.
 */
class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    public const SETUP = 'setup';
    public const LIVE = 'live';
    public const CLOSED = 'closed';

    protected $fillable = [
        'name',
        'code',
        'theme',
        'status',
        'rounds',
        'finalists_per_group',
        'finals_from_zero',
        'round1_weight',
        'finals_weight',
    ];

    protected function casts(): array
    {
        return [
            'rounds' => 'integer',
            'finalists_per_group' => 'integer',
            'finals_from_zero' => 'boolean',
            'round1_weight' => 'integer',
            'finals_weight' => 'integer',
            'finalists_set_at' => 'datetime',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function groups(): HasMany
    {
        return $this->hasMany(EventGroup::class)->orderBy('position')->orderBy('id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('round')->orderBy('position')->orderBy('id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    /** Every score given in this event (through its categories). */
    public function scores(): HasManyThrough
    {
        return $this->hasManyThrough(Score::class, Category::class);
    }

    public function judges(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'judge')->orderBy('id');
    }

    /** In the order they were set (today's top_five_candidates order). */
    public function finalists(): HasMany
    {
        return $this->hasMany(Finalist::class)->orderBy('id');
    }

    public function isLive(): bool
    {
        return $this->status === self::LIVE;
    }

    public function finalistsSet(): bool
    {
        return $this->finalists_set_at !== null;
    }
}
