<?php

namespace App\Services\SupportAi\Providers;

use App\Models\SupportCall;
use App\Services\SupportAi\Contracts\SupportTelephonyProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class WebhookSupportTelephonyProvider implements SupportTelephonyProvider
{
    public function initiateOutbound(SupportCall $call, array $payload = []): array
    {
        return $this->post('/calls', array_merge([
            'reference' => $call->reference,
            'from' => $call->from_number ?: config('support_ai.telephony.from_number'),
            'to' => $call->to_number,
            'callback_url' => $this->publicRoute('webhooks.support.telephony'),
        ], $payload));
    }

    public function transfer(SupportCall $call, string $destination, array $payload = []): array
    {
        if (! $call->provider_call_id) {
            throw new RuntimeException('Cet appel ne possède pas encore de référence fournisseur et ne peut pas être transféré.');
        }

        return $this->post('/calls/'.urlencode((string) $call->provider_call_id).'/transfer', array_merge([
            'destination' => $destination,
            'callback_url' => $this->publicRoute('webhooks.support.telephony'),
        ], $payload));
    }

    public function hangup(SupportCall $call): void
    {
        if (! $call->provider_call_id) {
            throw new RuntimeException('Cet appel ne possède pas encore de référence fournisseur.');
        }

        $this->post('/calls/'.urlencode((string) $call->provider_call_id).'/hangup', []);
    }


    private function publicRoute(string $name): string
    {
        $base = rtrim((string) config('support_ai.telephony.public_base_url'), '/');

        return $base.route($name, [], false);
    }

    private function post(string $path, array $payload): array
    {
        $base = rtrim((string) config('support_ai.telephony.outbound_url'), '/');
        if ($base === '') {
            throw new RuntimeException('SUPPORT_TELEPHONY_OUTBOUND_URL n’est pas configuré.');
        }

        $token = (string) config('support_ai.telephony.api_token');
        $response = Http::timeout((int) config('support_ai.telephony.timeout', 20))
            ->acceptJson()
            ->when($token !== '', fn ($request) => $request->withToken($token))
            ->post($base.$path, $payload);

        if (! $response->successful()) {
            throw new RuntimeException('Le fournisseur téléphonique a retourné une erreur HTTP '.$response->status().'.');
        }

        return $response->json() ?: [];
    }
}
