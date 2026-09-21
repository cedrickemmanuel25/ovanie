<?php

namespace App\Services\WhatsApp;

class WhatsAppWebhookParser
{
    /**
     * @return array{messages:list<array<string,mixed>>,statuses:list<array<string,mixed>>}
     */
    public function parse(array $payload): array
    {
        $messages = [];
        $statuses = [];

        foreach ((array) ($payload['entry'] ?? []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? null) !== 'messages') {
                    continue;
                }

                $value = (array) ($change['value'] ?? []);
                $metadata = (array) ($value['metadata'] ?? []);
                $contacts = collect((array) ($value['contacts'] ?? []))->keyBy('wa_id');

                foreach ((array) ($value['messages'] ?? []) as $message) {
                    $from = (string) ($message['from'] ?? '');
                    $contact = (array) ($contacts->get($from) ?? []);
                    $messages[] = [
                        'provider_message_id' => (string) ($message['id'] ?? ''),
                        'from' => $from,
                        'to_phone_number_id' => $metadata['phone_number_id'] ?? null,
                        'display_phone_number' => $metadata['display_phone_number'] ?? null,
                        'contact_name' => data_get($contact, 'profile.name'),
                        'timestamp' => isset($message['timestamp']) ? (int) $message['timestamp'] : null,
                        'type' => (string) ($message['type'] ?? 'unknown'),
                        'body' => $this->body($message),
                        'context_message_id' => data_get($message, 'context.id'),
                        'raw' => $message,
                    ];
                }

                foreach ((array) ($value['statuses'] ?? []) as $status) {
                    $errors = (array) ($status['errors'] ?? []);
                    $firstError = (array) ($errors[0] ?? []);
                    $statuses[] = [
                        'provider_message_id' => (string) ($status['id'] ?? ''),
                        'recipient_id' => $status['recipient_id'] ?? null,
                        'status' => (string) ($status['status'] ?? 'unknown'),
                        'timestamp' => isset($status['timestamp']) ? (int) $status['timestamp'] : null,
                        'conversation' => $status['conversation'] ?? null,
                        'pricing' => $status['pricing'] ?? null,
                        'error_code' => isset($firstError['code']) ? (string) $firstError['code'] : null,
                        'error_message' => $firstError['title'] ?? $firstError['message'] ?? null,
                        'raw' => $status,
                    ];
                }
            }
        }

        return compact('messages', 'statuses');
    }

    private function body(array $message): string
    {
        return match ((string) ($message['type'] ?? 'unknown')) {
            'text' => trim((string) data_get($message, 'text.body')),
            'button' => trim((string) (data_get($message, 'button.text') ?: data_get($message, 'button.payload'))),
            'interactive' => trim((string) (
                data_get($message, 'interactive.button_reply.title')
                ?: data_get($message, 'interactive.list_reply.title')
                ?: data_get($message, 'interactive.button_reply.id')
                ?: data_get($message, 'interactive.list_reply.id')
            )),
            'location' => $this->locationBody($message),
            'image' => trim((string) data_get($message, 'image.caption')) ?: '[Image reçue sur WhatsApp]',
            'document' => trim((string) data_get($message, 'document.caption')) ?: '[Document reçu sur WhatsApp]',
            'audio' => '[Message audio reçu sur WhatsApp]',
            'video' => trim((string) data_get($message, 'video.caption')) ?: '[Vidéo reçue sur WhatsApp]',
            'sticker' => '[Sticker reçu sur WhatsApp]',
            default => '[Type de message WhatsApp non pris en charge]',
        };
    }

    private function locationBody(array $message): string
    {
        $latitude = data_get($message, 'location.latitude');
        $longitude = data_get($message, 'location.longitude');
        $name = data_get($message, 'location.name');
        $address = data_get($message, 'location.address');

        return trim('Position WhatsApp : '.implode(' — ', array_filter([
            $name,
            $address,
            ($latitude !== null && $longitude !== null) ? $latitude.', '.$longitude : null,
        ])));
    }
}
