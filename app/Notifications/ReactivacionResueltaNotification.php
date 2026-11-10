<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Avisa por correo si soporte aprobó o rechazó la solicitud de
 * reactivación (el usuario no puede ver notificaciones en la app
 * mientras su cuenta está bloqueada).
 */
class ReactivacionResueltaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly bool $aprobada,
        private readonly ?string $respuesta,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensaje = (new MailMessage)
            ->subject($this->aprobada
                ? 'Tu cuenta de RentaFácil fue reactivada'
                : 'Respuesta a tu solicitud de reactivación')
            ->greeting("Hola, {$notifiable->name}");

        if ($this->aprobada) {
            $mensaje->line('Revisamos tu solicitud y reactivamos tu cuenta. Ya puedes volver a iniciar sesión.')
                ->line('Tus strikes se reiniciaron. Te pedimos respetar el reglamento de la plataforma.')
                ->action('Iniciar sesión', rtrim(config('app.frontend_url'), '/') . '/login');
        } else {
            $mensaje->line('Revisamos tu solicitud y por ahora tu cuenta seguirá bloqueada.');
        }

        if ($this->respuesta) {
            $mensaje->line("Comentario del equipo de soporte: {$this->respuesta}");
        }

        return $mensaje->salutation('Equipo de RentaFácil');
    }
}
