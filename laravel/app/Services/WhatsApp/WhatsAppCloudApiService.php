<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WhatsAppCloudApiService
{
    public function isConfigured(): bool
    {
        return (bool) config('support_ai.integrations.whatsapp.enabled')
            && config('support_ai.integrations.whatsapp.provider') === 'meta'
            && filled(config('support_ai.integrations.whatsapp.access_token'))
            && filled(config('support_ai.integrations.whatsapp.phone_number_id'));
    }

    /**
     * @return array{id:string,payload:array}
     */
    public function sendTextMessage(string $to, string $body): array
    {
        $this->assertConfigured();

        $recipient = preg_replace('/\D+/', '', $to);
        if ($recipient === '') {
            throw new RuntimeException('Le numéro WhatsApp destinataire est invalide.');
        }

        $body = trim($body);
        if ($body === '') {
            throw new RuntimeException('Le message WhatsApp est vide.');
        }

        $response = $this->request()->post($this->messagesEndpoint(), [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => mb_substr($body, 0, 4000),
            ],
        ]);

        if (! $response->successful()) {
            $message = (string) data_get($response->json(), 'error.message', 'Erreur Meta non détaillée.');
            throw new RuntimeException('Meta WhatsApp a retourné HTTP '.$response->status().' : '.$message);
        }

        $payload = $response->json();
        $messageId = (string) data_get($payload, 'messages.0.id');
        if ($messageId === '') {
            throw new RuntimeException('Meta n’a retourné aucun identifiant de message WhatsApp.');
        }

        return ['id' => $messageId, 'payload' => is_array($payload) ? $payload : []];
    }

    public function markAsRead(string $providerMessageId): void
    {
        if (! $this->isConfigured() || trim($providerMessageId) === '') {
            return;
        }

        $this->request()->post($this->messagesEndpoint(), [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $providerMessageId,
        ])->throw();
    }

    private function request(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken((string) config('support_ai.integrations.whatsapp.access_token'))
            ->timeout((int) config('support_ai.integrations.whatsapp.timeout', 20))
            ->retry(2, 400, throw: false);
    }

    private function messagesEndpoint(): string
    {
        $baseUrl = rtrim((string) config('support_ai.integrations.whatsapp.graph_base_url'), '/');
        $version = trim((string) config('support_ai.integrations.whatsapp.graph_version'), '/');
        $phoneNumberId = trim((string) config('support_ai.integrations.whatsapp.phone_number_id'));

        return "{$baseUrl}/{$version}/{$phoneNumberId}/messages";
    }

    private function assertConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('WhatsApp Cloud API n’est pas entièrement configurée.');
        }
    }
}
