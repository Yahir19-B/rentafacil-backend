<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Avisos: las publicaciones de texto (con imagen opcional) que un
     * usuario hace en su muro. Junto con sus propiedades, son lo único
     * que aparece en el muro.
     */
    public function up(): void
    {
        Schema::create('avisos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('contenido');
            $table->string('imagen_url', 2048)->nullable();
            $table->string('imagen_path', 1000)->nullable();
            $table->timestamps();

            // El muro siempre consulta "avisos de X, más recientes primero".
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('avisos');
    }
};
