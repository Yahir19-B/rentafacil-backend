<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Moderacion extends Model
{
    protected $table = 'moderaciones';

    protected $fillable = [
        'propiedad_id',
        'user_id',
        'tipo',
        'resultado',
        'score',
        'etiquetas_detectadas',
        'motivo',
        'proveedor_ia',
        'estado',
    ];

    protected $casts = [
        'etiquetas_detectadas' => 'array',
        'score' => 'decimal:2',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function revisiones()
    {
        return $this->hasMany(RevisionAdmin::class);
    }
}
