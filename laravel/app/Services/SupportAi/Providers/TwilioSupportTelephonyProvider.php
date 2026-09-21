<?php

namespace App\Services\SupportAi\Providers;

use App\Models\SupportCall;
use App\Services\SupportAi\Contracts\SupportTelephonyProvider;
use App\Services\SupportAi\SupportServiceStatusService;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TwilioSupportTelephonyProvider implements SupportTelephonyProvider
{
    public function __construct(private readonly SupportServiceStatusService $services) {}

    public function initiateOutbound(SupportCall $call, array $payload = []): array
    {
        $this->assertConfigured();

        $params = [
            'To' => $call->to_number,
            'From' => $call->from_number ?: config('support_ai.telephony.from_number'),
            'Url' => $this->publicRoute('webhooks.support.twilio.voice', ['reference' => $call->reference]),
            'Method' => 'POST',
            'StatusCallback' => $this->publicRoute('webhooks.support.twilio.status'),
            'StatusCallbackMethod' => 'POST',
            'Record' => config('support_ai.telephony.record_calls') ? 'true' : 'false',
        ];

        $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986)
            .'&StatusCallbackEvent=initiated'
            .'&StatusCallbackEvent=ringing'
            .'&StatusCallbackEvent=answered'
            .'&StatusCallbackEvent=completed';

        $response = $this->client()
            ->withBody($body, 'application/x-www-form-urlencoded')
            ->post($this->callsEndpoint());

        if (! $response->successful()) {
            throw new RuntimeException('Twilio a refusé l’appel : '.$this->errorMessage($response->json(), $response->status()));
        }

        return [
            'call_id' => $response->json('sid'),
            'status' => $response->json('status', 'queued'),
            'provider_response' => $response->json(),
        ];
    }

    public function transfer(SupportCall $call, string $destination, array $payload = []): array
    {
        $this->assertConfigured();
        if (! $call->provider_call_id) {
            throw new RuntimeException('L’appel ne possède pas de SID Twilio et ne peut pas être transféré.');
        }

        $twiml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<Response><Dial callerId="'.htmlspecialchars((string) config('support_ai.telephony.from_number'), ENT_XML1 | ENT_QUOTES, 'UTF-8').'">'
            .'<Number>'.htmlspecialchars($destination, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Number></Dial></Response>';

        $response = $this->client()->asForm()->post($this->callEndpoint($call->provider_call_id), [
            'Twiml' => $twiml,
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Le transfert Twilio a échoué : '.$this->errorMessage($response->json(), $response->status()));
        }

        return ['call_id' => $response->json('sid'), 'status' => $response->json('status'), 'provider_response' => $response->json()];
    }

    public function hangup(SupportCall $call): void
    {
        $this->assertConfigured();
        if (! $call->provider_call_id) {
            throw new RuntimeException('L’appel ne possède pas de SID Twilio.');
        }

        $response = $this->client()->asForm()->post($this->callEndpoint($call->provider_call_id), [
            'Status' => 'completed',
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Impossible de terminer l’appel Twilio.');
        }
    }

    private function client(): PendingRequest
    {
        return Http::timeout((int) config('support_ai.telephony.timeout', 20))
            ->acceptJson()
            ->withBasicAuth(
                (string) config('support_ai.telephony.twilio.account_sid'),
                (string) config('support_ai.telephony.twilio.auth_token'),
            );
    }

    private function callsEndpoint(): string
    {
        $sid = rawurlencode((string) config('support_ai.telephony.twilio.account_sid'));
        return "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Calls.json";
    }

    private function callEndpoint(string $callSid): string
    {
        $sid = rawurlencode((string) config('support_ai.telephony.twilio.account_sid'));
        return "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Calls/".rawurlencode($callSid).'.json';
    }

    private function publicRoute(string $name, array $parameters = []): string
    {
        $base = rtrim((string) config('support_ai.telephony.public_base_url'), '/');
        return $base.route($name, $parameters, false);
    }

    private function assertConfigured(): void
    {
        if (! $this->services->telephonyConfigured() || config('support_ai.telephony.provider') !== 'twilio') {
            throw new RuntimeException('Twilio n’est pas complètement configuré dans le fichier .env.');
        }
    }

    private function errorMessage(?array $payload, int $status): string
    {
        return (string) ($payload['message'] ?? $payload['detail'] ?? "erreur HTTP {$status}");
    }
}
