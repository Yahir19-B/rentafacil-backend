<?php

namespace App\Models;


use App\Notifications\RecuperarPasswordNotification;
use App\Notifications\VerificarCorreoNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'phone_extra',
        'sexo',
        'fecha_nacimiento',
        'es_estudiante',
        'foto_perfil',
        'biografia',
        'foto_portada',
        'ciudad',
        'privacidad_perfil',
        'preferencias_busqueda',
        'role_id',
        'status',
        'strikes',
        'google_id',
        'apple_id',
        'auth_provider',
        'terminos_aceptados_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'codigo_reactivacion',
        'codigo_reactivacion_expira_en',
        // Privadas: se leen por GET /preferencias-busqueda.
        'preferencias_busqueda',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'fecha_nacimiento' => 'date',
            'es_estudiante' => 'boolean',
            'strikes' => 'integer',
            'terminos_aceptados_at' => 'datetime',
            'codigo_reactivacion_expira_en' => 'datetime',
            'privacidad_perfil' => 'array',
            'preferencias_busqueda' => 'array',
        ];
    }

    /**
     * Qué muestra el usuario en su muro y quién puede verlo. Lo que el
     * usuario no haya configurado toma estos valores. Los datos de
     * contacto van ocultos por defecto.
     */
    public const PRIVACIDAD_PREDETERMINADA = [
        'visibilidad_muro' => 'todos', // 'todos' | 'amigos'
        'mostrar_email' => false,
        'mostrar_telefono' => false,
        'mostrar_ciudad' => true,
        'mostrar_estudiante' => true,
        'mostrar_miembro_desde' => true,
        'mostrar_propiedades' => true,
        'mostrar_avisos' => true,
        'mostrar_amigos' => true,
    ];

    /**
     * Privacidad efectiva: lo guardado combinado con los valores por defecto.
     */
    public function getPrivacidadAttribute(): array
    {
        return array_merge(
            self::PRIVACIDAD_PREDETERMINADA,
            array_intersect_key(
                $this->privacidad_perfil ?? [],
                self::PRIVACIDAD_PREDETERMINADA
            )
        );
    }

    /**
     * IDs de los usuarios con los que tiene una amistad aceptada.
     */
    public function idsAmigos(): array
    {
        return SolicitudAmistad::where('estado', 'aceptada')
            ->where(function ($query) {
                $query->where('remitente_id', $this->id)
                    ->orWhere('destinatario_id', $this->id);
            })
            ->get(['remitente_id', 'destinatario_id'])
            ->map(fn ($solicitud) => $solicitud->remitente_id === $this->id
                ? $solicitud->destinatario_id
                : $solicitud->remitente_id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Estado mostrado en el panel de admin: 'advertido' es un
     * derivado de status=activo con strikes>0, no una columna.
     */
    public function getEstadoVisualAttribute(): string
    {
        if ($this->status === 'baneado') {
            return 'baneado';
        }

        if ($this->status === 'suspendido') {
            return 'suspendido';
        }

        if ($this->strikes > 0) {
            return 'advertido';
        }

        return 'activo';
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerificarCorreoNotification());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RecuperarPasswordNotification($token));
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function propiedades()
    {
        return $this->hasMany(Propiedad::class);
    }

    public function avisos()
    {
        return $this->hasMany(Aviso::class);
    }

    public function favoritos()
    {
        return $this->hasMany(Favorito::class);
    }
    public function reportes()
    {
        return $this->hasMany(Reporte::class);
    }

    /**
     * Reportes recibidos sobre las propiedades de este usuario
     * (a diferencia de reportes(), que son los que ÉL presentó).
     */
    public function solicitudesReactivacion()
    {
        return $this->hasMany(SolicitudReactivacion::class);
    }

    /** Valoraciones que otros usuarios le dieron como propietario. */
    public function valoracionesRecibidas()
    {
        return $this->hasMany(Valoracion::class, 'calificado_id');
    }

    /** Reportes hechos directamente contra este usuario (no a sus publicaciones). */
    public function reportesComoUsuario()
    {
        return $this->hasMany(Reporte::class, 'reportado_id');
    }

    public function reportesRecibidos()
    {
        return $this->hasManyThrough(Reporte::class, Propiedad::class);
    }

    public function sanciones()
    {
        return $this->hasMany(Sancion::class);
    }

    public function moderaciones()
    {
        return $this->hasMany(Moderacion::class);
    }

    public function comentarios()
    {
        return $this->hasMany(Comentario::class);
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class);
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class);
    }

    public function mensajesEnviados()
    {
        return $this->hasMany(Mensaje::class, 'emisor_id');
    }

    public function mensajesRecibidos()
    {
        return $this->hasMany(Mensaje::class, 'receptor_id');
    }

    public function conversacionesComoUsuarioUno()
    {
        return $this->hasMany(Conversacion::class, 'user_uno_id');
    }

    public function conversacionesComoUsuarioDos()
    {
        return $this->hasMany(Conversacion::class, 'user_dos_id');
    }

    public function solicitudesAmistadEnviadas()
    {
        return $this->hasMany(SolicitudAmistad::class, 'remitente_id');
    }

    public function solicitudesAmistadRecibidas()
    {
        return $this->hasMany(SolicitudAmistad::class, 'destinatario_id');
    }
}
