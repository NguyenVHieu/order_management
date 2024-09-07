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
            $table->unsignedBigInteger('shop_id');
            $table->integer('quanity');
            $table->decimal('cost');
            $table->decimal('shipping_cost');
            $table->string('status');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('region')->nullable();
            $table->string('address1');
            $table->string('city');
            $table->string('zip');
            $table->string('email');
            $table->string('phone');
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
