<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->timestamp('disponible_desde')->nullable()->after('disponible');
        });

        // Las propiedades ya habilitadas conservan su fecha de creación
        // como punto de partida, en vez de reiniciar sus 30 días de golpe.
        DB::table('propiedades')
            ->where('disponible', true)
            ->update(['disponible_desde' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->dropColumn('disponible_desde');
        });
    }
};
