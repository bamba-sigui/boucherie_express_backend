<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'Viandes',
                'Volailles',
                'Charcuterie',
                'Boeuf',
                'Agneau',
                'Porc',
                'Preparations',
            ]),
            'description' => fake()->sentence(),
            'image' => fake()->imageUrl(400, 300, 'food'),
        ];
    }
}