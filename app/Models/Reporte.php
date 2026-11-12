<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Reporte extends Model
{
    /**
     * Tipos de reporte del caso de uso "Reportar publicaciones
     * directamente". "Otros" exige que el usuario escriba el motivo.
     */
    public const TIPOS = [
        'Publicación falsa',
        'Contenido violento',
        'Lenguaje ofensivo',
        'Contenido inapropiado',
        'Spam',
        'Otros',
    ];

    protected $fillable = [
        'user_id',
        'propiedad_id',
        'reportado_id',
        'motivo',
        'descripcion',
        'evidencias',
        'estado',
        'veredicto',
        'admin_id',
        'comentario_admin',
        'resuelto_at',
    ];

    /**
     * Veredictos del caso de uso "Gestionar reportes (Administrador)".
     */
    public const VEREDICTOS = [
        'exonerado',
        'advertido',
        'suspendido',
        'baneado',
    ];

    protected $casts = [
        'evidencias' => 'array',
        'resuelto_at' => 'datetime',
    ];

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function propiedad() {
        return $this->belongsTo(Propiedad::class);
    }
    /** Usuario reportado (reportes de usuario; null en los de publicación). */
    public function reportado() {
        return $this->belongsTo(User::class, 'reportado_id');
    }
    public function admin() {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
