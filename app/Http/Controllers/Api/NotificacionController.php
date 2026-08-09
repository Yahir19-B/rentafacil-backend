<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    public function index()
    {
        $notificaciones = Notificacion::where('user_id', Auth::id())
            ->latest()
            ->get();

        return response()->json($notificaciones);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'tipo' => 'nullable|string|max:100',
            'titulo' => 'required|string|max:150',
            'mensaje' => 'required|string',
            'data' => 'nullable|array',
            'leida' => 'nullable|boolean',
        ]);

        $data['leida'] = $data['leida'] ?? false;

        $notificacion = Notificacion::create($data);

        return response()->json([
            'message' => 'Notificación creada correctamente',
            'notificacion' => $notificacion
        ], 201);
    }

    public function show(Notificacion $notificacion)
    {
        if ($notificacion->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'No tienes permiso para ver esta notificación'
            ], 403);
        }

        return response()->json($notificacion);
    }

    public function update(Request $request, Notificacion $notificacion)
    {
        if ($notificacion->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'No tienes permiso para modificar esta notificación'
            ], 403);
        }

        $data = $request->validate([
            'leida' => 'nullable|boolean',
        ]);

        if (isset($data['leida']) && $data['leida'] === true) {
            $data['leida_at'] = now();
        }

        if (isset($data['leida']) && $data['leida'] === false) {
            $data['leida_at'] = null;
        }

        $notificacion->update($data);

        return response()->json([
            'message' => 'Notificación actualizada correctamente',
            'notificacion' => $notificacion
        ]);
    }

    public function destroy(Notificacion $notificacion)
    {
        if ($notificacion->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'No tienes permiso para eliminar esta notificación'
            ], 403);
        }

        $notificacion->delete();

        return response()->json([
            'message' => 'Notificación eliminada correctamente'
        ]);
    }

    public function noLeidas()
    {
        $total = Notificacion::where('user_id', Auth::id())
            ->where('leida', false)
            ->count();

        return response()->json([
            'total' => $total
        ]);
    }

    public function marcarComoLeida(Notificacion $notificacion)
    {
        if ($notificacion->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'No tienes permiso para modificar esta notificación'
            ], 403);
        }

        $notificacion->update([
            'leida' => true,
            'leida_at' => now(),
        ]);

        return response()->json([
            'message' => 'Notificación marcada como leída',
            'notificacion' => $notificacion
        ]);
    }

    public function marcarTodasComoLeidas()
    {
        Notificacion::where('user_id', Auth::id())
            ->where('leida', false)
            ->update([
                'leida' => true,
                'leida_at' => now(),
            ]);

        return response()->json([
            'message' => 'Todas las notificaciones fueron marcadas como leídas'
        ]);
    }
}