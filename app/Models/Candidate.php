<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    /** @use HasFactory<\Database\Factories\CandidateFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'group_id',
        'profile_img',
        'candidate_number',
        'first_name',
        'last_name',
        'gender',
        'course',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function group()
    {
        return $this->belongsTo(EventGroup::class, 'group_id');
    }

    public function topFiveScores()
    {
        return $this->hasMany(TopFiveScore::class);
    }

    public function topFiveSelectionScores()
    {
        return $this->hasMany(TopFiveSelectionScore::class);
    }
}
