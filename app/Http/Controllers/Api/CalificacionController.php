<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CalificacionController extends Controller
{
    public function index(Request $request)
    {
        $query = Calificacion::with(['user', 'propiedad']);

        if ($request->filled('propiedad_id')) {
            $query->where('propiedad_id', $request->propiedad_id);
        }

        return response()->json($query->latest()->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'propiedad_id' => 'required|exists:propiedades,id',
            'estrellas' => 'required|integer|min:1|max:5',
            'comentario' => 'nullable|string',
        ]);

        $calificacion = Calificacion::updateOrCreate(
            [
                'propiedad_id' => $data['propiedad_id'],
                'user_id' => Auth::id(),
            ],
            [
                'estrellas' => $data['estrellas'],
                'comentario' => $data['comentario'] ?? null,
            ]
        );

        return response()->json($calificacion->load('user'), 201);
    }

    public function show(Calificacion $calificacion)
    {
        return response()->json($calificacion->load(['user', 'propiedad']));
    }

    public function update(Request $request, Calificacion $calificacion)
    {
        if ($calificacion->user_id !== Auth::id()) {
            return response()->json(['message' => 'No puedes editar esta calificación'], 403);
        }

        $data = $request->validate([
            'estrellas' => 'sometimes|integer|min:1|max:5',
            'comentario' => 'nullable|string',
        ]);

        $calificacion->update($data);

        return response()->json($calificacion->load('user'));
    }

    public function destroy(Calificacion $calificacion)
    {
        if ($calificacion->user_id !== Auth::id()) {
            return response()->json(['message' => 'No puedes eliminar esta calificación'], 403);
        }

        $calificacion->delete();

        return response()->json(['message' => 'Calificación eliminada']);
    }
}
