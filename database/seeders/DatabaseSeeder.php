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

        // Catégories sans images — les images vont sur les produits
        $categories = [
            ['id' => 1, 'name' => 'Boeuf',       'description' => 'Coupes de bœuf premium',      'sort_order' => 1, 'image' => null],
            ['id' => 2, 'name' => 'Agneau',       'description' => 'Viande d\'agneau française',  'sort_order' => 2, 'image' => null],
            ['id' => 3, 'name' => 'Porc',         'description' => 'Porc charcutier',             'sort_order' => 3, 'image' => null],
            ['id' => 4, 'name' => 'Volailles',    'description' => 'Poulet, canard, dinde',        'sort_order' => 4, 'image' => null],
            ['id' => 5, 'name' => 'Charcuterie',  'description' => 'Produits artisanaux',         'sort_order' => 5, 'image' => null],
            ['id' => 6, 'name' => 'Poisson',      'description' => 'Poissons et fruits de mer',   'sort_order' => 6, 'image' => null],
            ['id' => 7, 'name' => 'Épices',       'description' => 'Épices et assaisonnements',   'sort_order' => 7, 'image' => null],
        ];

        DB::table('categories')->insert($categories);

        $videoUrl = 'https://www.w3schools.com/html/mov_bbb.mp4';

        // Côte de bœuf (2 images)
        $coteBoeufImages = json_encode([
            'https://lh3.googleusercontent.com/aida-public/AB6AXuDXM2Istfc26EaBBsg-xZ0Kb4Dj8pnSaOjHMUlzgzns3n6hqDx6nMpI04g_Cpg-9dmNtuZUH00lM6Hz6rWOyhc7u2EB3IkUTiI_WB7g-FkqMuyx1nRDHSAYr2sIsJVT4BQdpNYG1LzIQw0wBrkvU76qpLqde40wphL9OgPW-pUgxN7LqfR0gswOxDSy-YVtyS-keueDzrhNCgcjYd8rPRple36wsuUxrVbN_qDQP5lF0pEf7IG4HSmAZOTAG7-xtijpjRzG2DeI0A',
            'https://loremflickr.com/800/600/beef,bone,grill',
        ]);

        // Filet de bœuf (2 images)
        $filetBoeufImages = json_encode([
            'https://loremflickr.com/800/600/beef,tenderloin,filet',
            'https://loremflickr.com/800/600/tenderloin,beef,raw',
        ]);

        // Entrecôte (2 images)
        $entrecoteImages = json_encode([
            'https://loremflickr.com/800/600/ribeye,steak,beef',
            'https://loremflickr.com/800/600/ribeye,beef,grill',
        ]);

        // Côtelettes d'agneau (2 images)
        $agneauImages = json_encode([
            'https://lh3.googleusercontent.com/aida-public/AB6AXuA7dtNCMryeKuY2VorIzeEHcn0XxaNAUolw_GEirZ6qt0Iyq4_kwdCrOMMjRUSnBxPQVBylpl2YqBI_6vJOLCLZAMmdNK9EMogJCLpuOO1XNzf07b8LBDX3dfDanmTnoliF99IOIg4RKFzZ3firN2GpsdFPlLucJBRLgpG2m2nOIHEnQA74Oey2ENBPx54FQ32v4lGLTIG7-KuGdTWrhRF-PsRycLWf63Ib982p49VGgLcP0zAsjcNPFtA3xehGvE4kraBCHR9NuA',
            'https://loremflickr.com/800/600/lamb,chops,grill',
        ]);

        // Rôti de porc (2 images)
        $porcImages = json_encode([
            'https://loremflickr.com/800/600/pork,roast',
            'https://loremflickr.com/800/600/pork,roast,oven',
        ]);

        // Poulet entier (4 images)
        $pouletImages = json_encode([
            'https://lh3.googleusercontent.com/aida-public/AB6AXuBjILed8FhgGgvVtlbG2qWPB6a5b8ByJkgzunk0ftNLsWVEFAz5luyq4t4BbHQM3tLOuhrUl4QDXa-GcuzxjRSEWn9bnNv-IoWgR4AkLLWEUzWQ2dZxoEz-59sXPzoyhS_26wlQcjJgP1rwI2h1Ij3yreDz-WWs2K-2-KFqp05Kitel5BXqOWFzaCwI_gvCvBhiPPtFbN5BvI-OE-UozWzQwSfvXXXKuShr-s3V2BIfCklCey__cOzmWaEqm-Y4tb-dYdh8Iek6EQ',
            'https://lh3.googleusercontent.com/aida-public/AB6AXuDEdKWgo9iEKfTcq9I6hNvDw_LDHlJ0uFav8tDptZVa2oWR2a13WqXfKxtCf_QlKXgQJ1MjcbLt4YvXkzQTtUmYZHomZnFDwZL169jaoiz2t19LT7IxCy-vNvI4r77U3Xvpn1V1RJnmBPKSbkNsG2A_28X7TR-q8STsqCl5YbqQ6BPmKsqj0LsmGuDcJfK2mHC7MFKnM6jni8EgyfpWbm8PPouBd6DMH0kafQtHD-_7gIqUOBxhQfRfKMsC_Vf7FuN3EhxWFumC2g',
            'https://lh3.googleusercontent.com/aida-public/AB6AXuAwbeTejxfakfRCOC0vAvhZ_zcNQMOnrD3SxVYuWkjFDyHpPps4a1xWCZwAJX2DoUbELgAWYFh_H7zIa0gRWmH2XnqgqYDITd9q4D5ie8lgexqDsvyCXgTzfeb8gpEJY7kG3kqGbiqwQzRMZEM5mQPkIgjrvsw_hV6dtIx9I6mVvFEkQbZSsw4IlcLKnEW7TLlr3JgRtFV2XiCKwnfM29K3ar0BxfYjIQSbP318f6uirW2WHaQYeNOs-YYd0fEWJngDqeyP4qwQSQ',
            'https://loremflickr.com/800/600/chicken,roasted',
        ]);

        // Jambon blanc (2 images)
        $jambonImages = json_encode([
            'https://loremflickr.com/800/600/ham,deli',
            'https://loremflickr.com/800/600/ham,slice,white',
        ]);

        // Saumon frais (3 images)
        $saumonImages = json_encode([
            'https://lh3.googleusercontent.com/aida-public/AB6AXuCN82LqpySZALE_CpW2BNKYcwBEtUwVquaswnBZJ0QWwucs3Y2WwLC1_A1VbDtHNe8LRmNqT0VwIjSUNR_yZlB1EXaA_jDBnHA-6cZ9xsFEyj_YanXyqPfSePT_QCj3e1Gh3_EmsLungtDDC-WLAnkCnzEN3PTYl0gC4pKVUpIPaEy42n3P7hex2rCU7rWC54vMpAnDZ5sq7eEptzKSGrf4KAFMklOyogiu2ekiXIkSeM6l0_z24eATzisBu2_KjNQE9Jl7ddIP3Q',
            'https://lh3.googleusercontent.com/aida-public/AB6AXuCnYxp2oQXMeT1wABRx1ZhAPcLQe-M_ghfitMIqtqO27DfgOHDIUPm1l3TADXnKEbRTNO9UcwiiIwBkhAdtKNQIA2WjMJUC2vRVe1XIhUOOlLIbirys3P_cdNFybcADjKt3ziKShyyzuu4ohzTqVZWWJLm44C8D4VTf7bQTn5LKrGa5qPYcEjwkaBj396XX13cHjrkTDkrj0c09oMX06aRrul2ikGNEQH7g499LSdgqedCBxUTeWLwRgXW_wuUn3hQg8uUELRjfOQ',
            'https://loremflickr.com/800/600/salmon,fish,fresh',
        ]);

        // Bœuf en cubes (2 images)
        $boeufCubesImages = json_encode([
            'https://lh3.googleusercontent.com/aida-public/AB6AXuDOAwXi2vjtxQc2hsmJh449eV9kgG0QaHa2p2vl1VODIUgFNT9tWbjW0Q8cuEBUlruZBv2bX2Y3TjgCsXxui9BJQUIXn6VrvlxWJ2jEWNDZVFaq9p18cgoXr0vSEKDqsp-eyoLB5tzOlyVy9jx085nbtrlV7TsQvRJNJNl7LgFu0FhncK9rWDbedsiGTu9d0H14yCsbz2_WXdQFyarjZ3q231Op3Un9-kJwaK3Pq204y5eAhL1kOwUkjWcTsYR0VGfnjhAeDjKlog',
            'https://loremflickr.com/800/600/beef,cubes,raw',
        ]);

        $products = [
            ['category_id' => 1, 'name' => 'Côte de bœuf',        'description' => 'Côte de bœuf de 400g environ',       'price' => 1890, 'stock' => 50, 'image' => null, 'images' => $coteBoeufImages,   'video_url' => $videoUrl],
            ['category_id' => 1, 'name' => 'Filet de bœuf',       'description' => 'Filet pur bœuf, qualité supérieure',  'price' => 3290, 'stock' => 30, 'image' => null, 'images' => $filetBoeufImages,  'video_url' => $videoUrl],
            ['category_id' => 1, 'name' => 'Entrecôte',           'description' => 'Entrecôte de bœuf maturée',           'price' => 2490, 'stock' => 25, 'image' => null, 'images' => $entrecoteImages,   'video_url' => $videoUrl],
            ['category_id' => 2, 'name' => 'Côtelettes d\'agneau','description' => '6 côtelettes d\'agneau français',      'price' => 2290, 'stock' => 20, 'image' => null, 'images' => $agneauImages,      'video_url' => $videoUrl],
            ['category_id' => 3, 'name' => 'Rôti de porc',        'description' => 'Rôti de porc artisanal',              'price' => 1490, 'stock' => 40, 'image' => null, 'images' => $porcImages,        'video_url' => $videoUrl],
            ['category_id' => 4, 'name' => 'Poulet entier',       'description' => 'Poulet fermier label rouge',           'price' => 1290, 'stock' => 35, 'image' => null, 'images' => $pouletImages,      'video_url' => $videoUrl],
            ['category_id' => 5, 'name' => 'Jambon blanc',        'description' => 'Jambon blanc supérieur',              'price' => 990,  'stock' => 60, 'image' => null, 'images' => $jambonImages,      'video_url' => $videoUrl],
            ['category_id' => 6, 'name' => 'Saumon frais',        'description' => 'Filets de saumon frais',              'price' => 2990, 'stock' => 20, 'image' => null, 'images' => $saumonImages,      'video_url' => $videoUrl],
            ['category_id' => 1, 'name' => 'Bœuf en cubes',       'description' => 'Bœuf tendre coupé en cubes',          'price' => 2190, 'stock' => 30, 'image' => null, 'images' => $boeufCubesImages,  'video_url' => $videoUrl],
        ];

        foreach ($products as $product) {
            DB::table('products')->insert(array_merge($product, ['is_active' => true]));
        }

        $this->command->info('Database seeded successfully!');
        $this->command->info('Admin login: admin@boucherie-express.fr / admin123');
    }
}