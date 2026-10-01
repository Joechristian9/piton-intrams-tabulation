<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Candidate>
 */
class CandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_img' => $this->faker->imageUrl(200, 200, 'people'),
            'first_name'  => $this->faker->firstName(),
            'last_name'   => $this->faker->lastName(),
            'gender'      => $this->faker->randomElement(['male', 'female']),
            'course'      => $this->faker->randomElement(['Bacherlor of Science in Information Technology', 'Bachelor of Science in Computer Science', 'Bachelor of Science in Information Systems', 'Bachelor of Science in Software Engineering']),
        ];
    }
}
