<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Seguimiento de solicitudes: quien las publicó puede cerrarlas (resuelta o
     * dada de baja) y marcar como atendida la información que recibe.
     */
    public function up(): void
    {
        Schema::table('person_requests', function (Blueprint $table): void {
            $table->timestamp('closed_at')->nullable()->after('photo_path');
            $table->string('closed_reason', 16)->nullable()->after('closed_at');

            $table->index(['status', 'closed_at']);
        });

        Schema::table('information_reports', function (Blueprint $table): void {
            $table->timestamp('attended_at')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('person_requests', function (Blueprint $table): void {
            $table->dropIndex(['status', 'closed_at']);
            $table->dropColumn(['closed_at', 'closed_reason']);
        });

        Schema::table('information_reports', function (Blueprint $table): void {
            $table->dropColumn('attended_at');
        });
    }
};
