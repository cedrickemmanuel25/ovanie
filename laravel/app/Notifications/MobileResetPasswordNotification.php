<?php

namespace App\Notifications;

use App\Services\Auth\PortalAuthenticationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification unique de récupération OVANIE pour Web et Mobile.
 *
 * Le même token Laravel est utilisé partout. Le portail détermine seulement
 * l'application / l'écran qui doit s'ouvrir, jamais une seconde base de mots
 * de passe.
 */
class MobileResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $portal = PortalAuthenticationService::PORTAL_CLIENT,
        private readonly bool $preferMobile = true,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = (string) ($notifiable->email ?? '');
        $portal = $this->portal === PortalAuthenticationService::PORTAL_VENDOR
            ? PortalAuthenticationService::PORTAL_VENDOR
            : PortalAuthenticationService::PORTAL_CLIENT;

        $mobileScheme = $portal === PortalAuthenticationService::PORTAL_VENDOR
            ? 'ovanie-vendeur'
            : 'ovanie';
        $mobileUrl = $mobileScheme.'://auth/reset-password?token='.rawurlencode($this->token)
            .'&email='.rawurlencode($email)
            .'&portal='.rawurlencode($portal);

        $webUrl = url('/reset-password/'.rawurlencode($this->token))
            .'?email='.rawurlencode($email)
            .'&portal='.rawurlencode($portal);

        $message = (new MailMessage)
            ->subject('Réinitialiser votre mot de passe OVANIE')
            ->greeting('Bonjour,')
            ->line('Vous avez demandé la réinitialisation du mot de passe de votre compte OVANIE.');

        if ($this->preferMobile) {
            $message->action(
                $portal === PortalAuthenticationService::PORTAL_VENDOR
                    ? 'Ouvrir OVANIE Vendeur'
                    : 'Ouvrir l’application OVANIE',
                $mobileUrl,
            )->line('Si l’application ne s’ouvre pas, utilisez le lien Web suivant :')
                ->line($webUrl);
        } else {
            $message->action('Réinitialiser mon mot de passe', $webUrl)
                ->line('Vous pouvez aussi ouvrir l’application correspondante avec ce lien :')
                ->line($mobileUrl);
        }

        return $message->line('Si vous n’êtes pas à l’origine de cette demande, ignorez ce message.');
    }
}
