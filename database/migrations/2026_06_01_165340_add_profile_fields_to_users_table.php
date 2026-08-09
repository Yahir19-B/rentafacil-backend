<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->nullable()->after('password');
            $table->string('phone_extra', 20)->nullable()->after('phone');
            $table->enum('sexo', ['masculino', 'femenino', 'otro'])->nullable()->after('phone_extra');
            $table->date('fecha_nacimiento')->nullable()->after('sexo');
            $table->boolean('es_estudiante')->default(false)->after('fecha_nacimiento');
            $table->text('foto_perfil')->nullable()->after('es_estudiante');
            $table->foreignId('role_id')->nullable()->after('foto_perfil')->constrained('roles')->nullOnDelete();
            $table->enum('status', ['activo', 'suspendido', 'baneado'])->default('activo')->after('role_id');
            $table->tinyInteger('strikes')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'phone',
                'phone_extra',
                'sexo',
                'fecha_nacimiento',
                'es_estudiante',
                'foto_perfil',
                'role_id',
                'status',
                'strikes',
            ]);
        });
    }
};
