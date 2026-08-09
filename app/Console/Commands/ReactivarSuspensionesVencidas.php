<?php

namespace App\Console\Commands;

use App\Models\Notificacion;
use App\Models\Sancion;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:reactivar-suspensiones-vencidas')]
#[Description('Reactiva cuentas suspendidas por strikes cuyos 3 días ya pasaron. Los baneos de admin no se tocan.')]
class ReactivarSuspensionesVencidas extends Command
{
    public function handle(): void
    {
        $vencidas = Sancion::query()
            ->where('tipo', 'suspension')
            ->where('activa', true)
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '<=', now())
            ->with('user')
            ->get();

        foreach ($vencidas as $sancion) {
            $usuario = $sancion->user;

            if (!$usuario || $usuario->status !== 'suspendido') {
                $sancion->update(['activa' => false]);
                continue;
            }

            $usuario->update(['status' => 'activo', 'strikes' => 0]);
            $sancion->update(['activa' => false]);

            Notificacion::create([
                'user_id' => $usuario->id,
                'tipo' => 'cuenta_reactivada',
                'titulo' => 'Tu cuenta fue reactivada',
                'mensaje' => 'Ya pasaron los 3 días de suspensión por strikes. Tu cuenta está activa de nuevo.',
                'data' => [],
                'leida' => false,
                'leida_at' => null,
            ]);
        }

        $this->info('Suspensiones por strikes reactivadas: ' . $vencidas->count() . '.');
    }
}
