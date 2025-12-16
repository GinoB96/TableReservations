<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class TableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ubication' => fake()->randomElement(['A', 'B', 'C', 'D']),
            'seats' => fake()->randomElement([2, 4]),
            'number' => fake()->unique()->numberBetween(1, 10),
        ];
    }
}
