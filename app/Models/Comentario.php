<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comentario extends Model
{
    protected $fillable = [
        'propiedad_id',
        'user_id',
        'parent_id',
        'comentario',
        'es_moderacion',
    ];

    protected $casts = [
        'es_moderacion' => 'boolean',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function respuestas()
    {
        return $this->hasMany(Comentario::class, 'parent_id');
    }

    public function padre()
    {
        return $this->belongsTo(Comentario::class, 'parent_id');
    }
}
