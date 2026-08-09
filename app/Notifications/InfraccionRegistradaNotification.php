<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InfraccionRegistradaNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $motivo,
        private readonly int $strikes,
        private readonly bool $suspendido,
        private readonly int $limite,
        private readonly int $diasSuspension = 3,
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
            ->subject($this->suspendido ? 'Tu cuenta fue suspendida' : 'Se registró una infracción en tu cuenta')
            ->view('emails.infraccion-registrada', [
                'user' => $notifiable,
                'motivo' => $this->motivo,
                'strikes' => $this->strikes,
                'limite' => $this->limite,
                'suspendido' => $this->suspendido,
                'diasSuspension' => $this->diasSuspension,
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
