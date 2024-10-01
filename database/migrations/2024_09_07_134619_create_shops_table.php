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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();;
            $table->string('token_printify', 1000)->nullable();;
            $table->string('shop_printify_id')->nullable();;
            $table->string('token_merchize', 1000)->nullable();;
            $table->string('email_otb')->nullable();;
            $table->string('password_otb', 1000)->nullable();;
            $table->string('email_private')->nullable();;
            $table->string('password_private', 1000)->nullable();;
            $table->string('token_hubfulfill', 1000)->nullable();;
            $table->string('email_lenful')->nullable();;
            $table->string('password_lenful', 1000)->nullable();;
            $table->string('shop_lenful_id')->nullable();;
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps(); 
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
