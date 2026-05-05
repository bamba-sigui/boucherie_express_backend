<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminRole     = Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
        $partnerRole   = Role::firstOrCreate(['name' => 'partenaire', 'guard_name' => 'web']);

        $admin = \App\Models\User::firstOrCreate(
            ['email' => 'admin@boucherie-express.fr'],
            [
                'name'     => 'Admin',
                'password' => Hash::make('admin123'),
                'phone'    => '+33 6 00 00 00 00',
            ]
        );
        $admin->syncRoles([$adminRole]);

        // Truncate to avoid duplicates on re-seed
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('categories')->truncate();
        DB::table('products')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $categories = [
            ['id' => 1, 'name' => 'Boeuf',       'description' => 'Coupes de bœuf premium',      'sort_order' => 1],
            ['id' => 2, 'name' => 'Agneau',       'description' => 'Viande d\'agneau française',  'sort_order' => 2],
            ['id' => 3, 'name' => 'Porc',         'description' => 'Porc charcutier',             'sort_order' => 3],
            ['id' => 4, 'name' => 'Volailles',    'description' => 'Poulet, canard, dinde',        'sort_order' => 4],
            ['id' => 5, 'name' => 'Charcuterie',  'description' => 'Produits artisanaux',         'sort_order' => 5],
        ];

        DB::table('categories')->insert($categories);

        $products = [
            ['category_id' => 1, 'name' => 'Côte de bœuf',        'description' => 'Côte de bœuf de 400g environ',       'price' => 1890, 'stock' => 50],
            ['category_id' => 1, 'name' => 'Filet de bœuf',       'description' => 'Filet pur bœuf, qualité supérieure',  'price' => 3290, 'stock' => 30],
            ['category_id' => 1, 'name' => 'Entrecôte',           'description' => 'Entrecôte de bœuf maturée',           'price' => 2490, 'stock' => 25],
            ['category_id' => 2, 'name' => 'Côtelettes d\'agneau','description' => '6 côtelettes d\'agneau français',      'price' => 2290, 'stock' => 20],
            ['category_id' => 3, 'name' => 'Rôti de porc',        'description' => 'Rôti de porc artisanal',              'price' => 1490, 'stock' => 40],
            ['category_id' => 4, 'name' => 'Poulet entier',       'description' => 'Poulet fermier label rouge',           'price' => 1290, 'stock' => 35],
            ['category_id' => 5, 'name' => 'Jambon blanc',        'description' => 'Jambon blanc supérieur',              'price' => 990,  'stock' => 60],
        ];

        foreach ($products as $product) {
            DB::table('products')->insert(array_merge($product, ['is_active' => true]));
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin login: admin@boucherie-express.fr / admin123');
    }
}