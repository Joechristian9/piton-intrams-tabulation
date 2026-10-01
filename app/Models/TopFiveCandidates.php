<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopFiveCandidates extends Model
{
    /** @use HasFactory<\Database\Factories\TopFiveCandidatesFactory> */
    use HasFactory;

    protected $fillable = [
        'candidate_id',
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }
}
