<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('propiedad_imagenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('propiedad_id')->constrained('propiedades')->cascadeOnDelete();
            $table->string('firebase_path');
            $table->text('url');
            $table->integer('orden')->default(0);
            $table->enum('estado', ['en_revision', 'aprobada', 'rechazada'])->default('en_revision');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('propiedad_imagenes');
    }
};
