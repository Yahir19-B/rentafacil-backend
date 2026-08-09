<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Moderacion;

class AdminModeracionController extends Controller
{
    public function estadisticas()
    {
        $rechazadas = Moderacion::where('resultado', 'rechazado');

        $imagenesRechazadas = (clone $rechazadas)->where('tipo', 'imagen')->count();
        $textosRechazados = (clone $rechazadas)->where('tipo', 'texto')->count();

        $recientes = Moderacion::with('user')
            ->where('resultado', 'rechazado')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn (Moderacion $moderacion) => [
                'id' => $moderacion->id,
                'tipo' => $moderacion->tipo,
                'motivo' => $moderacion->motivo,
                'proveedor_ia' => $moderacion->proveedor_ia,
                'usuario' => $moderacion->user ? [
                    'id' => $moderacion->user->id,
                    'name' => $moderacion->user->name,
                    'foto_perfil' => $moderacion->user->foto_perfil,
                ] : null,
                'created_at' => $moderacion->created_at,
            ]);

        return response()->json([
            'imagenes_rechazadas' => $imagenesRechazadas,
            'textos_rechazados' => $textosRechazados,
            'total_rechazados' => $imagenesRechazadas + $textosRechazados,
            'recientes' => $recientes,
        ]);
    }
}
