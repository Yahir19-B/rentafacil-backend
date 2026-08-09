<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReporteController extends Controller
{
    public function index(Request $request) {
        $usuario = $request->user()->load('role');

        $query = Reporte::with(['user', 'propiedad.user']);

        if ($usuario->role?->nombre !== 'admin') {
            $query->where('user_id', $usuario->id);
        }

        return response()->json($query->latest()->get());
    }

    public function store(Request $request) {
        $data = $request->validate([
            'propiedad_id' => 'required|exists:propiedades,id',
            'motivo' => 'required|string',
            'descripcion' => 'nullable|string'
        ]);

        $data['user_id'] = Auth::id();
        $data['estado'] = 'pendiente';

        return response()->json(Reporte::create($data), 201);
    }

    public function show(Request $request, Reporte $reporte) {
        $usuario = $request->user()->load('role');

        if ($usuario->role?->nombre !== 'admin' && $reporte->user_id !== $usuario->id) {
            return response()->json(['message' => 'No puedes ver este reporte'], 403);
        }

        return response()->json(
            $reporte->load(['user', 'propiedad.user'])
        );
    }

    public function update(Request $request, Reporte $reporte) {
        $data = $request->validate([
            'estado' => 'required|in:pendiente,revisado,descartado'
        ]);

        $reporte->update($data);

        return response()->json($reporte);
    }

    public function destroy(Reporte $reporte) {
        $reporte->delete();

        return response()->json(['message' => 'Reporte eliminado']);
    }
}