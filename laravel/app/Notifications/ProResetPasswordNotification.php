<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('pro.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe OVANIE Pro')
            ->line('Vous recevez ce message car une demande de réinitialisation du mot de passe de votre compte OVANIE Pro a été effectuée.')
            ->action('Réinitialiser mon mot de passe OVANIE Pro', $url)
            ->line('Si vous n’êtes pas à l’origine de cette demande, aucune action n’est nécessaire.');
    }
}
