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
            $table->string('order_id')->nullable();
            $table->string('order_number')->nullable();
            $table->string('price')->nullable();
            $table->string('place_order')->nullable();
            $table->unsignedBigInteger('shop_id')->nullable();;
            $table->string('size')->nullable();
            $table->string('color')->nullable();
            $table->string('style')->nullable();
            $table->string('personalization')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('print_provider_id')->nullable();
            $table->unsignedBigInteger('blueprint_id')->nullable();
            $table->string('thumbnail', 1024)->nullable();
            $table->string('sku')->nullable();
            $table->integer('quantity')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('zip')->nullable();
            $table->string('country')->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('apartment')->nullable();    
            $table->string('address')->nullable();
            $table->decimal('item_total')->default(0.00)->nullable();
            $table->decimal('discount')->default(0.00)->nullable();
            $table->decimal('sub_total')->default(0.00)->nullable();
            $table->decimal('shipping')->default(0.00)->nullable();
            $table->decimal('sale_tax')->default(0.00)->nullable();
            $table->decimal('order_total')->default(0.00)->nullable();
            $table->string('img_1')->nullable();
            $table->string('img_2')->nullable();
            $table->string('img_3')->nullable();
            $table->string('img_4')->nullable();
            $table->string('img_5')->nullable();
            $table->string('img_6')->nullable();
            $table->string('img_7')->nullable();
            $table->string('product_name')->nullable(); 
            $table->timestamp('recieved_mail_at')->nullable();
            $table->boolean('is_push')->default(false);
            $table->boolean('is_approval')->default(false);
            $table->boolean('multi')->default(false);
            $table->string('status_order')->nullable();
            $table->string('tracking_order')->nullable();
            $table->decimal('cost', 10, 2)->default(0.00)->nullable();
            $table->date('date_push')->nullable();
            $table->string('order_number_group')->nullable();
            $table->unsignedBigInteger('push_by')->nullable();
            $table->unsignedBigInteger('approval_by')->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
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
