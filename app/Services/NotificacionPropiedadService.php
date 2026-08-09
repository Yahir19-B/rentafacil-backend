<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Propiedad;
use App\Models\User;

/**
 * Avisa a todos los inquilinos cuando una propiedad nueva queda
 * aprobada y visible en la plataforma.
 */
class NotificacionPropiedadService
{
    public function notificarNuevaPublicacion(Propiedad $propiedad): void
    {
        $inquilinos = User::whereHas('role', fn ($q) => $q->where('nombre', 'inquilino'))
            ->where('id', '!=', $propiedad->user_id)
            ->get(['id']);

        $ahora = now();

        $notificaciones = $inquilinos->map(fn ($inquilino) => [
            'user_id' => $inquilino->id,
            'tipo' => 'nueva_propiedad',
            'titulo' => 'Nueva propiedad disponible',
            'mensaje' => "Se publicó \"{$propiedad->titulo}\" en {$propiedad->ciudad}. ¡Échale un vistazo!",
            'data' => json_encode(['propiedad_id' => $propiedad->id]),
            'leida' => false,
            'leida_at' => null,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ])->all();

        if (!empty($notificaciones)) {
            Notificacion::insert($notificaciones);
        }
    }
}
