<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopFiveSelectionScore extends Model
{
    /** @use HasFactory<\Database\Factories\TopFiveSelectionScoreFactory> */
    use HasFactory;

    protected $fillable = [
        'candidate_id',
        'judge_id',
        'production_number',
        'casual_wear',
        'swim_wear', 
        'formal_wear',
        'closed_door_interview',
        'total_score',
    ];

     public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function judge()
    {
        return $this->belongsTo(User::class, 'judge_id');
    }    
    
}