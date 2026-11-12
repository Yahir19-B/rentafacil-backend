<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Solicitud de un usuario suspendido o baneado para que soporte
 * reactive su cuenta.
 */
class SolicitudReactivacion extends Model
{
    protected $table = 'solicitudes_reactivacion';

    protected $fillable = [
        'user_id',
        'mensaje',
        'estado',
        'admin_id',
        'respuesta',
        'resuelta_at',
    ];

    protected $casts = [
        'resuelta_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
