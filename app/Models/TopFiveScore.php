<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopFiveScore extends Model
{
    /** @use HasFactory<\Database\Factories\TopFiveScoreFactory> */
    use HasFactory;

    protected $fillable = [
        'top_five_id',
        'judge_id',
        'face_and_figure',
        'delivery',
        'overall_appeal',
        'total_score',
    ];

    public function topFive()
    {
        return $this->belongsTo(TopFiveCandidates::class, 'top_five_id');
    }

    public function judge()
    {
        return $this->belongsTo(User::class, 'judge_id');
    }
}
