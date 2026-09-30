<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * La tabla es un resumen de unas 60 mil filas que las estadísticas suman una
     * y otra vez. Con estos índices cada suma se resuelve leyendo solo el índice
     * (sin ir a cada fila), entre 4 y 8 veces más rápido.
     */
    public function up(): void
    {
        Schema::table('registry_counts', function (Blueprint $table) {
            // Totales por estado, con y sin confidenciales.
            $table->index(['state', 'confidential', 'total'], 'registry_counts_state_confidential_total_index');
            // Histórico mensual y totales por estado dentro de un periodo.
            $table->index(['month', 'state', 'total'], 'registry_counts_month_state_total_index');
            // Sexo, edad y estatus de los registros que los informan, con o sin periodo.
            $table->index(['confidential', 'month', 'sex', 'age_range', 'status', 'total'], 'registry_counts_profile_index');
            // Sustituye al índice solo por mes: el de arriba ya lo cubre.
            $table->dropIndex('registry_counts_month_index');
            // Fecha de la última importación sin recorrer la tabla.
            $table->index('updated_at', 'registry_counts_updated_at_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registry_counts', function (Blueprint $table) {
            $table->index('month', 'registry_counts_month_index');
            $table->dropIndex('registry_counts_month_state_total_index');
            $table->dropIndex('registry_counts_profile_index');
            $table->dropIndex('registry_counts_state_confidential_total_index');
            $table->dropIndex('registry_counts_updated_at_index');
        });
    }
};
