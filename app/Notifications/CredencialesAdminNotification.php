<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CredencialesAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $email,
        private readonly string $password,
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
            ->subject('Tu cuenta de administrador en RentaFácil')
            ->view('emails.credenciales-admin', [
                'user' => $notifiable,
                'email' => $this->email,
                'password' => $this->password,
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
