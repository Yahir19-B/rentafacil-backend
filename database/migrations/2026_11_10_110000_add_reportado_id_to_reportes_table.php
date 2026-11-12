<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un reporte apunta a una publicación (propiedad_id) o a un usuario
     * (reportado_id), por eso propiedad_id pasa a ser opcional.
     */
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->foreignId('propiedad_id')->nullable()->change();

            $table->foreignId('reportado_id')
                ->nullable()
                ->after('propiedad_id')
                ->constrained('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reportado_id');
        });
    }
};
