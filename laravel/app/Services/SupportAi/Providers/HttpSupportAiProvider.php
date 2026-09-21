<?php

namespace App\Services\SupportAi\Providers;

use App\Models\SupportAiAgent;
use App\Models\SupportConversation;
use App\Services\SupportAi\Contracts\SupportAiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HttpSupportAiProvider implements SupportAiProvider
{
    public function generate(
        SupportConversation $conversation,
        SupportAiAgent $agent,
        string $message,
        array $context = []
    ): array {
        $endpoint = (string) config('support_ai.ai.endpoint');
        $apiKey = (string) config('support_ai.ai.api_key');

        if ($endpoint === '') {
            throw new RuntimeException('SUPPORT_AI_ENDPOINT n’est pas configuré.');
        }

        $response = Http::timeout((int) config('support_ai.ai.timeout', 20))
            ->acceptJson()
            ->when($apiKey !== '', fn ($request) => $request->withToken($apiKey))
            ->post($endpoint, [
                'model' => config('support_ai.ai.model'),
                'agent' => [
                    'name' => $agent->name,
                    'role' => $agent->role_key,
                    'system_prompt' => $agent->system_prompt,
                ],
                'conversation' => [
                    'id' => $conversation->id,
                    'channel' => $conversation->channel,
                    'subject' => $conversation->subject,
                    'summary' => $conversation->summary,
                ],
                'message' => $message,
                'context' => $context,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Le fournisseur IA a retourné une erreur HTTP '.$response->status().'.');
        }

        $payload = $response->json();
        $body = data_get($payload, 'body') ?? data_get($payload, 'message') ?? data_get($payload, 'text');

        if (! is_string($body) || trim($body) === '') {
            throw new RuntimeException('Le fournisseur IA n’a retourné aucune réponse exploitable.');
        }

        return [
            'body' => trim($body),
            'confidence' => (float) (data_get($payload, 'confidence', 85)),
            'provider' => 'http',
            'metadata' => is_array($payload) ? $payload : [],
        ];
    }
}
