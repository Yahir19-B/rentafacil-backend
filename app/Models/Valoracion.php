<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Valoración de un usuario (calificador) a un propietario (calificado).
 */
class Valoracion extends Model
{
    protected $table = 'valoraciones';

    protected $fillable = [
        'calificador_id',
        'calificado_id',
        'estrellas',
        'comentario',
    ];

    protected $casts = [
        'estrellas' => 'integer',
    ];

    public function calificador()
    {
        return $this->belongsTo(User::class, 'calificador_id');
    }

    public function calificado()
    {
        return $this->belongsTo(User::class, 'calificado_id');
    }
}
