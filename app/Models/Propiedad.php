<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Propiedad extends Model
{
    protected $table = 'propiedades';

    protected $fillable = [
        'user_id',
        'categoria_id',
        'titulo',
        'descripcion',
        'precio',
        'deposito',
        'direccion',
        'ciudad',
        'estado',
        'codigo_postal',
        'latitud',
        'longitud',
        'restricciones',
        'servicios',
        'estado_publicacion',
        'motivo_rechazo',
        'disponible',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'deposito' => 'decimal:2',
        'latitud' => 'decimal:8',
        'longitud' => 'decimal:8',
        'restricciones' => 'array',
        'servicios' => 'array',
        'disponible' => 'boolean',
        'disponible_desde' => 'datetime',
    ];

    /**
     * Reglas automáticas del ciclo de vida de una publicación:
     * - Si entra a revisión o es rechazada, se inhabilita (no puede
     *   quedar sin aprobar pero visible como habilitada al mismo tiempo).
     * - Cada vez que se habilita, se reinicia el contador de 30 días
     *   guardando desde cuándo quedó habilitada.
     */
    protected static function booted(): void
    {
        static::saving(function (Propiedad $propiedad) {
            if (in_array($propiedad->estado_publicacion, ['en_revision', 'rechazada'], true)) {
                $propiedad->disponible = false;
            }

            if ($propiedad->isDirty('disponible') && $propiedad->disponible) {
                $propiedad->disponible_desde = now();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class);
    }

public function imagenes()
{
    return $this->hasMany(
        PropiedadImagen::class,
        'propiedad_id',
        'id'
    )
    ->orderBy('orden', 'asc')
    ->orderBy('id', 'asc');
}
    public function moderaciones()
    {
        return $this->hasMany(Moderacion::class);
    }

    public function favoritos()
    {
        return $this->hasMany(Favorito::class);
    }

    public function mensajes()
    {
        return $this->hasMany(Mensaje::class);
    }

    public function reportes()
    {
        return $this->hasMany(Reporte::class);
    }

    public function solicitudes()
    {
        return $this->hasMany(SolicitudRenta::class);
    }

    public function comentarios()
    {
        return $this->hasMany(Comentario::class);
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class);
    }

    public function vistas()
    {
        return $this->hasMany(PropiedadVista::class);
    }
}
