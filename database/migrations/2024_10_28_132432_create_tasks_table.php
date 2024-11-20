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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('image', 255)->nullable();
            $table->unsignedBigInteger('status_id');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('category_design_id');
            $table->unsignedBigInteger('designer_recipient_id')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->timestamps();
            $table->timestamp('deadline')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('url_done', 255)->nullable();
            $table->unsignedBigInteger('count_product')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
