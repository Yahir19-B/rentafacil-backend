<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Mensaje;
use App\Models\Conversacion;
use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
            // El texto es opcional si el mensaje lleva archivos.
            'mensaje' => 'required_without:adjuntos|nullable|string|max:1000',
            'adjuntos' => 'nullable|array|max:' . Mensaje::MAX_ADJUNTOS,
            'adjuntos.*.url' => ['required', 'string', 'max:2048', 'starts_with:https://firebasestorage.googleapis.com/'],
            'adjuntos.*.path' => 'required|string|max:1000',
            'adjuntos.*.tipo' => ['required', Rule::in(array_keys(Mensaje::LIMITES_ADJUNTOS))],
            'adjuntos.*.mime' => 'required|string|max:100',
            'adjuntos.*.nombre' => 'nullable|string|max:255',
            'adjuntos.*.tamano' => 'required|integer|min:1',
            'adjuntos.*.duracion' => 'nullable|numeric|min:0|max:' . Mensaje::MAX_SEGUNDOS,
        ], [
            'mensaje.required_without' => 'Escribe un mensaje o adjunta un archivo.',
            'adjuntos.max' => 'Puedes enviar hasta ' . Mensaje::MAX_ADJUNTOS . ' archivos por mensaje.',
            'adjuntos.*.duracion.max' => 'Los audios y videos pueden durar como máximo 5 minutos.',
        ]);

        if ($error = $this->validarAdjuntos($data['adjuntos'] ?? [])) {
            return response()->json(['message' => $error, 'errors' => ['adjuntos' => [$error]]], 422);
        }

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
            'mensaje' => trim($data['mensaje'] ?? '') ?: null,
            'adjuntos' => $this->normalizarAdjuntos($data['adjuntos'] ?? []),
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

    /** Revisa que cada archivo cumpla el formato y el tamaño de su tipo. */
    private function validarAdjuntos(array $adjuntos): ?string
    {
        foreach ($adjuntos as $adjunto) {
            $limite = Mensaje::LIMITES_ADJUNTOS[$adjunto['tipo']];

            if (!in_array($adjunto['mime'], $limite['mimes'], true)) {
                return 'Formato no permitido: usa JPG, PNG o WEBP para imágenes, MP3 para audio y MP4 o WEBM para video.';
            }

            if ($adjunto['tamano'] > $limite['max_bytes']) {
                $mb = $limite['max_bytes'] / 1024 / 1024;

                return "Los archivos de tipo {$adjunto['tipo']} pueden pesar como máximo {$mb} MB.";
            }
        }

        return null;
    }

    private function normalizarAdjuntos(array $adjuntos): ?array
    {
        if (!$adjuntos) {
            return null;
        }

        return array_map(fn (array $adjunto) => [
            'url' => $adjunto['url'],
            'path' => $adjunto['path'],
            'tipo' => $adjunto['tipo'],
            'mime' => $adjunto['mime'],
            'nombre' => $adjunto['nombre'] ?? null,
            'tamano' => (int) $adjunto['tamano'],
            'duracion' => isset($adjunto['duracion']) ? (float) $adjunto['duracion'] : null,
        ], array_values($adjuntos));
    }
}
