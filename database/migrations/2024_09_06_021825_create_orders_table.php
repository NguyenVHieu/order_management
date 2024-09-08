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
            $table->string('external_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('variant_id');
            $table->unsignedBigInteger('print_provider_id');
            $table->unsignedBigInteger('blueprint_id');
            $table->integer('quanity');
            $table->decimal('cost')->nullale();
            $table->decimal('shipping_cost')->nullale();
            $table->string('status')->nullale();
            $table->string('first_name')->nullale();
            $table->string('last_name')->nullale();
            $table->string('region')->nullable();
            $table->string('address1')->nullale();
            $table->string('city')->nullale();
            $table->string('zip')->nullale();
            $table->string('email')->nullale();
            $table->string('phone')->nullale();
            $table->string('country')->nullable();
            $table->string('company')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_push')->default(0); 
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
