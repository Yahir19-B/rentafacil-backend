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
        ];
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
