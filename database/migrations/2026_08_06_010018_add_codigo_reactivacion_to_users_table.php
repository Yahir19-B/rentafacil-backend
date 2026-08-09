<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('codigo_reactivacion', 6)->nullable()->after('status');
            $table->timestamp('codigo_reactivacion_expira_en')->nullable()->after('codigo_reactivacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['codigo_reactivacion', 'codigo_reactivacion_expira_en']);
        });
    }
};
