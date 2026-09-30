<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'person_records_search_fulltext';

    /**
     * Run the migrations.
     *
     * El texto completo solo existe en MySQL; en otras bases (SQLite en las
     * pruebas) la búsqueda usa LIKE. La entidad va en el índice para que
     * «jalisco» o «nuevo leon» encuentren a las fichas de ese estado.
     */
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('person_records', function (Blueprint $table) {
            $table->fullText(['name', 'municipality', 'description', 'state'], self::INDEX);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('person_records', function (Blueprint $table) {
            $table->dropFullText(self::INDEX);
        });
    }
};
