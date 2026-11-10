<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mensaje extends Model
{
    protected $fillable = [
        'conversacion_id',
        'emisor_id',
        'receptor_id',
        'propiedad_id',
        'mensaje',
        'adjuntos',
        'leido',
        'leido_at',
    ];

    /**
     * Límites de archivos multimedia por mensaje (requerimientos):
     * audio MP3 10 MB / 5 min, video MP4-WEBM 50 MB / 5 min,
     * imagen JPG-PNG-WEBP 10 MB, hasta 5 archivos.
     */
    public const MAX_ADJUNTOS = 5;
    public const MAX_SEGUNDOS = 300;
    public const LIMITES_ADJUNTOS = [
        'imagen' => ['mimes' => ['image/jpeg', 'image/png', 'image/webp'], 'max_bytes' => 10 * 1024 * 1024],
        'audio' => ['mimes' => ['audio/mpeg', 'audio/mp3'], 'max_bytes' => 10 * 1024 * 1024],
        'video' => ['mimes' => ['video/mp4', 'video/webm'], 'max_bytes' => 50 * 1024 * 1024],
    ];

    protected $casts = [
        'adjuntos' => 'array',
        'leido' => 'boolean',
        'leido_at' => 'datetime',
    ];

    public function conversacion()
    {
        return $this->belongsTo(Conversacion::class);
    }

    public function emisor()
    {
        return $this->belongsTo(User::class, 'emisor_id');
    }

    public function receptor()
    {
        return $this->belongsTo(User::class, 'receptor_id');
    }

    public function propiedad()
    {
        return $this->belongsTo(Propiedad::class);
    }
}