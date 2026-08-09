<?php

namespace App\Console\Commands;

use App\Models\Notificacion;
use App\Models\Propiedad;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:inhabilitar-propiedades-vencidas')]
#[Description('Inhabilita publicaciones aprobadas con más de 30 días desde que se habilitaron.')]
class InhabilitarPropiedadesVencidas extends Command
{
    private const DIAS_VIGENCIA = 30;

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $vencidas = Propiedad::query()
            ->where('estado_publicacion', 'aprobada')
            ->where('disponible', true)
            ->whereNotNull('disponible_desde')
            ->where('disponible_desde', '<=', now()->subDays(self::DIAS_VIGENCIA))
            ->get();

        foreach ($vencidas as $propiedad) {
            $propiedad->update(['disponible' => false]);

            Notificacion::create([
                'user_id' => $propiedad->user_id,
                'tipo' => 'propiedad_inhabilitada_vencimiento',
                'titulo' => 'Publicación inhabilitada',
                'mensaje' => "Tu publicación \"{$propiedad->titulo}\" se inhabilitó automáticamente "
                    . 'porque llevaba ' . self::DIAS_VIGENCIA . ' días habilitada. '
                    . 'Actívala de nuevo cuando quieras seguir recibiendo mensajes.',
                'data' => ['propiedad_id' => $propiedad->id],
                'leida' => false,
                'leida_at' => null,
            ]);
        }

        $this->info('Publicaciones inhabilitadas por vencimiento (' . self::DIAS_VIGENCIA . ' días): ' . $vencidas->count() . '.');
    }
}
