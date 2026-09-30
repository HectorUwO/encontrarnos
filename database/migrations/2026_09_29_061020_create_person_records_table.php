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
        Schema::create('person_records', function (Blueprint $table) {
            $table->id();
            $table->string('folio', 20)->unique();
            $table->string('type', 32);
            $table->string('disappearance_status', 32)->nullable();
            $table->string('sex', 16)->nullable();
            $table->string('name', 200)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->unsignedTinyInteger('current_age')->nullable();
            $table->string('state', 32)->nullable();
            $table->string('municipality', 120)->nullable();
            $table->date('event_date')->nullable();
            $table->text('description')->nullable();
            $table->json('traits')->nullable();
            $table->text('clothing')->nullable();
            $table->text('distinguishing_marks')->nullable();
            $table->string('authority')->nullable();
            $table->char('photo_sha256', 64)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('source_victim_id', 36)->nullable();
            $table->unsignedInteger('source_report_id')->nullable();
            $table->unsignedInteger('source_agency_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['source_victim_id', 'source_report_id', 'source_agency_id'], 'person_records_source_unique');
            $table->index(['event_date', 'id']);
            $table->index('state');
            $table->index('age');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('person_records');
    }
};
