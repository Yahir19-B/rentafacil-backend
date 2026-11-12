<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\SolicitudReactivacion;
use App\Models\User;
use App\Notifications\ReactivacionResueltaNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * "Solicitar la reactivación de una cuenta suspendida o baneada por
 * strikes con el soporte técnico" y "La restitución del acceso
 * únicamente procederá después de la revisión del administrador".
 */
class SolicitudReactivacionController extends Controller
{
    /**
     * El usuario no puede iniciar sesión, así que se identifica con
     * su correo y contraseña.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'mensaje' => 'required|string|min:20|max:1000',
        ], [
            'mensaje.required' => 'Explica por qué quieres que reactivemos tu cuenta.',
            'mensaje.min' => 'Explica tu caso con al menos 20 caracteres.',
        ]);

        $usuario = User::where('email', $data['email'])->first();

        if (!$usuario || !Hash::check($data['password'], $usuario->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }

        $bloqueadaPorStrikes = $usuario->status === 'baneado'
            || ($usuario->status === 'suspendido' && $usuario->sanciones()
                ->whereIn('tipo', ['suspension', 'baneo'])
                ->where('activa', true)
                ->exists());

        if (!$bloqueadaPorStrikes) {
            return response()->json([
                'message' => 'Tu cuenta no está suspendida ni baneada por infracciones.',
            ], 422);
        }

        if ($usuario->solicitudesReactivacion()->where('estado', 'pendiente')->exists()) {
            return response()->json([
                'message' => 'Ya tienes una solicitud en revisión. Te responderemos por correo.',
            ], 422);
        }

        $solicitud = $usuario->solicitudesReactivacion()->create([
            'mensaje' => trim($data['mensaje']),
            'estado' => 'pendiente',
        ]);

        $admins = User::whereHas('role', fn ($q) => $q->where('nombre', 'admin'))->pluck('id');

        foreach ($admins as $adminId) {
            Notificacion::create([
                'user_id' => $adminId,
                'tipo' => 'solicitud_reactivacion',
                'titulo' => 'Nueva solicitud de reactivación',
                'mensaje' => "{$usuario->name} pidió que reactiven su cuenta ({$usuario->status}).",
                'data' => ['solicitud_id' => $solicitud->id, 'usuario_id' => $usuario->id],
                'leida' => false,
                'leida_at' => null,
            ]);
        }

        return response()->json([
            'message' => 'Recibimos tu solicitud. Un administrador la revisará y te responderemos por correo.',
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | Administrador
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        $estado = $request->input('estado', 'pendiente');

        $solicitudes = SolicitudReactivacion::with([
                'user:id,name,email,foto_perfil,status,strikes',
                'admin:id,name',
            ])
            ->when($estado === 'pendiente',
                fn ($q) => $q->where('estado', 'pendiente'),
                fn ($q) => $q->where('estado', '!=', 'pendiente'))
            ->latest()
            ->get();

        // Última sanción activa, para que el admin vea por qué está bloqueada.
        $solicitudes->each(function (SolicitudReactivacion $solicitud) {
            $solicitud->setAttribute('sancion', $solicitud->user?->sanciones()
                ->where('activa', true)
                ->latest()
                ->first(['tipo', 'motivo', 'fecha_inicio', 'fecha_fin']));
        });

        return response()->json($solicitudes);
    }

    public function aprobar(Request $request, SolicitudReactivacion $solicitud): JsonResponse
    {
        return $this->resolver($request, $solicitud, true);
    }

    public function rechazar(Request $request, SolicitudReactivacion $solicitud): JsonResponse
    {
        return $this->resolver($request, $solicitud, false);
    }

    private function resolver(Request $request, SolicitudReactivacion $solicitud, bool $aprobar): JsonResponse
    {
        $data = $request->validate([
            'respuesta' => ($aprobar ? 'nullable' : 'required') . '|string|max:1000',
        ], [
            'respuesta.required' => 'Explica al usuario por qué se rechaza su solicitud.',
        ]);

        if ($solicitud->estado !== 'pendiente') {
            return response()->json(['message' => 'Esta solicitud ya fue resuelta.'], 422);
        }

        $respuesta = trim($data['respuesta'] ?? '') ?: null;
        $usuario = $solicitud->user;

        DB::transaction(function () use ($solicitud, $usuario, $aprobar, $respuesta) {
            $solicitud->update([
                'estado' => $aprobar ? 'aprobada' : 'rechazada',
                'admin_id' => Auth::id(),
                'respuesta' => $respuesta,
                'resuelta_at' => now(),
            ]);

            if ($aprobar && $usuario) {
                $usuario->update(['status' => 'activo', 'strikes' => 0]);

                $usuario->sanciones()
                    ->whereIn('tipo', ['suspension', 'baneo'])
                    ->where('activa', true)
                    ->update(['activa' => false, 'fecha_fin' => now()]);
            }
        });

        $usuario?->notify(new ReactivacionResueltaNotification($aprobar, $respuesta));

        return response()->json($solicitud->fresh(['user:id,name,email,foto_perfil,status,strikes', 'admin:id,name']));
    }
}
