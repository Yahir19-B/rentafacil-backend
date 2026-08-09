<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mensaje;
use App\Models\Conversacion;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MensajeController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $mensajes = Mensaje::with([
                'emisor',
                'receptor',
                'propiedad',
                'conversacion'
            ])
            ->where(function ($query) use ($userId) {
                $query->where('emisor_id', $userId)
                      ->orWhere('receptor_id', $userId);
            })
            ->latest()
            ->get();

        return response()->json($mensajes);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'receptor_id' => 'required|exists:users,id',
            'propiedad_id' => 'nullable|exists:propiedades,id',
            'mensaje' => 'required|string|max:1000',
        ]);

        $emisorId = Auth::id();
        $receptorId = $data['receptor_id'];
        $propiedadId = $data['propiedad_id'] ?? null;

        if ($emisorId == $receptorId) {
            return response()->json([
                'message' => 'No puedes enviarte mensajes a ti mismo'
            ], 422);
        }

        // Una sola conversación por par de usuarios, sin importar cuántas
        // propiedades distintas se hayan mencionado a lo largo de los mensajes.
        $conversacion = Conversacion::where(function ($query) use ($emisorId, $receptorId) {
                $query->where(function ($q) use ($emisorId, $receptorId) {
                    $q->where('user_uno_id', $emisorId)
                      ->where('user_dos_id', $receptorId);
                })
                ->orWhere(function ($q) use ($emisorId, $receptorId) {
                    $q->where('user_uno_id', $receptorId)
                      ->where('user_dos_id', $emisorId);
                });
            })
            ->first();

        if (!$conversacion) {
            $conversacion = Conversacion::create([
                'propiedad_id' => $propiedadId,
                'user_uno_id' => $emisorId,
                'user_dos_id' => $receptorId,
            ]);
        }

        $mensaje = Mensaje::create([
            'conversacion_id' => $conversacion->id,
            'emisor_id' => $emisorId,
            'receptor_id' => $receptorId,
            'propiedad_id' => $propiedadId,
            'mensaje' => $data['mensaje'],
            'leido' => false,
            'leido_at' => null,
        ]);

        Notificacion::create([
            'user_id' => $receptorId,
            'tipo' => 'mensaje',
            'titulo' => 'Nuevo mensaje',
            'mensaje' => $propiedadId
                ? 'Tienes un nuevo mensaje sobre una propiedad'
                : 'Tienes un nuevo mensaje',
            'data' => [
                'mensaje_id' => $mensaje->id,
                'conversacion_id' => $conversacion->id,
                'propiedad_id' => $propiedadId,
                'emisor_id' => $emisorId,
            ],
            'leida' => false,
            'leida_at' => null,
        ]);

        return response()->json([
            'message' => 'Mensaje enviado correctamente',
            'mensaje' => $mensaje->load([
                'emisor',
                'receptor',
                'propiedad',
                'conversacion'
            ])
        ], 201);
    }

    public function show(Mensaje $mensaje)
    {
        if ($mensaje->emisor_id !== Auth::id() && $mensaje->receptor_id !== Auth::id()) {
            return response()->json([
                'message' => 'No tienes permiso para ver este mensaje'
            ], 403);
        }

        return response()->json(
            $mensaje->load([
                'emisor',
                'receptor',
                'propiedad',
                'conversacion'
            ])
        );
    }

    public function update(Request $request, Mensaje $mensaje)
    {
        if ($mensaje->receptor_id !== Auth::id()) {
            return response()->json([
                'message' => 'Solo el receptor puede marcar el mensaje como leído'
            ], 403);
        }

        $data = $request->validate([
            'leido' => 'required|boolean',
        ]);

        if ($data['leido'] === true) {
            $data['leido_at'] = now();
        } else {
            $data['leido_at'] = null;
        }

        $mensaje->update($data);

        return response()->json([
            'message' => 'Mensaje actualizado correctamente',
            'mensaje' => $mensaje
        ]);
    }

    public function destroy(Mensaje $mensaje)
    {
        if ($mensaje->emisor_id !== Auth::id()) {
            return response()->json([
                'message' => 'Solo puedes eliminar los mensajes que tú enviaste'
            ], 403);
        }

        $mensaje->delete();

        return response()->json([
            'message' => 'Mensaje eliminado correctamente'
        ]);
    }

    public function mensajesPorConversacion($conversacionId)
    {
        $userId = Auth::id();

        $conversacion = Conversacion::where('id', $conversacionId)
            ->where(function ($query) use ($userId) {
                $query->where('user_uno_id', $userId)
                      ->orWhere('user_dos_id', $userId);
            })
            ->first();

        if (!$conversacion) {
            return response()->json([
                'message' => 'No tienes permiso para ver esta conversación'
            ], 403);
        }

        $mensajes = Mensaje::with([
                'emisor',
                'receptor',
                'propiedad'
            ])
            ->where('conversacion_id', $conversacionId)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json($mensajes);
    }

    public function marcarComoLeido(Mensaje $mensaje)
    {
        if ($mensaje->receptor_id !== Auth::id()) {
            return response()->json([
                'message' => 'Solo el receptor puede marcar este mensaje como leído'
            ], 403);
        }

        $mensaje->update([
            'leido' => true,
            'leido_at' => now(),
        ]);

        return response()->json([
            'message' => 'Mensaje marcado como leído',
            'mensaje' => $mensaje
        ]);
    }
}