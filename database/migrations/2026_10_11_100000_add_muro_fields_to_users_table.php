<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campos del muro público (perfil tipo Facebook):
     * - biografia: texto corto "Acerca de mí".
     * - foto_portada: URL (Firebase) de la imagen de portada del muro.
     * - ciudad: dónde vive / dónde renta el usuario.
     * - privacidad_perfil: JSON con qué datos muestra en su muro y quién
     *   puede verlo. Se guarda como JSON para poder agregar opciones
     *   nuevas sin otra migración; los valores por defecto viven en
     *   User::PRIVACIDAD_PREDETERMINADA.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('biografia', 300)->nullable()->after('foto_perfil');
            $table->string('foto_portada', 2048)->nullable()->after('biografia');
            $table->string('ciudad', 100)->nullable()->after('foto_portada');
            $table->json('privacidad_perfil')->nullable()->after('ciudad');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['biografia', 'foto_portada', 'ciudad', 'privacidad_perfil']);
        });
    }
};
