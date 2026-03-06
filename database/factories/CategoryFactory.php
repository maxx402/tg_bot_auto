<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['video', 'photo']);
        $key = fake()->unique()->slug();

        return [
            'external_id' => fake()->unique()->uuid(),
            'type' => $type,
            'key' => $key,
            'title' => fake()->words(2, true),
            'icon' => 'category/' . $key,
            'order' => fake()->numberBetween(1, 100),
            'status' => true,
            'external_created_at' => fake()->dateTimeBetween('-1 year'),
            'external_updated_at' => fake()->dateTimeBetween('-1 month'),
        ];
    }
}
