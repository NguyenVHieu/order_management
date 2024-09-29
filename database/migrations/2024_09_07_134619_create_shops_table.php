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
            $table->string('name');
            $table->string('token_printify', 1000);
            $table->string('shop_printify_id');
            $table->string('token_merchize', 1000);
            $table->string('email_otb');
            $table->string('password_otb', 1000);
            $table->string('email_private');
            $table->string('password_private', 1000);
            $table->string('token_hubfulfill', 1000);
            $table->string('email_lenful');
            $table->string('password_lenful', 1000);
            $table->string('shop_lenful_id');
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
