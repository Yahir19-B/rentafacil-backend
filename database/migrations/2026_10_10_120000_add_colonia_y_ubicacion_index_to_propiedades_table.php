<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - `colonia`: el formulario de publicación ya la pedía, pero la tabla
     *   no tenía la columna y Laravel la descartaba al validar. Ahora se
     *   guarda (y se rellena sola al colocar el pin en el mapa).
     * - Índice (latitud, longitud): el mapa del home consulta propiedades
     *   por zona; el índice acelera los filtros por rango de coordenadas.
     */
    public function up(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->string('colonia', 150)->nullable()->after('direccion');
            $table->index(['latitud', 'longitud'], 'propiedades_ubicacion_index');
        });
    }

    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->dropIndex('propiedades_ubicacion_index');
            $table->dropColumn('colonia');
        });
    }
};
