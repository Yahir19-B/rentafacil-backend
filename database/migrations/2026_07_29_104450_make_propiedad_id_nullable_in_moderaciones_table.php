<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un rechazo de texto/imagen al PUBLICAR una propiedad ocurre
     * antes de que la propiedad exista, así que propiedad_id debe
     * poder quedar vacío (igual que se hizo con sanciones.admin_id).
     */
    public function up(): void
    {
        Schema::table('moderaciones', function (Blueprint $table) {
            $table->foreignId('propiedad_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('moderaciones', function (Blueprint $table) {
            $table->foreignId('propiedad_id')->nullable(false)->change();
        });
    }
};
