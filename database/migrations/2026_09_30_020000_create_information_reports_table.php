<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Información que alguien comparte sobre una ficha de desaparecidos o sobre
     * una solicitud. Se guarda para que quien administra pueda darle seguimiento.
     */
    public function up(): void
    {
        Schema::create('information_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('person_record_id')->nullable()->constrained('person_records')->nullOnDelete();
            $table->foreignId('person_request_id')->nullable()->constrained('person_requests')->nullOnDelete();
            $table->text('message');
            $table->string('phone', 30)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('information_reports');
    }
};
