<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aviso extends Model
{
    protected $table = 'avisos';

    protected $fillable = [
        'user_id',
        'contenido',
        'imagen_url',
        'imagen_path',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
