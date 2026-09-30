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
        Schema::create('registry_counts', function (Blueprint $table) {
            $table->id();
            $table->string('state', 32)->nullable();
            $table->string('municipality', 120)->nullable();
            $table->date('month')->nullable();
            $table->string('sex', 16)->nullable();
            $table->string('age_range', 16)->nullable();
            $table->string('status', 32)->nullable();
            $table->boolean('confidential')->default(false);
            $table->unsignedInteger('total');
            $table->timestamps();

            $table->index(['state', 'month']);
            $table->index('month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registry_counts');
    }
};
