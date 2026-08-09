<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('tipo', ['imagen', 'texto']);
            $table->enum('resultado', ['aprobado', 'sospechoso', 'rechazado']);
            $table->decimal('score', 5, 2)->nullable();
            $table->json('etiquetas_detectadas')->nullable();
            $table->text('motivo')->nullable();
            $table->string('proveedor_ia', 100)->nullable();
            $table->enum('estado', ['pendiente', 'revisado'])->default('pendiente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderaciones');
    }
};
