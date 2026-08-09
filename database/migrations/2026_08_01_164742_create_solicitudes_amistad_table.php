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
        Schema::create('solicitudes_amistad', function (Blueprint $table) {
            $table->id();

            $table->foreignId('remitente_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('destinatario_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('estado', ['pendiente', 'aceptada', 'rechazada'])
                ->default('pendiente');

            $table->timestamps();

            $table->unique(['remitente_id', 'destinatario_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('solicitudes_amistad');
    }
};
