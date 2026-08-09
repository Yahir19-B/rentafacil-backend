<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {

            if (!Schema::hasColumn('mensajes', 'conversacion_id')) {
                $table->foreignId('conversacion_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('conversaciones')
                    ->onDelete('cascade');
            }

            if (!Schema::hasColumn('mensajes', 'leido')) {
                $table->boolean('leido')
                    ->default(false)
                    ->after('mensaje');
            }

            if (!Schema::hasColumn('mensajes', 'leido_at')) {
                $table->timestamp('leido_at')
                    ->nullable()
                    ->after('leido');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mensajes', function (Blueprint $table) {

            if (Schema::hasColumn('mensajes', 'conversacion_id')) {
                $table->dropForeign(['conversacion_id']);
                $table->dropColumn('conversacion_id');
            }

            if (Schema::hasColumn('mensajes', 'leido_at')) {
                $table->dropColumn('leido_at');
            }
        });
    }
};