<?php

namespace App\Notifications;

use App\Models\Propiedad;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImagenSospechosaNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Propiedad $propiedad,
        private readonly string $motivo,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Imagen pendiente de revisión en RentaFácil')
            ->view('emails.imagen-sospechosa', [
                'admin' => $notifiable,
                'propiedad' => $this->propiedad,
                'motivo' => $this->motivo,
                'logoUrl' => env('LOGO_EMAIL_URL'),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
