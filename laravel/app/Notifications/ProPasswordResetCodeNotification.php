<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProPasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $code,
        public readonly string $challenge
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Code de réinitialisation OVANIE PRO')
            ->greeting('Bonjour ' . ($notifiable->first_name ?: ''))
            ->line('Vous avez demandé la réinitialisation de votre mot de passe OVANIE PRO.')
            ->line('Votre code de vérification est : ' . $this->code)
            ->line('Ce code est valable pendant 10 minutes et ne peut être utilisé qu’une seule fois.')
            ->action('Définir un nouveau mot de passe', route('pro.password.reset', ['challenge' => $this->challenge]))
            ->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.');
    }
}
