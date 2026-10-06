<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One judge's score for one candidate in one category. */
class Score extends Model
{
    protected $fillable = ['category_id', 'candidate_id', 'judge_id', 'score'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function judge(): BelongsTo
    {
        return $this->belongsTo(User::class, 'judge_id');
    }
}
