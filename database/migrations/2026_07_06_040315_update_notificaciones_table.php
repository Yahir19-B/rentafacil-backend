<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            if (!Schema::hasColumn('notificaciones', 'user_id')) {
                $table->foreignId('user_id')
                    ->after('id')
                    ->constrained('users')
                    ->onDelete('cascade');
            }

            if (!Schema::hasColumn('notificaciones', 'tipo')) {
                $table->string('tipo')->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('notificaciones', 'titulo')) {
                $table->string('titulo')->after('tipo');
            }

            if (!Schema::hasColumn('notificaciones', 'mensaje')) {
                $table->text('mensaje')->after('titulo');
            }

            if (!Schema::hasColumn('notificaciones', 'data')) {
                $table->json('data')->nullable()->after('mensaje');
            }

            if (!Schema::hasColumn('notificaciones', 'leida')) {
                $table->boolean('leida')->default(false)->after('data');
            }

            if (!Schema::hasColumn('notificaciones', 'leida_at')) {
                $table->timestamp('leida_at')->nullable()->after('leida');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn([
                'user_id',
                'tipo',
                'titulo',
                'mensaje',
                'data',
                'leida',
                'leida_at'
            ]);
        });
    }
};