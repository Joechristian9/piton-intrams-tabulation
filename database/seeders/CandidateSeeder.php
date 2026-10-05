<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Candidate;
use App\Models\Event;

class CandidateSeeder extends Seeder
{
    public function run(): void
    {
        // Event #1 (created by DatabaseSeeder); candidates belong to its Female/Male groups.
        $event = Event::where('code', 'piton')->firstOrFail();
        $groups = $event->groups()->pluck('id', 'name');

        $femaleCandidates = [
            ['Trixie Anne', 'Datul', 'Bachelor of Technology and Livelihood Education'],
            ['Jelly Mae', 'Panangui', 'Bachelor of Technical-Vocational Teacher Education'],
            ['Cieryl Feiye', 'Azul', 'Bachelor of Science in Civil Engineering'],
            ['Donnamie', 'Bernardo', 'Bachelor of Science in Nursing'],
            ['Simran', 'Lola', 'Bachelor of Science in Information Technology'],

            ['Ma. Theresa', 'Cabasal', 'Bachelor of Science in Electrical Engineering'],
            ['Ericka Mae', 'Aguinaldo', 'Bachelor of Science in Psychology'],
            ['Aisly', 'Salvador', 'Bachelor of Physical Education'],
            ['Iloissa', 'Manuel', 'Bachelor of Science in Architecture'],
            ['Jarmie', 'Nedia', 'Bachelor of Science in Industrial Technology'],

            ['Hanzel', 'Raquel', 'Bachelor of Science in Midwifery'],
            ['Kieshamea', 'Paqueño', 'Bachelor of Secondary Education']
        ];

        $maleCandidates = [
            ['Narciso', 'Cabalonga', 'Bachelor of Technology and Livelihood Education'],
            ['Jon-lei', 'Tagao', 'Bachelor of Technical-Vocational Teacher Education'],
            ['Muhammad', 'Brohi', 'Bachelor of Science in Civil Engineering'],
            ['Justine Paul', 'Garcia', 'Bachelor of Science in Nursing'],
            ['Reynaldo', 'Pascua Jr.', 'Bachelor of Science in Information Technology'],

            ['John Vincent', 'Juaton', 'Bachelor of Science in Electrical Engineering'],
            ['Lorenz Jamuel', 'Gamboa', 'Bachelor of Science in Psychology'],
            ['Luke', 'Dumlao', 'Bachelor of Physical Education'],
            ['Christopher', 'Catembung', 'Bachelor of Science in Architecture'],
            ['Jep', 'Lapuebla', 'Bachelor of Science in Industrial Technology']
        ];

        // Female candidates with numbered images and candidate_number
        foreach ($femaleCandidates as $index => $candidate) {
            Candidate::create([
                'first_name'      => $candidate[0],
                'last_name'       => $candidate[1],
                'event_id'        => $event->id,
                'group_id'        => $groups['Female'],
                'gender'          => 'female',
                'course'          => $candidate[2],
                'profile_img'     => "candidates/female/" . ($index + 1) . ".JPEG",
                'candidate_number' => $index + 1,
            ]);
        }

        // Male candidates with numbered images and candidate_number
        foreach ($maleCandidates as $index => $candidate) {
            Candidate::create([
                'first_name'      => $candidate[0],
                'last_name'       => $candidate[1],
                'event_id'        => $event->id,
                'group_id'        => $groups['Male'],
                'gender'          => 'male',
                'course'          => $candidate[2],
                'profile_img'     => "candidates/male/" . ($index + 1) . ".JPEG",
                'candidate_number' => $index + 1,
            ]);
        }
    }
}
