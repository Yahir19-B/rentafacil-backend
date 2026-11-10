<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RecuperarPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(
            config('app.frontend_url'),
            '/'
        );

        $url = $frontendUrl
            . '/reset-password?token=' . urlencode($this->token)
            . '&email=' . urlencode($notifiable->getEmailForPasswordReset());

        return (new MailMessage)
            ->subject('Restablece tu contraseña de RentaFácil')
            ->view('emails.recuperar-password', [
                'url' => $url,
                'user' => $notifiable,
                'logoUrl' => config('app.logo_email_url'),
                'expirationMinutes' => config('auth.passwords.users.expire', 60),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
