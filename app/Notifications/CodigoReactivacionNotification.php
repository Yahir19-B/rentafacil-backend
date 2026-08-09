<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CodigoReactivacionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $codigo,
        private readonly int $minutosVigencia,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Código para reactivar tu cuenta de RentaFácil')
            ->view('emails.codigo-reactivacion', [
                'user' => $notifiable,
                'codigo' => $this->codigo,
                'minutosVigencia' => $this->minutosVigencia,
                'logoUrl' => env('LOGO_EMAIL_URL'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
