<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sancion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Gestión de usuarios desde el panel de administración: listado
 * con filtros, detalle completo (incluye suspendidos/baneados,
 * a diferencia de UsuarioController que es de cara al público) y
 * las acciones de banear/desbanear.
 */
class AdminUsuarioController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('role')
            ->whereHas('role', fn ($q) => $q->whereIn('nombre', ['dueno', 'inquilino']))
            ->withCount([
                'reportesRecibidos as reportes_pendientes' => function ($q) {
                    $q->where('reportes.estado', 'pendiente');
                },
            ]);

        if ($request->filled('buscar')) {
            $texto = trim($request->input('buscar'));

            $query->where(function ($q) use ($texto) {
                $q->where('name', 'like', "%{$texto}%")
                    ->orWhere('email', 'like', "%{$texto}%");
            });
        }

        if ($request->filled('rol')) {
            $query->whereHas('role', fn ($q) => $q->where('nombre', $request->input('rol')));
        }

        switch ($request->input('estado')) {
            case 'activos':
                $query->where('status', 'activo')->where('strikes', 0);
                break;

            case 'advertidos':
                $query->where('status', 'activo')->where('strikes', '>', 0);
                break;

            case 'suspendidos':
                $query->where('status', 'suspendido');
                break;

            case 'baneados':
                $query->where('status', 'baneado');
                break;
        }

        $usuarios = $query->orderByDesc('created_at')->get();

        return response()->json(
            $usuarios->map(fn (User $usuario) => $this->resumen($usuario))
        );
    }

    public function show(User $usuario)
    {
        $usuario->load(['role', 'sanciones' => function ($q) {
            $q->latest();
        }]);

        $anuncios = $usuario->propiedades()->count();
        $reportesTotal = $usuario->reportesRecibidos()->count();
        $infraccionesPendientes = $usuario->reportesRecibidos()
            ->where('reportes.estado', 'pendiente')
            ->get();

        $infraccionesCometidas = $usuario->moderaciones()
            ->where('resultado', 'rechazado')
            ->with('propiedad')
            ->latest()
            ->get();

        $sancionActiva = $usuario->sanciones
            ->firstWhere(fn ($sancion) => $sancion->activa && in_array($sancion->tipo, ['suspension', 'baneo'], true));

        return response()->json([
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'phone' => $usuario->phone,
            'foto_perfil' => $usuario->foto_perfil,
            'role' => $usuario->role,
            'status' => $usuario->status,
            'estado_visual' => $usuario->estado_visual,
            'strikes' => $usuario->strikes,
            'created_at' => $usuario->created_at,
            'updated_at' => $usuario->updated_at,
            'anuncios' => $anuncios,
            'reportes' => $reportesTotal,
            'infracciones' => $infraccionesCometidas->count(),
            'sancion_activa' => $sancionActiva,
            'infracciones_pendientes' => $infraccionesPendientes->load('propiedad')->values(),
            'infracciones_cometidas' => $infraccionesCometidas,
            'sanciones' => $usuario->sanciones,
        ]);
    }

    public function banear(Request $request, User $usuario)
    {
        $data = $request->validate([
            'motivo' => 'required|string|max:1000',
        ]);

        if ($usuario->role?->nombre === 'admin') {
            return response()->json(['message' => 'No puedes banear a otro administrador.'], 422);
        }

        $usuario->update(['status' => 'baneado']);
        $usuario->tokens()->delete();

        Sancion::create([
            'user_id' => $usuario->id,
            'admin_id' => Auth::id(),
            'tipo' => 'baneo',
            'motivo' => $data['motivo'],
            'fecha_inicio' => now(),
            'fecha_fin' => null,
            'activa' => true,
        ]);

        return response()->json($this->resumen($usuario->fresh('role')));
    }

    public function desbanear(User $usuario)
    {
        $usuario->update(['status' => 'activo', 'strikes' => 0]);

        $usuario->sanciones()
            ->whereIn('tipo', ['suspension', 'baneo'])
            ->where('activa', true)
            ->update(['activa' => false, 'fecha_fin' => now()]);

        return response()->json($this->resumen($usuario->fresh('role')));
    }

    private function resumen(User $usuario): array
    {
        return [
            'id' => $usuario->id,
            'name' => $usuario->name,
            'email' => $usuario->email,
            'foto_perfil' => $usuario->foto_perfil,
            'role' => $usuario->role,
            'status' => $usuario->status,
            'estado_visual' => $usuario->estado_visual,
            'strikes' => $usuario->strikes,
            'created_at' => $usuario->created_at,
            'reportes_pendientes' => $usuario->reportes_pendientes ?? null,
        ];
    }
}
