<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Video del espacio en renta (MP4/WEBM, hasta 50 MB) guardado en
     * Firebase Storage. Uno por publicación; se puede quitar al editar.
     */
    public function up(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->string('video_url', 2048)->nullable()->after('servicios');
            $table->string('video_path', 1000)->nullable()->after('video_url');
        });
    }

    public function down(): void
    {
        Schema::table('propiedades', function (Blueprint $table) {
            $table->dropColumn(['video_url', 'video_path']);
        });
    }
};
