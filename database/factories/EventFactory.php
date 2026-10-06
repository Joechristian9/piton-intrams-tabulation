<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Event ' . $this->faker->unique()->numberBetween(1, 99999),
            'code' => 'ev' . $this->faker->unique()->numberBetween(1, 99999),
            'status' => Event::SETUP,
            'rounds' => 2,
            'finalists_per_group' => 3,
            'finals_from_zero' => true,
        ];
    }

    public function live(): static
    {
        return $this->state(['status' => Event::LIVE]);
    }

    public function singleRound(): static
    {
        return $this->state(['rounds' => 1, 'finalists_per_group' => null]);
    }
}
