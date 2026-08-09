<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropiedadImagen extends Model
{
    protected $table = 'propiedad_imagenes';

    protected $fillable = [
        'propiedad_id',
        'firebase_path',
        'url',
        'orden',
        'estado',
    ];

    protected $casts = [
        'propiedad_id' => 'integer',
        'orden' => 'integer',
    ];

    public function propiedad()
    {
        return $this->belongsTo(
            Propiedad::class,
            'propiedad_id',
            'id'
        );
    }
}