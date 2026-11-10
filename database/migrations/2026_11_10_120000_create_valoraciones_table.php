<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Valoración de 1 a 5 estrellas que un usuario le da a un propietario
     * por su experiencia al rentar o pedir informes. Una por pareja.
     */
    public function up(): void
    {
        Schema::create('valoraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('calificador_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('calificado_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('estrellas');
            $table->string('comentario', 250)->nullable();
            $table->timestamps();

            $table->unique(['calificador_id', 'calificado_id']);
            $table->index(['calificado_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('valoraciones');
    }
};
