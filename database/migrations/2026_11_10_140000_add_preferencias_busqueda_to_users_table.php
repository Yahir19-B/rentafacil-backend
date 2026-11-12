<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preferencias de búsqueda: tipos de propiedad (categorías),
     * características (estudiantes, mascotas, familias, parejas) y rango
     * de precios. Se usan para recomendar y para avisar de inmuebles nuevos.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('preferencias_busqueda')->nullable()->after('privacidad_perfil');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('preferencias_busqueda');
        });
    }
};
