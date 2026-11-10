<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Aviso;
use App\Models\Moderacion;
use App\Services\FiltroPalabrasService;
use App\Services\ModeracionImagenService;
use App\Services\StrikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avisos del muro: publicaciones de texto (con imagen opcional) que el
 * usuario hace en su propio perfil. Pasan por el mismo filtro de
 * lenguaje que las propiedades y comentarios.
 */
class AvisoController extends Controller
{
    public function __construct(
        private readonly FiltroPalabrasService $filtroPalabras,
        private readonly ModeracionImagenService $moderacionImagen,
        private readonly StrikeService $strikeService,
    ) {
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'contenido' => 'required|string|max:1000',
            'imagen_url' => 'nullable|string|max:2048',
            'imagen_path' => 'nullable|string|max:1000',
        ]);

        $usuario = $request->user();
        $contenido = trim($data['contenido']);

        if ($contenido === '') {
            return response()->json([
                'message' => 'Escribe el contenido del aviso.',
            ], 422);
        }

        $palabrasEncontradas = $this->filtroPalabras->contieneMalasPalabras($contenido);

        if (!empty($palabrasEncontradas)) {
            $motivo = 'Lenguaje no permitido en un aviso del muro.';
            $resultado = $this->strikeService->registrarStrike($usuario, $motivo);

            Moderacion::create([
                'propiedad_id' => null,
                'user_id' => $usuario->id,
                'tipo' => 'texto',
                'resultado' => 'rechazado',
                'etiquetas_detectadas' => $palabrasEncontradas,
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

        if (!empty($data['imagen_url'])) {
            $analisis = $this->moderacionImagen->analizarImagen($data['imagen_url']);

            if (!$analisis['aprobada']) {
                return response()->json([
                    'message' => 'La imagen parece tener contenido inapropiado. Elige otra imagen.',
                    'imagen_rechazada' => true,
                ], 422);
            }
        }

        $aviso = Aviso::create([
            'user_id' => $usuario->id,
            'contenido' => $contenido,
            'imagen_url' => $data['imagen_url'] ?? null,
            'imagen_path' => $data['imagen_path'] ?? null,
        ]);

        return response()->json($aviso, 201);
    }

    public function destroy(Request $request, Aviso $aviso): JsonResponse
    {
        $usuario = $request->user()->loadMissing('role');

        if ($aviso->user_id !== $usuario->id && $usuario->role?->nombre !== 'admin') {
            return response()->json([
                'message' => 'No puedes eliminar este aviso.',
            ], 403);
        }

        $aviso->delete();

        return response()->json([
            'message' => 'Aviso eliminado.',
            'imagen_path' => $aviso->imagen_path,
        ]);
    }
}
