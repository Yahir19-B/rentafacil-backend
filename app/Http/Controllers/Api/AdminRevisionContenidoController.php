<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comentario;
use App\Models\Configuracion;
use App\Models\Moderacion;
use App\Models\Notificacion;
use App\Models\Sancion;
use App\Services\NotificacionPropiedadService;
use App\Services\StrikeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Cola de publicaciones cuyas imágenes fueron marcadas como
 * sospechosas (sangre, +18, violento) por Google Vision. El admin
 * resuelve cada una con uno de cuatro veredictos:
 *
 * - Exonerado: la IA se equivocó, se aprueba y publica normal.
 * - Advertido: se rechaza la publicación y se registra un strike
 *   (StrikeService decide si es solo advertencia o si ya alcanza
 *   el límite y suspende la cuenta automáticamente).
 * - Suspendido: el admin fuerza la suspensión de la cuenta por 3
 *   días de una vez, sin depender del conteo de strikes.
 * - Baneado: el admin banea la cuenta del dueño por completo.
 */
class AdminRevisionContenidoController extends Controller
{
    public function __construct(
        private readonly StrikeService $strikeService,
        private readonly NotificacionPropiedadService $notificacionPropiedad,
    ) {
    }

    public function index(Request $request)
    {
        $query = Moderacion::query()
            ->where('tipo', 'imagen')
            ->where('resultado', 'sospechoso')
            ->with(['propiedad.imagenes', 'user']);

        if ($request->input('estado', 'pendiente') !== 'todas') {
            $query->where('estado', 'pendiente');
        }

        $moderaciones = $query
            ->orderByDesc('created_at')
            ->get();

        return response()->json($moderaciones);
    }

    public function aprobar(Moderacion $moderacion)
    {
        if ($moderacion->estado !== 'pendiente') {
            return response()->json(['message' => 'Esta revisión ya fue resuelta.'], 422);
        }

        $propiedad = $moderacion->propiedad;

        if (!$propiedad) {
            return response()->json(['message' => 'La publicación ya no existe.'], 404);
        }

        $propiedad->update(['estado_publicacion' => 'aprobada']);

        $moderacion->update(['estado' => 'revisado']);

        $this->notificacionPropiedad->notificarNuevaPublicacion($propiedad);

        Notificacion::create([
            'user_id' => $propiedad->user_id,
            'tipo' => 'propiedad_aprobada',
            'titulo' => 'Tu publicación fue aprobada',
            'mensaje' => "Revisamos \"{$propiedad->titulo}\" y ya está publicada.",
            'data' => ['propiedad_id' => $propiedad->id],
            'leida' => false,
            'leida_at' => null,
        ]);

        return response()->json($moderacion->load(['propiedad.imagenes', 'user']));
    }

    public function banear(Request $request, Moderacion $moderacion)
    {
        [$propiedad, $motivo, $error] = $this->prepararRechazo($request, $moderacion);

        if ($error) {
            return $error;
        }

        $strike = $this->strikeService->registrarStrike(
            $propiedad->user,
            $motivo,
            Auth::id()
        );

        Notificacion::create([
            'user_id' => $propiedad->user_id,
            'tipo' => 'propiedad_rechazada',
            'titulo' => 'Tu publicación no cumple con las normas',
            'mensaje' => $strike['mensaje'],
            'data' => [
                'propiedad_id' => $propiedad->id,
                'strikes' => $strike['strikes'],
                'suspendido' => $strike['suspendido'],
            ],
            'leida' => false,
            'leida_at' => null,
        ]);

        return response()->json($moderacion->load(['propiedad.imagenes', 'user']));
    }

    /**
     * Suspende la cuenta del dueño por 3 días de una vez, sin importar
     * cuántos strikes lleve acumulados. Se usa cuando el admin ya sabe
     * que el dueño incumplió su límite de strikes.
     */
    public function suspender(Request $request, Moderacion $moderacion)
    {
        [$propiedad, $motivo, $error] = $this->prepararRechazo($request, $moderacion);

        if ($error) {
            return $error;
        }

        $usuario = $propiedad->user;
        $limite = Configuracion::actual()->limite_strikes;

        $usuario->update([
            'strikes' => max($usuario->strikes, $limite),
            'status' => 'suspendido',
        ]);
        $usuario->tokens()->delete();

        Sancion::create([
            'user_id' => $usuario->id,
            'admin_id' => Auth::id(),
            'tipo' => 'suspension',
            'motivo' => $motivo,
            'fecha_inicio' => now(),
            'fecha_fin' => now()->addDays(StrikeService::DIAS_SUSPENSION),
            'activa' => true,
        ]);

        Notificacion::create([
            'user_id' => $usuario->id,
            'tipo' => 'propiedad_rechazada',
            'titulo' => 'Tu cuenta fue suspendida',
            'mensaje' => "Tu publicación \"{$propiedad->titulo}\" no cumple con las normas y ya "
                . "acumulaste el límite de strikes. Tu cuenta fue suspendida por "
                . StrikeService::DIAS_SUSPENSION . ' días.',
            'data' => [
                'propiedad_id' => $propiedad->id,
                'strikes' => $usuario->strikes,
                'suspendido' => true,
            ],
            'leida' => false,
            'leida_at' => null,
        ]);

        return response()->json($moderacion->load(['propiedad.imagenes', 'user']));
    }

    /**
     * Banea la cuenta del dueño por completo (no solo rechaza la
     * publicación). Es una decisión manual del admin, sin depender
     * del sistema de strikes.
     */
    public function banearCuenta(Request $request, Moderacion $moderacion)
    {
        [$propiedad, $motivo, $error] = $this->prepararRechazo($request, $moderacion);

        if ($error) {
            return $error;
        }

        $usuario = $propiedad->user;

        $usuario->update(['status' => 'baneado']);
        $usuario->tokens()->delete();

        Sancion::create([
            'user_id' => $usuario->id,
            'admin_id' => Auth::id(),
            'tipo' => 'baneo',
            'motivo' => $motivo,
            'fecha_inicio' => now(),
            'fecha_fin' => null,
            'activa' => true,
        ]);

        Notificacion::create([
            'user_id' => $usuario->id,
            'tipo' => 'propiedad_rechazada',
            'titulo' => 'Tu cuenta fue baneada',
            'mensaje' => "Tu cuenta fue baneada por publicar contenido inapropiado en "
                . "\"{$propiedad->titulo}\".",
            'data' => ['propiedad_id' => $propiedad->id],
            'leida' => false,
            'leida_at' => null,
        ]);

        return response()->json($moderacion->load(['propiedad.imagenes', 'user']));
    }

    /**
     * Valida la moderación, arma el motivo (base + nota opcional del
     * admin) y deja la publicación como rechazada. Reutilizado por
     * banear/suspender/banearCuenta, que solo difieren en qué le pasa
     * a la cuenta del dueño.
     *
     * @return array{0: ?\App\Models\Propiedad, 1: ?string, 2: ?\Illuminate\Http\JsonResponse}
     */
    private function prepararRechazo(Request $request, Moderacion $moderacion): array
    {
        $data = $request->validate([
            'motivo_adicional' => 'nullable|string|max:1000',
        ]);

        if ($moderacion->estado !== 'pendiente') {
            return [null, null, response()->json(['message' => 'Esta revisión ya fue resuelta.'], 422)];
        }

        $propiedad = $moderacion->propiedad;

        if (!$propiedad) {
            return [null, null, response()->json(['message' => 'La publicación ya no existe.'], 404)];
        }

        $motivo = 'La publicación "' . $propiedad->titulo
            . '" incluía imágenes con contenido inapropiado (sangre, +18 o violento).';

        if (!empty($data['motivo_adicional'])) {
            $motivo .= ' ' . trim($data['motivo_adicional']);
        }

        $propiedad->update([
            'estado_publicacion' => 'rechazada',
            'motivo_rechazo' => $motivo,
        ]);

        $moderacion->update(['estado' => 'revisado']);

        // Además de guardarlo como motivo_rechazo, se deja como comentario
        // real en la publicación para que el dueño pueda responderlo ahí
        // mismo y el admin vea la respuesta en el mismo hilo. La columna
        // solo acepta 250 caracteres, así que se recorta si hace falta.
        Comentario::create([
            'propiedad_id' => $propiedad->id,
            'user_id' => Auth::id(),
            'parent_id' => null,
            'comentario' => Str::limit($motivo, 247),
            'es_moderacion' => true,
        ]);

        return [$propiedad->fresh('user'), $motivo, null];
    }
}
