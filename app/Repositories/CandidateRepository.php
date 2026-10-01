<?php

namespace App\Repositories;

use App\Models\Candidate;

class CandidateRepository
{
    public function all()
    {
        return Candidate::all();
    }
}
