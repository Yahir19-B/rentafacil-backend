<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Moderacion;
use App\Models\Reporte;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::whereHas('role', fn ($q) => $q->whereIn('nombre', ['dueno', 'inquilino']));

        $totalUsuarios = (clone $usuarios)->count();
        $activos = (clone $usuarios)->where('status', 'activo')->where('strikes', 0)->count();
        $baneados = (clone $usuarios)->where('status', 'baneado')->count();

        $reportesPendientes = Reporte::where('estado', 'pendiente')->count();
        $moderacionesPendientes = Moderacion::where('resultado', 'rechazado')
            ->where('estado', 'pendiente')
            ->count();

        $pendientes = $reportesPendientes + $moderacionesPendientes;

        $reportesRecientes = Reporte::with(['propiedad.user'])
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Reporte $reporte) {
                $usuarioReportado = $reporte->propiedad?->user;

                return [
                    'id' => 'reporte-' . $reporte->id,
                    'usuario_id' => $usuarioReportado?->id,
                    'nombre' => $usuarioReportado?->name ?? 'Usuario eliminado',
                    'foto_perfil' => $usuarioReportado?->foto_perfil,
                    'motivo' => $reporte->motivo,
                    'estado' => $reporte->estado,
                    'created_at' => $reporte->created_at,
                ];
            });

        $moderacionesRecientes = Moderacion::with('user')
            ->where('resultado', 'rechazado')
            ->latest()
            ->take(5)
            ->get()
            ->map(function (Moderacion $moderacion) {
                return [
                    'id' => 'moderacion-' . $moderacion->id,
                    'usuario_id' => $moderacion->user?->id,
                    'nombre' => $moderacion->user?->name ?? 'Usuario eliminado',
                    'foto_perfil' => $moderacion->user?->foto_perfil,
                    'motivo' => $moderacion->motivo,
                    'estado' => $moderacion->estado,
                    'created_at' => $moderacion->created_at,
                ];
            });

        $infraccionesRecientes = $reportesRecientes
            ->concat($moderacionesRecientes)
            ->sortByDesc('created_at')
            ->take(5)
            ->values();

        $usuariosRecientes = (clone $usuarios)
            ->with('role')
            ->orderByDesc('created_at')
            ->take(5)
            ->get()
            ->map(fn (User $usuario) => [
                'id' => $usuario->id,
                'name' => $usuario->name,
                'foto_perfil' => $usuario->foto_perfil,
                'role' => $usuario->role,
                'estado_visual' => $usuario->estado_visual,
                'created_at' => $usuario->created_at,
            ]);

        return response()->json([
            'total_usuarios' => $totalUsuarios,
            'activos' => $activos,
            'pendientes' => $pendientes,
            'baneados' => $baneados,
            'infracciones_recientes' => $infraccionesRecientes,
            'usuarios_recientes' => $usuariosRecientes,
        ]);
    }

}
