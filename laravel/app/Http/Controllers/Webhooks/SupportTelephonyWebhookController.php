<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Services\SupportAi\SupportServiceStatusService;
use App\Services\SupportAi\SupportTelephonyManager;
use Illuminate\Http\Request;

class SupportTelephonyWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        SupportTelephonyManager $manager,
        SupportServiceStatusService $services,
    ) {
        abort_unless(config('support_ai.telephony.provider') === 'webhook', 404);

        if (! $services->telephonyConfigured()) {
            return response()->json(['message' => $services->channel('telephony')['message']], 503);
        }

        if (! $this->validSignature($request)) {
            return response()->json(['message' => 'Signature de webhook invalide.'], 401);
        }

        $payload = $request->validate([
            'event' => ['required_without:type', 'nullable', 'string', 'max:80'],
            'type' => ['required_without:event', 'nullable', 'string', 'max:80'],
            'event_id' => ['nullable', 'string', 'max:255'],
            'call_id' => ['nullable', 'string', 'max:255'],
            'from' => ['nullable', 'string', 'max:80'],
            'to' => ['nullable', 'string', 'max:80'],
            'text' => ['nullable', 'string', 'max:20000'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'recording_url' => ['nullable', 'url', 'max:2000'],
            'summary' => ['nullable', 'string', 'max:10000'],
            'occurred_at' => ['nullable', 'date'],
            'call' => ['nullable', 'array'],
            'transcript' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json($manager->handleWebhook($payload));
    }

    private function validSignature(Request $request): bool
    {
        $secret = (string) config('support_ai.telephony.webhook_secret');

        if ($secret === '') {
            return false;
        }

        $provided = (string) $request->header('X-OVANIE-SIGNATURE');
        if ($provided === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $provided);
    }
}
