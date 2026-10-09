<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** ACC-13 : lien d'invitation collaborateur (jeton à usage unique, expirant). */
class ProCompanyInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $url,
        public string $organizationName,
        public string $permissionLabel,
        public string $inviterName,
        public string $expiresAt,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Invitation à rejoindre {$this->organizationName} sur OVANIE Pro")
            ->line("{$this->inviterName} vous invite à rejoindre {$this->organizationName} sur OVANIE Pro.")
            ->line("Accès proposé : {$this->permissionLabel}.")
            ->action('Voir l’invitation', $this->url)
            ->line("Cette invitation expire le {$this->expiresAt}. Si vous ne connaissez pas cette entreprise, ignorez ce message.");
    }
}
