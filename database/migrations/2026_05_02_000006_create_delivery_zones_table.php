<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->integer('fee')->default(1500);
            $table->integer('free_delivery_threshold')->default(30000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
