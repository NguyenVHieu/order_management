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
            $table->unsignedBigInteger('designer_tag')->nullable();
            $table->unsignedBigInteger('designer_process')->nullable();
            $table->timestamps();
            $table->timestamp('deadline')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('url_done', 255)->nullable();
            $table->unsignedTinyInteger('level_task')->default(1);
            $table->text('comment')->nullable();
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
