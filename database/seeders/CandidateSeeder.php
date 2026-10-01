<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Candidate;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        $femaleCandidates = [
            ['Elsie', 'Valdez', 'Bachelor of Technical and Vocational Education'],
            ['Nicole Kim', 'Paguirigan', 'Bachelor of Secondary Education'],
            ['Andrea Leigh', 'Fernandez', 'Bachelor of Science in Psychology'],
            ['Kenneth Clier', 'Bunagan', 'Bachelor of Science in Nursing'],
            ['Ma. Kathleen Joyce', 'Cabanlong', 'Bachelor of Science in Civil Engineering'],
            ['Simran', 'Lola', 'Bachelor of Science in Information Technology'],
            ['Sharah Mayne', 'Mendieta', 'Bachelor of Science in Industrial Technology'],
            ['Monique Laurose', 'Evangelista', 'Bachelor of Science in Architecture'],
            ['Ashleigh Zea', 'Bartolome', 'Bachelor of Physical Education'],
            ['Precious Nhickole', 'Zipagan', 'Bachelor of Science in Electrical Engineering'],
            ['Gracielle', 'Perez', 'Bachelor of Science in Midwifery'],
        ];

        $maleCandidates = [
            ['JunJun', 'Cabansag', 'Bachelor of Technical and Vocational Education'],
            ['Rayndel Deive', 'Respicio', 'Bachelor of Secondary Education'],
            ['Smile', 'Aroc', 'Bachelor of Science in Psychology'],
            ['Jojeath', 'Queturas', 'Bachelor of Science in Nursing'],
            ['Jaiden Paul', 'Orteza', 'Bachelor of Science in Civil Engineering'],
            ['Justin Elijah', 'Tumolva', 'Bachelor of Science in Information Technology'],
            ['Gerome', 'De Guzman', 'Bachelor of Science in Industrial Technology'],
            ['Prince John', 'Agcaoili', 'Bachelor of Science in Architecture'],
            ['Jairus', 'Alvarez', 'Bachelor of Physical Education'],
            ['Jenkins', 'Delos Reyes', 'Bachelor of Science in Electrical Engineering'],
            ['John Carlo', 'Mendoza', 'Bachelor of Technology and Livelihood Education'],
        ];

        // Female candidates with numbered images and candidate_number
        foreach ($femaleCandidates as $index => $candidate) {
            Candidate::create([
                'first_name'      => $candidate[0],
                'last_name'       => $candidate[1],
                'gender'          => 'female',
                'course'          => $candidate[2],
                'profile_img'     => "candidates/female/" . ($index + 1) . ".jpg",
                'candidate_number' => $index + 1,
            ]);
        }

        // Male candidates with numbered images and candidate_number
        foreach ($maleCandidates as $index => $candidate) {
            Candidate::create([
                'first_name'      => $candidate[0],
                'last_name'       => $candidate[1],
                'gender'          => 'male',
                'course'          => $candidate[2],
                'profile_img'     => "candidates/male/" . ($index + 1) . ".jpg",
                'candidate_number' => $index + 1,
            ]);
        }
    }
}
