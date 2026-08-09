<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comentario;
use App\Models\Moderacion;
use App\Models\Notificacion;
use App\Models\Propiedad;
use App\Services\FiltroPalabrasService;
use App\Services\StrikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ComentarioController extends Controller
{
    public function __construct(
        private readonly FiltroPalabrasService $filtroPalabras,
        private readonly StrikeService $strikeService,
    ) {
    }

    private function respuestaRechazo(Request $request, array $palabras, ?int $propiedadId): JsonResponse
    {
        $motivo = 'Lenguaje no permitido en un comentario.';

        $resultado = $this->strikeService->registrarStrike($request->user(), $motivo);

        Moderacion::create([
            'propiedad_id' => $propiedadId,
            'user_id' => $request->user()->id,
            'tipo' => 'texto',
            'resultado' => 'rechazado',
            'etiquetas_detectadas' => $palabras,
            'motivo' => $motivo,
            'proveedor_ia' => 'filtro_palabras',
            'estado' => 'pendiente',
        ]);

        return response()->json([
            'message' => $resultado['mensaje'],
            'strikes' => $resultado['strikes'],
            'suspendido' => $resultado['suspendido'],
        ], 422);
    }

    public function index(Request $request)
    {
        $query = Comentario::with(['user', 'respuestas.user'])->whereNull('parent_id');

        if ($request->filled('propiedad_id')) {
            $query->where('propiedad_id', $request->propiedad_id);

            if (!$this->puedeVerModeracion($request->propiedad_id)) {
                $query->where('es_moderacion', false);
            }
        } else {
            $query->where('es_moderacion', false);
        }

        return response()->json($query->latest()->get());
    }

    /**
     * Los comentarios de moderación (el motivo que deja el admin al
     * advertir/suspender/banear una publicación) son una conversación
     * privada entre el admin y el dueño, no comentarios públicos: solo
     * ellos dos pueden verlos.
     */
    private function puedeVerModeracion(int $propiedadId): bool
    {
        $usuario = Auth::guard('sanctum')->user();

        if (!$usuario) {
            return false;
        }

        if ($usuario->role?->nombre === 'admin') {
            return true;
        }

        $propiedad = Propiedad::find($propiedadId);

        return $propiedad && $propiedad->user_id === $usuario->id;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'propiedad_id' => 'required|exists:propiedades,id',
            'parent_id' => 'nullable|exists:comentarios,id',
            'comentario' => 'required|string|max:250',
        ]);

        $palabrasEncontradas = $this->filtroPalabras->contieneMalasPalabras($data['comentario']);

        if (!empty($palabrasEncontradas)) {
            return $this->respuestaRechazo($request, $palabrasEncontradas, $data['propiedad_id']);
        }

        $data['user_id'] = Auth::id();

        if (!empty($data['parent_id'])) {
            // Una respuesta a un comentario de moderación también es privada
            // entre admin y dueño, aunque quien responda no pueda marcarla así.
            $data['es_moderacion'] = (bool) Comentario::find($data['parent_id'])?->es_moderacion;
        }

        $comentario = Comentario::create($data);

        $this->notificarComentario($comentario);

        return response()->json($comentario->load('user'), 201);
    }

    private function notificarComentario(Comentario $comentario): void
    {
        $autorId = $comentario->user_id;
        $titulo = 'Nuevo comentario';
        $mensaje = 'Alguien comentó en tu publicación.';

        if ($comentario->parent_id) {
            $destinatarioId = Comentario::find($comentario->parent_id)?->user_id;
            $titulo = 'Nueva respuesta';
            $mensaje = 'Alguien respondió a tu comentario.';
        } else {
            $destinatarioId = Propiedad::find($comentario->propiedad_id)?->user_id;
        }

        if (!$destinatarioId || $destinatarioId === $autorId) {
            return;
        }

        Notificacion::create([
            'user_id' => $destinatarioId,
            'tipo' => 'comentario',
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'data' => [
                'comentario_id' => $comentario->id,
                'propiedad_id' => $comentario->propiedad_id,
                'parent_id' => $comentario->parent_id,
                'autor_id' => $autorId,
            ],
            'leida' => false,
            'leida_at' => null,
        ]);
    }

    public function show(Comentario $comentario)
    {
        return response()->json($comentario->load(['user', 'respuestas.user']));
    }

    public function update(Request $request, Comentario $comentario)
    {
        if ($comentario->user_id !== Auth::id()) {
            return response()->json(['message' => 'No puedes editar este comentario'], 403);
        }

        $data = $request->validate([
            'comentario' => 'required|string|max:250',
        ]);

        $palabrasEncontradas = $this->filtroPalabras->contieneMalasPalabras($data['comentario']);

        if (!empty($palabrasEncontradas)) {
            return $this->respuestaRechazo($request, $palabrasEncontradas, $comentario->propiedad_id);
        }

        $comentario->update($data);

        return response()->json($comentario->load('user'));
    }

    public function destroy(Comentario $comentario)
    {
        if ($comentario->user_id !== Auth::id()) {
            return response()->json(['message' => 'No puedes eliminar este comentario'], 403);
        }

        $comentario->delete();

        return response()->json(['message' => 'Comentario eliminado']);
    }
}
