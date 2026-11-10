<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Solicitud a soporte para reactivar una cuenta suspendida o baneada
     * por strikes. El acceso solo vuelve si un administrador la aprueba.
     */
    public function up(): void
    {
        Schema::create('solicitudes_reactivacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('mensaje');
            $table->enum('estado', ['pendiente', 'aprobada', 'rechazada'])->default('pendiente');
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('respuesta')->nullable();
            $table->timestamp('resuelta_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_reactivacion');
    }
};
