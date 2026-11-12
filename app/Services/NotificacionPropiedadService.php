<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Propiedad;
use App\Models\User;

/**
 * Avisa a los inquilinos cuando una propiedad nueva queda aprobada y
 * visible: a quien guardó preferencias de búsqueda solo si coincide con
 * ellas, y a quien no tiene preferencias siempre.
 */
class NotificacionPropiedadService
{
    public function notificarNuevaPublicacion(Propiedad $propiedad): void
    {
        $recomendaciones = app(RecomendacionService::class);

        // Quien guardó preferencias solo recibe los inmuebles que coinciden;
        // quien no tiene preferencias recibe todos, como antes.
        $inquilinos = User::whereHas('role', fn ($q) => $q->where('nombre', 'inquilino'))
            ->where('id', '!=', $propiedad->user_id)
            ->get(['id', 'preferencias_busqueda'])
            ->filter(function (User $inquilino) use ($recomendaciones, $propiedad) {
                $preferencias = $recomendaciones->preferencias($inquilino);

                return !$recomendaciones->tienePreferencias($preferencias)
                    || $recomendaciones->coincide($propiedad, $preferencias);
            });

        $ahora = now();
        $coincide = fn (User $inquilino) => $recomendaciones->tienePreferencias($recomendaciones->preferencias($inquilino));

        $notificaciones = $inquilinos->map(fn ($inquilino) => [
            'user_id' => $inquilino->id,
            'tipo' => 'nueva_propiedad',
            'titulo' => $coincide($inquilino) ? 'Nuevo inmueble que coincide con tus preferencias' : 'Nueva propiedad disponible',
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
