<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * auth_provider guarda el nombre del proveedor ("google"), así que el
     * índice único solo dejaba existir a UN usuario de Google y el login
     * con Google fallaba para todos los demás.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_auth_provider_unique');
            $table->index('auth_provider');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['auth_provider']);
            $table->unique('auth_provider');
        });
    }
};
