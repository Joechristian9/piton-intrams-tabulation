<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    /** @use HasFactory<\Database\Factories\CandidateFactory> */
    use HasFactory;

    protected $fillable = [
        'profile_img',
        'candidate_number',
        'first_name',
        'last_name',
        'gender',
        'course',
    ];

    public function topFiveScores()
    {
        return $this->hasMany(TopFiveScore::class);
    }

    public function topFiveSelectionScores()
    {
        return $this->hasMany(TopFiveSelectionScore::class);
    }
}
