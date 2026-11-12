<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proceso "Marcar como rentado": el propietario marca la renta con un
     * inquilino con quien conversó, el inquilino la confirma o la rechaza,
     * y si ambos confirman la publicación pasa a "Rentado" y se habilita
     * calificar al propietario.
     */
    public function up(): void
    {
        Schema::create('rentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('propietario_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('inquilino_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('conversacion_id')->nullable()->constrained('conversaciones')->nullOnDelete();
            $table->enum('estado', ['pendiente', 'confirmada', 'rechazada', 'cancelada'])->default('pendiente');
            $table->timestamp('respondida_at')->nullable();
            $table->timestamps();

            $table->index(['inquilino_id', 'estado']);
            $table->index(['propiedad_id', 'estado']);
        });

        Schema::table('propiedades', function (Blueprint $table) {
            $table->timestamp('rentada_at')->nullable()->after('disponible_desde');
        });
    }

    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->dropColumn('rentada_at');
        });

        Schema::dropIfExists('rentas');
    }
};
