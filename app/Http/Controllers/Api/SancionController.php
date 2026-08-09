<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sancion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SancionController extends Controller
{
    public function index() {
        return response()->json(
            Sancion::with(['user', 'admin'])->latest()->get()
        );
    }

    public function store(Request $request) {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'tipo' => 'required|in:advertencia,suspension,baneo',
            'motivo' => 'required|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'activa' => 'boolean'
        ]);

        $data['admin_id'] = Auth::id();
        $data['activa'] = $data['activa'] ?? true;

        return response()->json(Sancion::create($data), 201);
    }

    public function show(Sancion $sancion) {
        return response()->json(
            $sancion->load(['user', 'admin'])
        );
    }

    public function update(Request $request, Sancion $sancion) {
        $data = $request->validate([
            'tipo' => 'sometimes|in:advertencia,suspension,baneo',
            'motivo' => 'sometimes|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'activa' => 'boolean'
        ]);

        $sancion->update($data);

        return response()->json($sancion);
    }

    public function destroy(Sancion $sancion) {
        $sancion->delete();

        return response()->json(['message' => 'Sanción eliminada']);
    }
}