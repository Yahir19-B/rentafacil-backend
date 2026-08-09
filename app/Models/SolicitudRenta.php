<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudRenta extends Model
{
    //
    protected $fillable = [
        'propiedad_id',
        'inquilino_id',
        'propietario_id',
        'mensaje',
        'estado'
    ];
    public function propiedad(){
        return $this->belongsTo(Propiedad::class);
    }
    public function inquilino(){
        return $this->belongsTo(User::class, 'inquilino_id');
    }
    public function propietario(){
        return $this->belongsTo(User::class, 'propietario_id');
    }
}
