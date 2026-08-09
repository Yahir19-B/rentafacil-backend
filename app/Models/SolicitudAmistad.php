<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SolicitudAmistad extends Model
{
    protected $table = 'solicitudes_amistad';

    protected $fillable = [
        'remitente_id',
        'destinatario_id',
        'estado',
    ];

    public function remitente()
    {
        return $this->belongsTo(User::class, 'remitente_id');
    }

    public function destinatario()
    {
        return $this->belongsTo(User::class, 'destinatario_id');
    }
}
