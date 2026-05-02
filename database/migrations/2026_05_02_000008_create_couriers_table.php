<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('photo_url')->nullable();
            $table->string('vehicle')->default('Moto');
            $table->string('license_plate')->nullable();
            $table->decimal('rating', 3, 1)->default(5.0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true);
            $table->decimal('current_lat', 10, 7)->nullable();
            $table->decimal('current_lng', 10, 7)->nullable();
            $table->decimal('current_heading', 5, 2)->nullable();
            $table->timestamp('location_updated_at')->nullable();
            $table->timestamps();
        });

        // Ajouter la clé étrangère courier_id sur orders
        Schema::table('orders', function (Blueprint $table) {
            $table->foreign('courier_id')->references('id')->on('couriers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['courier_id']);
        });
        Schema::dropIfExists('couriers');
    }
};
