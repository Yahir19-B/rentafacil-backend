<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Propiedad;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReporteController extends Controller
{
    public function index(Request $request) {
        $usuario = $request->user()->load('role');

        $query = Reporte::with(['user', 'propiedad.user', 'reportado:id,name,foto_perfil']);

        if ($usuario->role?->nombre !== 'admin') {
            $query->where('user_id', $usuario->id);
        }

        // "Revisar reporte cometido": los reportes que el usuario ya hizo
        // sobre una publicación concreta.
        if ($request->filled('propiedad_id')) {
            $query->where('propiedad_id', $request->integer('propiedad_id'));
        }

        if ($request->filled('reportado_id')) {
            $query->where('reportado_id', $request->integer('reportado_id'));
        }

        return response()->json($query->latest()->get());
    }

    /**
     * "Enviar reporte" → "Validando datos del reporte" → "Finalizar proceso".
     */
    public function store(Request $request) {
        $data = $request->validate([
            // Se reporta una publicación o un usuario, no ambos.
            'propiedad_id' => 'required_without:reportado_id|prohibits:reportado_id|nullable|exists:propiedades,id',
            'reportado_id' => 'required_without:propiedad_id|nullable|exists:users,id',
            'motivo' => ['required', 'string', Rule::in(Reporte::TIPOS)],
            // El motivo escrito es obligatorio en todos los tipos.
            'descripcion' => 'required|string|min:50|max:1000',
            'evidencias' => 'nullable|array|max:5',
            'evidencias.*.url' => 'required|string|max:2048',
            'evidencias.*.firebase_path' => 'required|string|max:1000',
        ], [
            'motivo.in' => 'Selecciona un tipo de reporte válido.',
            'descripcion.required' => 'Escribe el motivo de tu reporte.',
            'descripcion.min' => 'El motivo del reporte debe tener al menos 50 caracteres.',
            'propiedad_id.required_without' => 'Indica la publicación o el usuario que quieres reportar.',
            'reportado_id.required_without' => 'Indica la publicación o el usuario que quieres reportar.',
        ]);

        $usuario = $request->user();

        if (!empty($data['propiedad_id'])) {
            $propiedad = Propiedad::findOrFail($data['propiedad_id']);

            if ($propiedad->user_id === $usuario->id) {
                return response()->json([
                    'message' => 'No puedes reportar tu propia publicación.',
                ], 422);
            }

            if ($propiedad->estado_publicacion !== 'aprobada') {
                return response()->json([
                    'message' => 'Esta publicación ya no está disponible para reportar.',
                ], 422);
            }

            $objetivo = ['propiedad_id' => $propiedad->id];
            $yaReportado = 'Ya reportaste esta publicación y tu reporte sigue en revisión.';
        } else {
            $reportado = User::with('role')->findOrFail($data['reportado_id']);

            if ($reportado->id === $usuario->id) {
                return response()->json([
                    'message' => 'No puedes reportarte a ti mismo.',
                ], 422);
            }

            if ($reportado->role?->nombre === 'admin') {
                return response()->json([
                    'message' => 'No puedes reportar a un administrador.',
                ], 422);
            }

            $objetivo = ['reportado_id' => $reportado->id];
            $yaReportado = 'Ya reportaste a este usuario y tu reporte sigue en revisión.';
        }

        $reportePendiente = Reporte::where('user_id', $usuario->id)
            ->where($objetivo)
            ->where('estado', 'pendiente')
            ->first();

        if ($reportePendiente) {
            return response()->json([
                'message' => $yaReportado,
                'reporte_id' => $reportePendiente->id,
            ], 422);
        }

        $descripcion = trim($data['descripcion']);

        // "min:50" cuenta espacios; se valida también ya recortado.
        if (mb_strlen($descripcion) < 50) {
            return response()->json([
                'message' => 'El motivo del reporte debe tener al menos 50 caracteres.',
                'errors' => ['descripcion' => ['El motivo del reporte debe tener al menos 50 caracteres.']],
            ], 422);
        }

        $reporte = Reporte::create($objetivo + [
            'user_id' => $usuario->id,
            'motivo' => $data['motivo'],
            'descripcion' => $descripcion,
            'evidencias' => $data['evidencias'] ?? [],
            'estado' => 'pendiente',
        ]);

        return response()->json(
            $reporte->load(['user', 'propiedad.user', 'reportado:id,name,foto_perfil']),
            201
        );
    }

    public function show(Request $request, Reporte $reporte) {
        $usuario = $request->user()->load('role');

        if ($usuario->role?->nombre !== 'admin' && $reporte->user_id !== $usuario->id) {
            return response()->json(['message' => 'No puedes ver este reporte'], 403);
        }

        return response()->json(
            $reporte->load(['user', 'propiedad.user', 'reportado:id,name,foto_perfil'])
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