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
        Schema::create('conversaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('propiedad_id')
                ->nullable()
                ->constrained('propiedades')
                ->onDelete('cascade');

            $table->foreignId('user_uno_id')
                ->constrained('propiedades')
                ->onDelete('cascade');

            $table->foreignId('user_dos_id')
                ->constrained('users')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversaciones');
    }
};
