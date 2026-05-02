<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('total_price')->default(0)->after('total');
            $table->integer('delivery_fee')->default(0)->after('total_price');
            $table->integer('total_amount')->default(0)->after('delivery_fee');
            $table->string('delivery_address')->nullable()->after('shipping_address');
            $table->unsignedBigInteger('address_id')->nullable()->after('delivery_address');
            $table->string('payment_method')->nullable()->after('address_id');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->after('payment_method');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->unsignedBigInteger('courier_id')->nullable()->after('payment_reference');
            $table->timestamp('eta')->nullable()->after('courier_id');
            $table->text('note')->nullable()->after('eta');
            $table->timestamp('ordered_at')->nullable()->after('note');

            $table->foreign('address_id')->references('id')->on('addresses')->nullOnDelete();
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['address_id']);
            $table->dropColumn([
                'total_price', 'delivery_fee', 'total_amount', 'delivery_address',
                'address_id', 'payment_method', 'payment_status', 'payment_reference',
                'courier_id', 'eta', 'note', 'ordered_at',
            ]);
        });
    }
};
