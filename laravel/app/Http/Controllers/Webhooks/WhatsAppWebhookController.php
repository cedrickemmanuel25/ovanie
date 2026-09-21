<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppWebhook;
use App\Models\WhatsAppWebhookEvent;
use App\Services\WhatsApp\MetaWebhookSignatureVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use JsonException;
use Throwable;

class WhatsAppWebhookController extends Controller
{
    /**
     * Vérification initiale du webhook par Meta.
     *
     * Meta envoie les paramètres suivants :
     * - hub.mode
     * - hub.verify_token
     * - hub.challenge
     *
     * Selon la configuration PHP, les points peuvent être convertis en
     * underscores. Le contrôleur accepte donc les deux formes.
     */
    public function verify(Request $request): Response
    {
        $mode = $this->queryValue($request, 'hub.mode', 'hub_mode');
        $receivedToken = $this->queryValue($request, 'hub.verify_token', 'hub_verify_token');
        $challenge = $this->queryValue($request, 'hub.challenge', 'hub_challenge');

        $expectedToken = trim((string) config('whatsapp.verify_token', ''));

        if ($expectedToken === '') {
            Log::error('Webhook WhatsApp : WHATSAPP_WEBHOOK_VERIFY_TOKEN absent de la configuration.');

            return response('Configuration du webhook WhatsApp incomplète.', 500)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $isAuthorized = $mode === 'subscribe'
            && $challenge !== ''
            && $receivedToken !== ''
            && hash_equals($expectedToken, $receivedToken);

        if (! $isAuthorized) {
            Log::warning('Échec de vérification du webhook WhatsApp.', [
                'mode_valide' => $mode === 'subscribe',
                'challenge_present' => $challenge !== '',
                'token_present' => $receivedToken !== '',
                'ip' => $request->ip(),
            ]);

            return response('Webhook WhatsApp non autorisé.', 403)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        return response($challenge, 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /**
     * Réception des messages et statuts envoyés par Meta.
     */
    public function receive(
        Request $request,
        MetaWebhookSignatureVerifier $signatureVerifier,
    ): JsonResponse {
        if (! (bool) config('whatsapp.enabled', false)) {
            return response()->json([
                'received' => false,
                'message' => 'Le canal WhatsApp est désactivé.',
            ], 503);
        }

        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signatureVerifier->verify($rawBody, $signature)) {
            Log::warning('Webhook WhatsApp rejeté : signature Meta invalide.', [
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'received' => false,
                'message' => 'Signature Meta invalide.',
            ], 401);
        }

        try {
            $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            Log::warning('Webhook WhatsApp rejeté : JSON invalide.', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'received' => false,
                'message' => 'Payload JSON invalide.',
            ], 400);
        }

        if (! is_array($payload)) {
            return response()->json([
                'received' => false,
                'message' => 'Payload WhatsApp invalide.',
            ], 400);
        }

        if (($payload['object'] ?? null) !== 'whatsapp_business_account') {
            return response()->json([
                'received' => false,
                'message' => 'Type de webhook Meta non pris en charge.',
            ], 400);
        }

        $event = WhatsAppWebhookEvent::query()->firstOrCreate(
            ['event_key' => hash('sha256', $rawBody)],
            [
                'object_type' => $payload['object'] ?? null,
                'signature_valid' => true,
                'status' => 'received',
                'payload' => $payload,
                'received_at' => now(),
            ],
        );

        /*
         * Meta peut renvoyer le même événement plusieurs fois.
         * On ne crée donc le job que lors de la première réception.
         */
        if ($event->wasRecentlyCreated) {
            try {
                ProcessWhatsAppWebhook::dispatch($event->id)->afterResponse();
            } catch (Throwable $exception) {
                // Permet à Meta de retenter si la mise en file d'attente échoue.
                $event->delete();

                Log::error('Impossible de mettre le webhook WhatsApp en file d’attente.', [
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }

        return response()->json([
            'received' => true,
            'duplicate' => ! $event->wasRecentlyCreated,
        ]);
    }

    private function queryValue(Request $request, string $dottedKey, string $underscoredKey): string
    {
        $value = $request->query($underscoredKey);

        if ($value === null) {
            $value = $request->query($dottedKey);
        }

        return trim((string) $value);
    }
}
