<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A ranked group within an event (e.g. Female, Male); each gets its own Top N. */
class EventGroup extends Model
{
    /** @use HasFactory<\Database\Factories\EventGroupFactory> */
    use HasFactory;

    protected $fillable = ['event_id', 'name', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'group_id');
    }
}
