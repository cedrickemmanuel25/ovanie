<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $code,
        private readonly int $validityMinutes = 10
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Code de confirmation de suppression du compte OVANIE')
            ->greeting('Confirmation de sécurité')
            ->line('Une demande de suppression de votre compte OVANIE a été initiée.')
            ->line('Votre code de confirmation est : ' . $this->code)
            ->line('Ce code expire dans ' . $this->validityMinutes . ' minutes et ne doit être communiqué à personne.')
            ->line('Si vous n’êtes pas à l’origine de cette demande, ne saisissez pas ce code et sécurisez votre compte.');
    }
}
