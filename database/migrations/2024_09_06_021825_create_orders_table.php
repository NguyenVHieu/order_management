<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number');
            $table->unsignedBigInteger('product_id');
            $table->decimal('price')->default(0.00);
            $table->unsignedBigInteger('shop_id');
            $table->string('variant_id')->nullable();
            $table->unsignedBigInteger('print_provider_id');
            $table->unsignedBigInteger('blueprint_id');
            $table->integer('quantity');
            $table->string('status')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_push')->default(0); 
            $table->decimal('item_total')->default(0.00);
            $table->decimal('discount')->default(0.00);
            $table->decimal('sub_total')->default(0.00);
            $table->decimal('shipping')->default(0.00);
            $table->decimal('sale_tax')->default(0.00);
            $table->decimal('order_total')->default(0.00);
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
