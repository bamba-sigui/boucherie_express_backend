<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => fake()->unique()->randomElement([
                'Cote de Boeuf',
                'Filet de Boeuf',
                'Entrecote',
                'Brochette de Boeuf',
                'Souris d\'Agneau',
                'Cote d\'Agneau',
                'Epaule de Porc',
                'Chichon',
                'Poulet Entier',
                'Cuisses de Poulet',
            ]),
            'description' => fake()->paragraph(),
            'price' => fake()->randomFloat(2, 10, 50),
            'stock' => fake()->numberBetween(0, 100),
            'image' => fake()->imageUrl(600, 400, 'meat'),
            'is_active' => true,
        ];
    }
}