<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Veredicto del caso de uso "Gestionar reportes (Administrador)":
     * qué decidió el admin, quién lo decidió y cuándo.
     */
    public function up(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->enum('veredicto', ['exonerado', 'advertido', 'suspendido', 'baneado'])
                ->nullable()
                ->after('estado');
            $table->foreignId('admin_id')
                ->nullable()
                ->after('veredicto')
                ->constrained('users')
                ->nullOnDelete();
            $table->text('comentario_admin')->nullable()->after('admin_id');
            $table->timestamp('resuelto_at')->nullable()->after('comentario_admin');
        });
    }

    public function down(): void
    {
        Schema::table('reportes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_id');
            $table->dropColumn(['veredicto', 'comentario_admin', 'resuelto_at']);
        });
    }
};
