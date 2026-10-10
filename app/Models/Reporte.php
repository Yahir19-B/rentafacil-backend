<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    //
    protected $fillable = [
        'user_id',
        'propiedad_id',
        'motivo',
        'descripcion',
        'evidencias',
        'estado'
    ];

    protected $casts = [
        'evidencias' => 'array',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function propiedad() {
        return $this->belongsTo(Propiedad::class);
    }
}
