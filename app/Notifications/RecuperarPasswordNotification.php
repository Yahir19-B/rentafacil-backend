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
            env('FRONTEND_URL', 'http://localhost:8100'),
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
                'logoUrl' => env('LOGO_EMAIL_URL'),
                'expirationMinutes' => config('auth.passwords.users.expire', 60),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
