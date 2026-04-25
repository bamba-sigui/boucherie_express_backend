<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = \App\Models\User::create([
            'name' => 'Admin',
            'email' => 'admin@boucherie-express.fr',
            'password' => Hash::make('admin123'),
            'phone' => '+33 6 00 00 00 00',
        ]);

        $categories = [
            ['name' => 'Boeuf', 'description' => 'Coupes de Boeuf premium'],
            ['name' => 'Agneau', 'description' => 'Viande d\'agneau française'],
            ['name' => 'Porc', 'description' => 'Porc charcutier'],
            ['name' => 'Volailles', 'description' => 'Poulet, canard, dinde'],
            ['name' => 'Charcuterie', 'description' => 'Produits artisanale'],
        ];

        foreach ($categories as $cat) {
            DB::table('categories')->insert($cat);
        }

        $products = [
            ['category_id' => 1, 'name' => 'Côte de bœuf', 'description' => 'Côte de bœuf de 400g environ', 'price' => 18.90, 'stock' => 50],
            ['category_id' => 1, 'name' => 'Filet de bœuf', 'description' => 'Filet pur bœuf, qualité supérieure', 'price' => 32.90, 'stock' => 30],
            ['category_id' => 1, 'name' => 'Entrecôte', 'description' => 'Entrecôte de bœuf maturée', 'price' => 24.90, 'stock' => 25],
            ['category_id' => 2, 'name' => 'Côtelettes d\'agneau', 'description' => '6 côtelettes d\'agneau français', 'price' => 22.90, 'stock' => 20],
            ['category_id' => 3, 'name' => 'Rôti de por', 'description' => 'Rôti de porc artisanal', 'price' => 14.90, 'stock' => 40],
            ['category_id' => 4, 'name' => 'Poulet entier', 'description' => 'Poulet fermier label rouge', 'price' => 12.90, 'stock' => 35],
            ['category_id' => 5, 'name' => 'Jambon blanc', 'description' => 'Jambon blanc supérieur', 'price' => 9.90, 'stock' => 60],
        ];

        foreach ($products as $product) {
            DB::table('products')->insert(array_merge($product, ['is_active' => true]));
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin login: admin@boucherie-express.fr / admin123');
    }
}