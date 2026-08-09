<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = [
        'limite_strikes',
    ];

    protected $casts = [
        'limite_strikes' => 'integer',
    ];

    /**
     * Siempre hay una única fila de configuración; se crea con
     * los valores por defecto si todavía no existe.
     */
    public static function actual(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
