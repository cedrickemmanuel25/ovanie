<?php

namespace App\Services\SupportAi;

use Illuminate\Support\Str;

class SupportServiceStatusService
{
    public function all(): array
    {
        return [
            'ai' => $this->ai(),
            'telephony' => $this->telephony(),
            'whatsapp' => $this->whatsapp(),
            'email' => $this->email(),
            'chat' => [
                'key' => 'chat',
                'label' => 'Chat web',
                'configured' => true,
                'operational' => true,
                'provider' => 'OVANIE',
                'message' => 'Canal web interne disponible.',
            ],
        ];
    }

    public function telephonyConfigured(): bool
    {
        return (bool) $this->telephony()['operational'];
    }

    public function channel(string $channel): array
    {
        return $this->all()[$channel] ?? [
            'key' => $channel,
            'label' => ucfirst($channel),
            'configured' => false,
            'operational' => false,
            'provider' => null,
            'message' => 'Service non configuré.',
        ];
    }

    private function ai(): array
    {
        $provider = (string) config('support_ai.ai.provider', 'local');
        $configured = match ($provider) {
            'local' => true,
            'openai' => filled(config('support_ai.ai.openai.api_key'))
                && filled(config('support_ai.ai.openai.model'))
                && filled(config('support_ai.ai.openai.base_url')),
            'http' => filled(config('support_ai.ai.endpoint')) && filled(config('support_ai.ai.api_key')),
            default => false,
        };

        return [
            'key' => 'ai',
            'label' => 'Moteur IA',
            'configured' => $configured,
            'operational' => $configured,
            'provider' => $provider,
            'message' => match (true) {
                ! $configured => 'Le fournisseur IA sélectionné est incomplet.',
                $provider === 'local' => 'Moteur local OVANIE actif.',
                $provider === 'openai' => 'OpenAI est configuré pour le Support OVANIE.',
                default => 'Fournisseur IA HTTP configuré.',
            },
        ];
    }

    private function telephony(): array
    {
        $provider = (string) config('support_ai.telephony.provider', 'none');
        $configured = match ($provider) {
            'twilio' => filled(config('support_ai.telephony.twilio.account_sid'))
                && filled(config('support_ai.telephony.twilio.auth_token'))
                && filled(config('support_ai.telephony.from_number'))
                && filled(config('support_ai.telephony.public_base_url')),
            'webhook' => filled(config('support_ai.telephony.outbound_url'))
                && filled(config('support_ai.telephony.api_token'))
                && filled(config('support_ai.telephony.webhook_secret')),
            default => false,
        };

        $publicUrl = (string) config('support_ai.telephony.public_base_url');
        $publiclyReachable = $publicUrl !== ''
            && Str::startsWith($publicUrl, 'https://')
            && ! Str::contains($publicUrl, ['127.0.0.1', 'localhost']);

        $operational = $configured && $publiclyReachable;

        return [
            'key' => 'telephony',
            'label' => 'Téléphonie',
            'configured' => $configured,
            'operational' => $operational,
            'provider' => $provider,
            'message' => match (true) {
                ! $configured => 'Aucun fournisseur téléphonique complet n’est configuré.',
                ! $publiclyReachable => 'Le fournisseur est renseigné, mais l’URL publique HTTPS est absente ou locale.',
                default => 'Fournisseur téléphonique configuré pour les appels réels.',
            },
        ];
    }

    private function whatsapp(): array
    {
        $provider = (string) config('support_ai.integrations.whatsapp.provider', 'none');
        $enabled = (bool) config('support_ai.integrations.whatsapp.enabled', false);
        $configured = $provider === 'meta'
            && $enabled
            && filled(config('support_ai.integrations.whatsapp.access_token'))
            && filled(config('support_ai.integrations.whatsapp.app_secret'))
            && filled(config('support_ai.integrations.whatsapp.verify_token'))
            && filled(config('support_ai.integrations.whatsapp.phone_number_id'));

        $appUrl = (string) config('app.url');
        $publiclyReachable = Str::startsWith($appUrl, 'https://')
            && ! Str::contains($appUrl, ['127.0.0.1', 'localhost']);
        $operational = $configured && $publiclyReachable;

        return [
            'key' => 'whatsapp',
            'label' => 'WhatsApp',
            'configured' => $configured,
            'operational' => $operational,
            'provider' => $provider,
            'message' => match (true) {
                ! $configured => 'WhatsApp Cloud API n’est pas entièrement configurée.',
                ! $publiclyReachable => 'WhatsApp est configuré, mais APP_URL doit être une adresse HTTPS publique.',
                default => 'WhatsApp Cloud API est configurée avec le webhook OVANIE.',
            },
        ];
    }

    private function email(): array
    {
        $mailer = (string) config('mail.default');
        $configured = ! in_array($mailer, ['', 'log', 'array'], true) && filled(config('mail.from.address'));

        return [
            'key' => 'email',
            'label' => 'E-mail',
            'configured' => $configured,
            'operational' => $configured,
            'provider' => $mailer,
            'message' => $configured ? 'Transport e-mail configuré.' : 'Transport e-mail réel non configuré.',
        ];
    }
}
