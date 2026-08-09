<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Moderacion;
use Illuminate\Http\Request;

class ModeracionController extends Controller
{
    public function index() {
        return response()->json(
            Moderacion::with(['propiedad', 'user'])->latest()->get()
        );
    }

    public function store(Request $request) {
        $data = $request->validate([
            'propiedad_id' => 'required|exists:propiedades,id',
            'user_id' => 'required|exists:users,id',
            'tipo' => 'required|in:imagen,texto',
            'resultado' => 'required|in:aprobado,sospechoso,rechazado',
            'score' => 'nullable|numeric',
            'etiquetas_detectadas' => 'nullable|array',
            'motivo' => 'nullable|string',
            'proveedor_ia' => 'nullable|string',
            'estado' => 'nullable|in:pendiente,revisado'
        ]);

        $data['estado'] = $data['estado'] ?? 'pendiente';

        return response()->json(Moderacion::create($data), 201);
    }

    public function show(Moderacion $moderacion) {
        return response()->json(
            $moderacion->load(['propiedad', 'user', 'revisiones'])
        );
    }

    public function update(Request $request, Moderacion $moderacion) {
        $data = $request->validate([
            'resultado' => 'sometimes|in:aprobado,sospechoso,rechazado',
            'score' => 'nullable|numeric',
            'etiquetas_detectadas' => 'nullable|array',
            'motivo' => 'nullable|string',
            'proveedor_ia' => 'nullable|string',
            'estado' => 'sometimes|in:pendiente,revisado'
        ]);

        $moderacion->update($data);

        return response()->json($moderacion);
    }

    public function destroy(Moderacion $moderacion) {
        $moderacion->delete();

        return response()->json(['message' => 'Moderación eliminada']);
    }
}