<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'round' => 1,
            'name' => 'Category ' . $this->faker->unique()->numberBetween(1, 99999),
            'max_score' => 25,
            'position' => 1,
        ];
    }
}
