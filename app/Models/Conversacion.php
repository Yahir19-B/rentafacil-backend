<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversacion extends Model
{
    protected $table = 'conversaciones';

    protected $fillable = [
        'propiedad_id',
        'user_uno_id',
        'user_dos_id',
    ];

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class);
    }

    public function userUno()
    {
        return $this->belongsTo(User::class, 'user_uno_id');
    }

    public function userDos()
    {
        return $this->belongsTo(User::class, 'user_dos_id');
    }

    public function mensajes()
    {
        return $this->hasMany(Mensaje::class);
    }
}