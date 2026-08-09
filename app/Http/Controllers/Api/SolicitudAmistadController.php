<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\SolicitudAmistad;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SolicitudAmistadController extends Controller
{
    /**
     * Envía una solicitud de amistad. Si el otro usuario ya le había
     * enviado una solicitud pendiente a este, se aceptan mutuamente
     * en automático (igual que en Facebook).
     */
    public function enviar(Request $request, User $usuario): JsonResponse
    {
        $remitente = $request->user();

        if ($usuario->id === $remitente->id) {
            return response()->json([
                'message' => 'No puedes enviarte una solicitud a ti mismo.'
            ], 422);
        }

        $existente = $this->buscarEntre($remitente->id, $usuario->id);

        if ($existente && $existente->estado === 'aceptada') {
            return response()->json([
                'message' => 'Ya son contactos.'
            ], 422);
        }

        if ($existente && $existente->estado === 'pendiente') {
            if ($existente->remitente_id === $remitente->id) {
                return response()->json([
                    'message' => 'Ya le enviaste una solicitud a este usuario.'
                ], 422);
            }

            // El otro usuario ya nos había enviado una solicitud: se acepta mutuamente.
            $existente->update(['estado' => 'aceptada']);

            Notificacion::create([
                'user_id' => $existente->remitente_id,
                'tipo' => 'solicitud_aceptada',
                'titulo' => 'Solicitud aceptada',
                'mensaje' => "{$remitente->name} aceptó tu solicitud de amistad.",
                'data' => ['usuario_id' => $remitente->id],
                'leida' => false,
                'leida_at' => null,
            ]);

            return response()->json(
                $existente->fresh(['remitente', 'destinatario'])
            );
        }

        if ($existente && $existente->estado === 'rechazada') {
            $existente->update([
                'remitente_id' => $remitente->id,
                'destinatario_id' => $usuario->id,
                'estado' => 'pendiente',
            ]);

            $this->notificarNuevaSolicitud($existente, $remitente, $usuario);

            return response()->json(
                $existente->fresh(['remitente', 'destinatario'])
            );
        }

        $solicitud = SolicitudAmistad::create([
            'remitente_id' => $remitente->id,
            'destinatario_id' => $usuario->id,
            'estado' => 'pendiente',
        ]);

        $this->notificarNuevaSolicitud($solicitud, $remitente, $usuario);

        return response()->json(
            $solicitud->fresh(['remitente', 'destinatario']),
            201
        );
    }

    public function aceptar(Request $request, SolicitudAmistad $solicitud): JsonResponse
    {
        if ($solicitud->destinatario_id !== $request->user()->id) {
            return response()->json([
                'message' => 'No puedes responder esta solicitud.'
            ], 403);
        }

        if ($solicitud->estado !== 'pendiente') {
            return response()->json([
                'message' => 'Esta solicitud ya fue respondida.'
            ], 422);
        }

        $solicitud->update(['estado' => 'aceptada']);

        Notificacion::create([
            'user_id' => $solicitud->remitente_id,
            'tipo' => 'solicitud_aceptada',
            'titulo' => 'Solicitud aceptada',
            'mensaje' => "{$request->user()->name} aceptó tu solicitud de amistad.",
            'data' => ['usuario_id' => $request->user()->id],
            'leida' => false,
            'leida_at' => null,
        ]);

        return response()->json(
            $solicitud->fresh(['remitente', 'destinatario'])
        );
    }

    public function rechazar(Request $request, SolicitudAmistad $solicitud): JsonResponse
    {
        if ($solicitud->destinatario_id !== $request->user()->id) {
            return response()->json([
                'message' => 'No puedes responder esta solicitud.'
            ], 403);
        }

        if ($solicitud->estado !== 'pendiente') {
            return response()->json([
                'message' => 'Esta solicitud ya fue respondida.'
            ], 422);
        }

        $solicitud->update(['estado' => 'rechazada']);

        Notificacion::create([
            'user_id' => $solicitud->remitente_id,
            'tipo' => 'solicitud_rechazada',
            'titulo' => 'Solicitud rechazada',
            'mensaje' => "{$request->user()->name} rechazó tu solicitud de amistad.",
            'data' => ['usuario_id' => $request->user()->id],
            'leida' => false,
            'leida_at' => null,
        ]);

        return response()->json([
            'message' => 'Solicitud rechazada.'
        ]);
    }

    /**
     * Estado de la relación entre el usuario autenticado y $usuario.
     */
    public function estado(Request $request, User $usuario): JsonResponse
    {
        $yo = $request->user()->id;

        if ($usuario->id === $yo) {
            return response()->json([
                'estado' => 'propio',
                'solicitud_id' => null,
            ]);
        }

        $registro = $this->buscarEntre($yo, $usuario->id);

        if (!$registro || $registro->estado === 'rechazada') {
            return response()->json([
                'estado' => 'ninguna',
                'solicitud_id' => null,
            ]);
        }

        if ($registro->estado === 'aceptada') {
            return response()->json([
                'estado' => 'aceptada',
                'solicitud_id' => $registro->id,
            ]);
        }

        return response()->json([
            'estado' => $registro->remitente_id === $yo
                ? 'pendiente_enviada'
                : 'pendiente_recibida',
            'solicitud_id' => $registro->id,
        ]);
    }

    private function buscarEntre(int $usuarioAId, int $usuarioBId): ?SolicitudAmistad
    {
        return SolicitudAmistad::where(function ($query) use ($usuarioAId, $usuarioBId) {
            $query->where('remitente_id', $usuarioAId)
                ->where('destinatario_id', $usuarioBId);
        })->orWhere(function ($query) use ($usuarioAId, $usuarioBId) {
            $query->where('remitente_id', $usuarioBId)
                ->where('destinatario_id', $usuarioAId);
        })->first();
    }

    private function notificarNuevaSolicitud(
        SolicitudAmistad $solicitud,
        User $remitente,
        User $destinatario
    ): void {
        Notificacion::create([
            'user_id' => $destinatario->id,
            'tipo' => 'solicitud_amistad',
            'titulo' => 'Nueva solicitud de amistad',
            'mensaje' => "{$remitente->name} te envió una solicitud de amistad.",
            'data' => [
                'usuario_id' => $remitente->id,
                'solicitud_id' => $solicitud->id,
            ],
            'leida' => false,
            'leida_at' => null,
        ]);
    }
}
