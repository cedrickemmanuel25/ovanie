<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\SupportCall;
use App\Services\SupportAi\SupportAiAuditLogger;
use App\Services\SupportAi\SupportTelephonyManager;
use App\Services\SupportAi\TwilioWebhookSignatureValidator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TwilioSupportVoiceController extends Controller
{
    public function voice(
        Request $request,
        SupportTelephonyManager $manager,
        TwilioWebhookSignatureValidator $signatures,
    ): Response {
        $this->authorizeWebhook($request, $signatures);

        $call = $manager->beginTwilioCall($request->all(), $request->query('reference'));
        $name = $call->requester?->name;
        $greeting = $name
            ? "Bonjour {$name}. Bienvenue au Support OVANIE. Je suis N’Nan. Décrivez votre demande après le signal sonore."
            : "Bonjour et bienvenue au Support OVANIE. Je suis N’Nan. Décrivez votre demande après le signal sonore.";

        return $this->xml($this->gatherTwiml($call, $greeting));
    }

    public function gather(
        Request $request,
        SupportTelephonyManager $manager,
        TwilioWebhookSignatureValidator $signatures,
        SupportAiAuditLogger $audit,
    ): Response {
        $this->authorizeWebhook($request, $signatures);

        $call = SupportCall::query()
            ->where('reference', $request->query('reference'))
            ->orWhere('provider_call_id', $request->input('CallSid'))
            ->firstOrFail();

        $result = $manager->handleTwilioSpeech(
            $call,
            (string) $request->input('SpeechResult', ''),
            $request->all(),
        );

        $reply = (string) $result['reply'];
        $transferNumber = (string) config('support_ai.telephony.human_transfer_number');
        $autoTransfer = (bool) config('support_ai.telephony.auto_transfer_sensitive');

        if ($result['requires_human'] && $autoTransfer && $transferNumber !== '') {
            $call->forceFill([
                'status' => 'waiting_transfer',
                'transfer_target' => $transferNumber,
            ])->save();

            $audit->log([
                'support_conversation_id' => $call->support_conversation_id,
                'support_call_id' => $call->id,
                'ai_agent_id' => $call->ai_agent_id,
                'action' => 'twilio_live_transfer_requested',
                'decision' => 'dial_human',
                'risk_level' => 'high',
                'input' => ['destination' => $transferNumber],
            ]);

            return $this->xml(
                '<Response>'
                .$this->say($reply.' Je vous mets maintenant en relation avec un agent humain.')
                .'<Dial callerId="'.$this->escape((string) config('support_ai.telephony.from_number')).'">'
                .'<Number>'.$this->escape($transferNumber).'</Number>'
                .'</Dial>'
                .$this->say('Aucun agent n’a pu répondre immédiatement. Votre dossier reste dans la file prioritaire et l’équipe vous rappellera.')
                .'</Response>',
            );
        }

        if ($result['requires_human']) {
            return $this->xml(
                '<Response>'
                .$this->say($reply)
                .$this->say('Votre dossier a été placé dans la file de traitement humain. Merci de votre appel.')
                .'</Response>',
            );
        }

        return $this->xml($this->gatherTwiml($call, $reply.' Avez-vous une autre précision à ajouter ?'));
    }

    public function status(
        Request $request,
        SupportTelephonyManager $manager,
        TwilioWebhookSignatureValidator $signatures,
    ): Response {
        $this->authorizeWebhook($request, $signatures);
        $manager->handleTwilioStatus($request->all());

        return response('', 204);
    }

    private function gatherTwiml(SupportCall $call, string $prompt): string
    {
        $action = $this->publicRoute('webhooks.support.twilio.gather', ['reference' => $call->reference]);
        $voice = $this->escape((string) config('support_ai.telephony.voice_name', 'alice'));
        $language = $this->escape((string) config('support_ai.telephony.voice_language', 'fr-FR'));

        return '<Response>'
            .'<Gather input="speech" action="'.$this->escape($action).'" method="POST" speechTimeout="auto" language="'.$language.'">'
            .'<Say voice="'.$voice.'" language="'.$language.'">'.$this->escape($prompt).'</Say>'
            .'</Gather>'
            .$this->say('Je n’ai reçu aucune réponse. Vous pouvez rappeler le Support OVANIE ou demander un rappel depuis le site.')
            .'</Response>';
    }

    private function say(string $text): string
    {
        return '<Say voice="'.$this->escape((string) config('support_ai.telephony.voice_name', 'alice')).'" language="'
            .$this->escape((string) config('support_ai.telephony.voice_language', 'fr-FR')).'">'
            .$this->escape($text)
            .'</Say>';
    }

    private function publicRoute(string $name, array $parameters = []): string
    {
        return rtrim((string) config('support_ai.telephony.public_base_url'), '/').route($name, $parameters, false);
    }

    private function authorizeWebhook(Request $request, TwilioWebhookSignatureValidator $signatures): void
    {
        abort_unless(config('support_ai.telephony.provider') === 'twilio', 404);
        abort_unless($signatures->valid($request), 401, 'Signature Twilio invalide.');
    }

    private function xml(string $twiml): Response
    {
        return response($twiml, 200, ['Content-Type' => 'text/xml; charset=UTF-8']);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
