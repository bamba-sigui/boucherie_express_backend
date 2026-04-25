<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => fake()->randomElement(['pending', 'paid', 'preparing', 'shipping', 'delivered']),
            'total' => fake()->randomFloat(2, 20, 200),
            'items' => [
                [
                    'product_id' => 1,
                    'name' => 'Cote de Boeuf',
                    'quantity' => 2,
                    'price' => 25.00,
                ],
            ],
            'shipping_address' => fake()->address(),
        ];
    }
}