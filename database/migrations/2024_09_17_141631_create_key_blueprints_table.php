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
        Schema::create('key_blueprints', function (Blueprint $table) {
            $table->id();
            $table->string('style')->nullable();
            $table->string('color')->nullable();
            $table->string('printify')->nullable();
            $table->string('merchize')->nullable();
            $table->string('private')->nullable();
            $table->string('hubfulfill')->nullable();
            $table->string('otb')->nullable();
            $table->string('lenfull')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('key_blueprints');
    }
};
