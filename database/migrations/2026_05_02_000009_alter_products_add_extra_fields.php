<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('old_price')->nullable()->after('price');
            $table->string('unit')->default('kg')->after('stock');
            $table->integer('min_order_quantity')->default(1)->after('unit');
            $table->integer('low_stock_threshold')->default(5)->after('min_order_quantity');
            $table->json('images')->nullable()->after('image');
            $table->string('video_url')->nullable()->after('images');
            $table->boolean('is_fresh')->default(true)->after('is_active');
            $table->boolean('is_bio')->default(false)->after('is_fresh');
            $table->boolean('is_halal')->default(true)->after('is_bio');
            $table->boolean('is_promoted')->default(false)->after('is_halal');
            $table->boolean('is_featured')->default(false)->after('is_promoted');
            $table->json('preparation_options')->nullable()->after('is_featured');
            $table->json('tags')->nullable()->after('preparation_options');
            $table->string('farm_name')->nullable()->after('tags');
            $table->unsignedBigInteger('supplier_id')->nullable()->after('farm_name');
            $table->date('slaughter_date')->nullable()->after('supplier_id');
            $table->string('lot_number')->nullable()->after('slaughter_date');
            $table->text('storage_conditions')->nullable()->after('lot_number');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'old_price', 'unit', 'min_order_quantity', 'low_stock_threshold',
                'images', 'video_url', 'is_fresh', 'is_bio', 'is_halal',
                'is_promoted', 'is_featured', 'preparation_options', 'tags',
                'farm_name', 'supplier_id', 'slaughter_date', 'lot_number', 'storage_conditions',
            ]);
        });
    }
};
