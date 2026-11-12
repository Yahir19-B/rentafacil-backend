<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Archivos multimedia del mensaje (imágenes, audio y video en
     * Firebase Storage, hasta 5). El texto pasa a ser opcional cuando
     * hay adjuntos.
     */
    public function up(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->text('mensaje')->nullable()->change();
            $table->json('adjuntos')->nullable()->after('mensaje');
        });
    }

    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {
            $table->dropColumn('adjuntos');
        });
    }
};
