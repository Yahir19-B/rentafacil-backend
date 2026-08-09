<?php

namespace App\Services;

use App\Models\Configuracion;
use App\Models\Notificacion;
use App\Models\Sancion;
use App\Models\User;
use App\Notifications\InfraccionRegistradaNotification;

/**
 * Registra un strike cuando la moderación de IA (imagen o texto)
 * rechaza contenido de un usuario. Al llegar al límite configurado
 * (Configuracion::actual()->limite_strikes) suspende la cuenta
 * automáticamente. Notifica a todos los admins en cada strike.
 */
class StrikeService
{
    public const DIAS_SUSPENSION = 3;

    /**
     * @return array{strikes: int, suspendido: bool, mensaje: string}
     */
    public function registrarStrike(User $usuario, string $motivo, ?int $adminId = null): array
    {
        $limite = Configuracion::actual()->limite_strikes;

        $usuario->increment('strikes');
        $usuario->refresh();

        $suspendido = $usuario->strikes >= $limite;

        if ($suspendido) {
            $usuario->update(['status' => 'suspendido']);
            $usuario->tokens()->delete();
        }

        Sancion::create([
            'user_id' => $usuario->id,
            'admin_id' => $adminId,
            'tipo' => $suspendido ? 'suspension' : 'advertencia',
            'motivo' => $motivo,
            'fecha_inicio' => now(),
            // Las suspensiones por strikes se levantan solas a los 3 días;
            // los baneos manuales del admin (tipo "baneo") no llevan fecha_fin.
            'fecha_fin' => $suspendido ? now()->addDays(self::DIAS_SUSPENSION) : null,
            'activa' => true,
        ]);

        $this->notificarAdmins($usuario, $motivo, $suspendido, $limite);

        $usuario->notify(
            new InfraccionRegistradaNotification(
                $motivo,
                $usuario->strikes,
                $suspendido,
                $limite,
                self::DIAS_SUSPENSION
            )
        );

        return [
            'strikes' => $usuario->strikes,
            'suspendido' => $suspendido,
            'mensaje' => $this->mensajeParaUsuario($usuario->strikes, $suspendido, $limite),
        ];
    }

    private function mensajeParaUsuario(int $strikes, bool $suspendido, int $limite): string
    {
        if ($suspendido) {
            return "No se puede subir por contenido explícito. Ya acumulaste {$limite} strikes: "
                . 'tu cuenta fue suspendida por ' . self::DIAS_SUSPENSION . ' días. '
                . 'Se reactivará automáticamente al pasar ese tiempo, o puedes hablar con soporte técnico.';
        }

        $restantes = $limite - $strikes;
        $texto = $restantes === 1 ? '1 strike' : "{$restantes} strikes";

        return "No se puede subir por contenido explícito. Esta es una advertencia: "
            . "te quedan {$texto} antes de que tu cuenta sea suspendida.";
    }

    private function notificarAdmins(User $usuario, string $motivo, bool $suspendido, int $limite): void
    {
        $admins = User::whereHas('role', fn ($q) => $q->where('nombre', 'admin'))->get(['id']);

        $titulo = $suspendido ? 'Usuario suspendido por strikes' : 'Nuevo strike registrado';

        $mensaje = $suspendido
            ? "{$usuario->name} llegó a {$limite} strikes y su cuenta fue suspendida automáticamente."
            : "{$usuario->name} tiene un strike ({$usuario->strikes}/{$limite}).";

        foreach ($admins as $admin) {
            Notificacion::create([
                'user_id' => $admin->id,
                'tipo' => 'strike',
                'titulo' => $titulo,
                'mensaje' => $mensaje,
                'data' => [
                    'usuario_id' => $usuario->id,
                    'strikes' => $usuario->strikes,
                    'limite' => $limite,
                    'suspendido' => $suspendido,
                    'motivo' => $motivo,
                ],
                'leida' => false,
                'leida_at' => null,
            ]);
        }
    }
}
