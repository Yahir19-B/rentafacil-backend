<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Calificacion extends Model
{
    protected $table = 'calificaciones';

    protected $fillable = [
        'propiedad_id',
        'user_id',
        'estrellas',
        'comentario',
    ];

    protected $casts = [
        'estrellas' => 'integer',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
