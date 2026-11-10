<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RecomendacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * "Registrar / actualizar preferencias de búsqueda" y "Mostrar
 * publicaciones recomendadas".
 */
class PreferenciaBusquedaController extends Controller
{
    public function __construct(
        private readonly RecomendacionService $recomendaciones,
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->recomendaciones->preferencias($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'categorias' => 'present|array',
            'categorias.*' => 'integer|exists:categorias,id',
            'caracteristicas' => 'present|array',
            'caracteristicas.*' => ['string', Rule::in(RecomendacionService::CARACTERISTICAS)],
            'precio_min' => 'nullable|numeric|min:0',
            'precio_max' => 'nullable|numeric|min:0|gte:precio_min',
            'ciudad' => 'nullable|string|max:100',
        ], [
            'precio_max.gte' => 'El precio máximo debe ser mayor o igual al mínimo.',
        ]);

        $request->user()->update([
            'preferencias_busqueda' => [
                'categorias' => array_values(array_unique($data['categorias'])),
                'caracteristicas' => array_values(array_unique($data['caracteristicas'])),
                'precio_min' => $data['precio_min'] ?? null,
                'precio_max' => $data['precio_max'] ?? null,
                'ciudad' => trim($data['ciudad'] ?? '') ?: null,
            ],
        ]);

        return response()->json([
            'message' => 'Preferencias guardadas.',
            'preferencias' => $this->recomendaciones->preferencias($request->user()->fresh()),
        ]);
    }

    /** ?busquedas[]=... con las búsquedas recientes que guarda la app. */
    public function recomendadas(Request $request): JsonResponse
    {
        $request->validate([
            'busquedas' => 'nullable|array|max:10',
            'busquedas.*' => 'string|max:100',
        ]);

        return response()->json(
            $this->recomendaciones->recomendar($request->user(), $request->input('busquedas', []))
        );
    }
}
