<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices para los filtros y el orden del catálogo de fichas.
     */
    public function up(): void
    {
        Schema::table('person_records', function (Blueprint $table): void {
            $table->index('sex');
            $table->index('disappearance_status');
            $table->index('nationality');
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('person_records', function (Blueprint $table): void {
            $table->dropIndex(['sex']);
            $table->dropIndex(['disappearance_status']);
            $table->dropIndex(['nationality']);
            $table->dropIndex(['name']);
        });
    }
};
