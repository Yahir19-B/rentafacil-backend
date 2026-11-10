<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Renta marcada por el propietario y confirmada (o no) por el inquilino.
 */
class Renta extends Model
{
    protected $table = 'rentas';

    protected $fillable = [
        'propiedad_id',
        'propietario_id',
        'inquilino_id',
        'conversacion_id',
        'estado',
        'respondida_at',
    ];

    protected $casts = [
        'respondida_at' => 'datetime',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class);
    }

    public function propietario()
    {
        return $this->belongsTo(User::class, 'propietario_id');
    }

    public function inquilino()
    {
        return $this->belongsTo(User::class, 'inquilino_id');
    }
}
