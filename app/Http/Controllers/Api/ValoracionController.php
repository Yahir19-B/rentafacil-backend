<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\User;
use App\Models\Valoracion;
use App\Services\FiltroPalabrasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Valorar de una a cinco estrellas a otros usuarios (cualquiera menos a
 * uno mismo, con sesión iniciada) y mostrar las valoraciones recibidas
 * en el perfil.
 */
class ValoracionController extends Controller
{
    /** Resumen (promedio, total, distribución) y últimas valoraciones de un usuario. */
    public function index(Request $request, User $usuario): JsonResponse
    {
        $valoraciones = $usuario->valoracionesRecibidas()
            ->with('calificador:id,name,foto_perfil')
            ->latest()
            ->get();

        $total = $valoraciones->count();

        $distribucion = collect(range(5, 1))
            ->mapWithKeys(fn ($estrellas) => [$estrellas => $valoraciones->where('estrellas', $estrellas)->count()]);

        return response()->json([
            'promedio' => $total ? round($valoraciones->avg('estrellas'), 1) : null,
            'total' => $total,
            'distribucion' => $distribucion,
            'puede_valorar' => $this->puedeValorar($request->user(), $usuario) === null,
            'mi_valoracion' => $valoraciones->firstWhere('calificador_id', $request->user()->id),
            'valoraciones' => $valoraciones->take(50)->values(),
        ]);
    }

    /** Crea o actualiza la valoración del usuario autenticado. */
    public function store(Request $request, User $usuario, FiltroPalabrasService $filtro): JsonResponse
    {
        $data = $request->validate([
            'estrellas' => 'required|integer|min:1|max:5',
            'comentario' => 'nullable|string|max:250',
        ], [
            'estrellas.required' => 'Elige de 1 a 5 estrellas.',
            'estrellas.min' => 'Elige de 1 a 5 estrellas.',
            'estrellas.max' => 'Elige de 1 a 5 estrellas.',
            'comentario.max' => 'El comentario puede tener como máximo 250 caracteres.',
        ]);

        if ($error = $this->puedeValorar($request->user(), $usuario)) {
            return response()->json(['message' => $error], 422);
        }

        $comentario = trim($data['comentario'] ?? '') ?: null;

        if ($comentario && !$filtro->esValido($comentario)) {
            return response()->json([
                'message' => 'El comentario contiene lenguaje no permitido.',
                'errors' => ['comentario' => ['El comentario contiene lenguaje no permitido.']],
            ], 422);
        }

        $valoracion = Valoracion::updateOrCreate(
            ['calificador_id' => $request->user()->id, 'calificado_id' => $usuario->id],
            ['estrellas' => $data['estrellas'], 'comentario' => $comentario]
        );

        if ($valoracion->wasRecentlyCreated) {
            Notificacion::create([
                'user_id' => $usuario->id,
                'tipo' => 'nueva_valoracion',
                'titulo' => 'Recibiste una valoración',
                'mensaje' => "{$request->user()->name} te valoró con {$data['estrellas']} "
                    . ($data['estrellas'] === 1 ? 'estrella' : 'estrellas') . '.',
                'data' => ['usuario_id' => $request->user()->id, 'valoracion_id' => $valoracion->id],
                'leida' => false,
                'leida_at' => null,
            ]);
        }

        return response()->json(
            $valoracion->load('calificador:id,name,foto_perfil'),
            $valoracion->wasRecentlyCreated ? 201 : 200
        );
    }

    /** Elimina la valoración que el usuario autenticado le dio a $usuario. */
    public function destroy(Request $request, User $usuario): JsonResponse
    {
        $borradas = Valoracion::where('calificador_id', $request->user()->id)
            ->where('calificado_id', $usuario->id)
            ->delete();

        if (!$borradas) {
            return response()->json(['message' => 'No has valorado a este usuario.'], 404);
        }

        return response()->json(['message' => 'Valoración eliminada.']);
    }

    /** Devuelve el motivo por el que no se puede valorar, o null si sí se puede. */
    private function puedeValorar(User $yo, User $usuario): ?string
    {
        // Cualquier usuario con sesión puede calificar a cualquier otro,
        // excepto su propia cuenta.
        if ($yo->id === $usuario->id) {
            return 'No puedes valorarte a ti mismo.';
        }

        return null;
    }
}
