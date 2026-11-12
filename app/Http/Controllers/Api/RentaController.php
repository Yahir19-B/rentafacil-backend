<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversacion;
use App\Models\Notificacion;
use App\Models\Propiedad;
use App\Models\Renta;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Proceso "Marcar como rentado":
 * 1-2. El inquilino contacta al propietario por la mensajería.
 * 3-4. El propietario marca la publicación como rentada (en la
 *      conversación o en la publicación) indicando al inquilino.
 * 5-6. El inquilino recibe la solicitud y confirma si rentó o no.
 * 7.   El sistema registra el resultado.
 * 8.   Si ambos confirman, la publicación pasa a "Rentado" y se
 *      habilita calificar la experiencia con el propietario.
 */
class RentaController extends Controller
{
    /**
     * Personas con las que el propietario ha conversado: son a quienes
     * puede marcar como inquilino de la publicación.
     */
    public function candidatos(Request $request, Propiedad $propiedad): JsonResponse
    {
        if ($propiedad->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Esta publicación no es tuya.'], 403);
        }

        $yo = $request->user()->id;

        $conversaciones = Conversacion::where(fn ($q) => $q->where('user_uno_id', $yo)->orWhere('user_dos_id', $yo))
            ->whereHas('mensajes')
            ->with(['userUno:id,name,foto_perfil', 'userDos:id,name,foto_perfil'])
            ->latest('updated_at')
            ->get();

        $candidatos = $conversaciones
            ->map(fn (Conversacion $c) => [
                'conversacion_id' => $c->id,
                'sobre_esta_publicacion' => $c->propiedad_id === $propiedad->id,
                'usuario' => $c->user_uno_id === $yo ? $c->userDos : $c->userUno,
            ])
            ->filter(fn ($c) => $c['usuario'])
            ->unique(fn ($c) => $c['usuario']->id)
            ->sortByDesc('sobre_esta_publicacion')
            ->values();

        return response()->json($candidatos);
    }

    /** Pasos 3-5: el propietario marca la renta y se le pide confirmar al inquilino. */
    public function marcar(Request $request, Propiedad $propiedad): JsonResponse
    {
        $data = $request->validate([
            'inquilino_id' => 'required|integer|exists:users,id',
        ]);

        $propietario = $request->user();

        if ($propiedad->user_id !== $propietario->id) {
            return response()->json(['message' => 'Solo el propietario puede marcar la publicación como rentada.'], 403);
        }

        if ((int) $data['inquilino_id'] === $propietario->id) {
            return response()->json(['message' => 'Elige a la persona que rentó tu espacio.'], 422);
        }

        if ($propiedad->estado_publicacion !== 'aprobada') {
            return response()->json(['message' => 'Solo puedes marcar como rentada una publicación aprobada.'], 422);
        }

        if ($propiedad->rentada_at) {
            return response()->json(['message' => 'Esta publicación ya está marcada como rentada.'], 422);
        }

        if ($propiedad->rentas()->where('estado', 'pendiente')->exists()) {
            return response()->json([
                'message' => 'Ya hay una confirmación pendiente para esta publicación. Espera la respuesta o cancélala.',
            ], 422);
        }

        // Paso 2: debe existir una conversación entre ambos en RentaFácil.
        $conversacion = $this->conversacionEntre($propietario->id, (int) $data['inquilino_id']);

        if (!$conversacion) {
            return response()->json([
                'message' => 'Solo puedes marcar como inquilino a alguien con quien hayas conversado por mensajes.',
            ], 422);
        }

        $renta = Renta::create([
            'propiedad_id' => $propiedad->id,
            'propietario_id' => $propietario->id,
            'inquilino_id' => $data['inquilino_id'],
            'conversacion_id' => $conversacion->id,
            'estado' => 'pendiente',
        ]);

        Notificacion::create([
            'user_id' => $data['inquilino_id'],
            'tipo' => 'confirmar_renta',
            'titulo' => '¿Rentaste este espacio?',
            'mensaje' => "{$propietario->name} indicó que te rentó \"{$propiedad->titulo}\". Confirma si se concretó la renta.",
            'data' => ['renta_id' => $renta->id, 'propiedad_id' => $propiedad->id, 'usuario_id' => $propietario->id],
            'leida' => false,
            'leida_at' => null,
        ]);

        return response()->json($this->conRelaciones($renta), 201);
    }

    /** Solicitudes de confirmación que le llegaron al usuario como inquilino. */
    public function pendientes(Request $request): JsonResponse
    {
        $rentas = Renta::where('inquilino_id', $request->user()->id)
            ->where('estado', 'pendiente')
            ->latest()
            ->get()
            ->map(fn (Renta $renta) => $this->conRelaciones($renta));

        return response()->json($rentas);
    }

    /** Rentas de una publicación (para el propietario). */
    public function dePropiedad(Request $request, Propiedad $propiedad): JsonResponse
    {
        if ($propiedad->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Esta publicación no es tuya.'], 403);
        }

        return response()->json(
            $propiedad->rentas()->with('inquilino:id,name,foto_perfil')->latest()->get()
        );
    }

    /** Pasos 6-8: el inquilino confirma o rechaza. */
    public function responder(Request $request, Renta $renta): JsonResponse
    {
        $data = $request->validate([
            'confirmada' => 'required|boolean',
        ]);

        if ($renta->inquilino_id !== $request->user()->id) {
            return response()->json(['message' => 'Esta solicitud no es para ti.'], 403);
        }

        if ($renta->estado !== 'pendiente') {
            return response()->json(['message' => 'Esta solicitud ya fue respondida.'], 422);
        }

        $confirmada = (bool) $data['confirmada'];
        $propiedad = $renta->propiedad;
        $inquilino = $request->user();

        DB::transaction(function () use ($renta, $confirmada, $propiedad, $inquilino) {
            $renta->update([
                'estado' => $confirmada ? 'confirmada' : 'rechazada',
                'respondida_at' => now(),
            ]);

            if ($confirmada && $propiedad) {
                // Paso 8: la publicación queda "Rentado" y deja de mostrarse como disponible.
                $propiedad->update(['disponible' => false, 'rentada_at' => now()]);
            }

            Notificacion::create([
                'user_id' => $renta->propietario_id,
                'tipo' => $confirmada ? 'renta_confirmada' : 'renta_rechazada',
                'titulo' => $confirmada ? 'Renta confirmada' : 'La renta no se confirmó',
                'mensaje' => $confirmada
                    ? "{$inquilino->name} confirmó que rentó \"{$propiedad?->titulo}\". La publicación quedó como Rentado."
                    : "{$inquilino->name} indicó que la renta de \"{$propiedad?->titulo}\" no se concretó.",
                'data' => ['renta_id' => $renta->id, 'propiedad_id' => $renta->propiedad_id, 'usuario_id' => $inquilino->id],
                'leida' => false,
                'leida_at' => null,
            ]);
        });

        return response()->json([
            'message' => $confirmada
                ? 'Gracias por confirmar. Ya puedes calificar tu experiencia con el propietario.'
                : 'Registramos que la renta no se concretó.',
            'renta' => $this->conRelaciones($renta->fresh()),
            'puede_calificar' => $confirmada,
        ]);
    }

    /** El propietario cancela una solicitud que sigue pendiente. */
    public function cancelar(Request $request, Renta $renta): JsonResponse
    {
        if ($renta->propietario_id !== $request->user()->id) {
            return response()->json(['message' => 'Solo el propietario puede cancelar esta solicitud.'], 403);
        }

        if ($renta->estado !== 'pendiente') {
            return response()->json(['message' => 'Esta solicitud ya fue respondida.'], 422);
        }

        $renta->update(['estado' => 'cancelada']);

        Notificacion::where('user_id', $renta->inquilino_id)
            ->where('tipo', 'confirmar_renta')
            ->where('data->renta_id', $renta->id)
            ->delete();

        return response()->json(['message' => 'Solicitud cancelada.']);
    }

    private function conversacionEntre(int $a, int $b): ?Conversacion
    {
        return Conversacion::where(fn ($q) => $q->where('user_uno_id', $a)->where('user_dos_id', $b))
            ->orWhere(fn ($q) => $q->where('user_uno_id', $b)->where('user_dos_id', $a))
            ->first();
    }

    private function conRelaciones(Renta $renta): Renta
    {
        return $renta->load([
            'propiedad:id,titulo,ciudad,precio,rentada_at,disponible',
            'propiedad.imagenes' => fn ($q) => $q->orderBy('orden')->limit(1),
            'propietario:id,name,foto_perfil',
            'inquilino:id,name,foto_perfil',
        ]);
    }
}
