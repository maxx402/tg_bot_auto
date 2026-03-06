<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Content>
 */
class ContentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['video', 'photo']);
        $content = $type === 'video'
            ? fake()->url()
            : fake()->randomElements([fake()->imageUrl(), fake()->imageUrl(), fake()->imageUrl()], fake()->numberBetween(1, 10));

        return [
            'external_id' => fake()->unique()->uuid(),
            'category_id' => \App\Models\Category::factory(),
            'type' => $type,
            'title' => fake()->sentence(),
            'cover' => fake()->imageUrl(),
            'content' => $content,
            'price' => fake()->numberBetween(0, 100),
            'views' => fake()->numberBetween(0, 10000),
            'collects' => fake()->numberBetween(0, 5000),
            'shares' => fake()->numberBetween(0, 3000),
            'comments' => fake()->numberBetween(0, 500),
            'duration' => $type === 'video' ? fake()->randomFloat(2, 5, 300) : null,
            'status' => true,
            'member_data' => [
                '_id' => fake()->uuid(),
                'username' => fake()->userName(),
                'avatar' => fake()->imageUrl(),
                'group' => fake()->word(),
                'status' => fake()->numberBetween(0, 1),
            ],
            'external_created_at' => fake()->dateTimeBetween('-1 year'),
            'external_updated_at' => fake()->dateTimeBetween('-1 month'),
        ];
    }
}
