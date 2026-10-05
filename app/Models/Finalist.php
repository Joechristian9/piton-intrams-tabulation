<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A candidate who advanced to an event's final round. */
class Finalist extends Model
{
    protected $fillable = ['event_id', 'candidate_id'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
