<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\Propiedad;
use App\Services\StrikeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Listado y moderación libre de propiedades desde el panel admin:
 * a diferencia de "Revisión de contenido" (solo imágenes marcadas
 * como sospechosas), aquí el admin ve TODAS las publicaciones sin
 * importar su estado y puede banear o eliminar cualquiera en
 * cualquier momento.
 */
class AdminPropiedadController extends Controller
{
    public function __construct(
        private readonly StrikeService $strikeService,
    ) {
    }

    public function index(Request $request)
    {
        $query = Propiedad::with(['user', 'categoria', 'imagenes'])
            ->withCount('reportes');

        if ($request->filled('buscar')) {
            $texto = trim($request->input('buscar'));

            $query->where(function ($q) use ($texto) {
                $q->where('titulo', 'like', "%{$texto}%")
                    ->orWhere('ciudad', 'like', "%{$texto}%");
            });
        }

        if ($request->filled('estado_publicacion')) {
            $query->where('estado_publicacion', $request->input('estado_publicacion'));
        }

        $propiedades = $query->latest()->get();

        return response()->json($propiedades);
    }

    public function banear(Request $request, Propiedad $propiedad)
    {
        $data = $request->validate([
            'motivo' => 'required|string|max:1000',
        ]);

        if ($propiedad->estado_publicacion === 'rechazada') {
            return response()->json([
                'message' => 'Esta publicación ya está rechazada.'
            ], 422);
        }

        $propiedad->update([
            'estado_publicacion' => 'rechazada',
            'motivo_rechazo' => $data['motivo'],
        ]);

        $strike = $this->strikeService->registrarStrike(
            $propiedad->user,
            $data['motivo'],
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

        return response()->json($propiedad->fresh(['user', 'categoria', 'imagenes']));
    }
}
