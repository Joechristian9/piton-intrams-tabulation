<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventGroup>
 */
class EventGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'name' => 'Group ' . $this->faker->unique()->numberBetween(1, 99999),
            'position' => 1,
        ];
    }
}
