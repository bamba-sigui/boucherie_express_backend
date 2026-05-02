<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('photo_url')->nullable()->after('phone');
            $table->string('fcm_token')->nullable()->after('photo_url');
            $table->enum('account_type', ['b2c', 'b2b', 'admin', 'partner'])->default('b2c')->after('fcm_token');
            $table->boolean('is_premium')->default(false)->after('account_type');
            $table->timestamp('premium_until')->nullable()->after('is_premium');
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['photo_url', 'fcm_token', 'account_type', 'is_premium', 'premium_until']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
