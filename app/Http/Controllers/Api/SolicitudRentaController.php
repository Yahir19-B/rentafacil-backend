<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SolicitudRenta;
use App\Models\Propiedad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SolicitudRentaController extends Controller
{
    public function index() {
        return response()->json(
            SolicitudRenta::with(['propiedad', 'inquilino', 'propietario'])
                ->where('inquilino_id', Auth::id())
                ->orWhere('propietario_id', Auth::id())
                ->latest()
                ->get()
        );
    }

    public function store(Request $request) {
        $data = $request->validate([
            'propiedad_id' => 'required|exists:propiedades,id',
            'mensaje' => 'nullable|string'
        ]);

        $propiedad = Propiedad::findOrFail($data['propiedad_id']);

        $solicitud = SolicitudRenta::create([
            'propiedad_id' => $propiedad->id,
            'inquilino_id' => Auth::id(),
            'propietario_id' => $propiedad->user_id,
            'mensaje' => $data['mensaje'] ?? null,
            'estado' => 'pendiente'
        ]);

        return response()->json($solicitud, 201);
    }

    public function show(SolicitudRenta $solicitudRenta) {
        return response()->json(
            $solicitudRenta->load(['propiedad', 'inquilino', 'propietario'])
        );
    }

    public function update(Request $request, SolicitudRenta $solicitudRenta) {
        $data = $request->validate([
            'estado' => 'required|in:pendiente,aceptada,rechazada,cancelada'
        ]);

        $solicitudRenta->update($data);

        return response()->json($solicitudRenta);
    }

    public function destroy(SolicitudRenta $solicitudRenta) {
        $solicitudRenta->delete();

        return response()->json(['message' => 'Solicitud eliminada']);
    }
}