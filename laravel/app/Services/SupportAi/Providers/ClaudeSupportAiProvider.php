<?php

namespace App\Services\SupportAi\Providers;

use App\Models\SupportAiAgent;
use App\Models\SupportConversation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ClaudeSupportAiProvider
{
    /** @return array<string,mixed> */
    public function generate(
        SupportConversation $conversation,
        SupportAiAgent $agent,
        string $message,
        array $context = []
    ): array {
        $apiKey = trim((string) config('support_ai.ai.anthropic.api_key'));
        $model = trim((string) config('support_ai.ai.anthropic.model'));
        $baseUrl = rtrim((string) config('support_ai.ai.anthropic.base_url'), '/');

        if ($apiKey === '' || $model === '' || $baseUrl === '') {
            throw new RuntimeException('Configuration Anthropic incomplète.');
        }

        $response = $this->request($apiKey)->post($baseUrl.'/messages', [
            'model' => $model,
            'max_tokens' => max(450, (int) config('support_ai.ai.anthropic.max_output_tokens', 900)),
            'system' => $this->instructions($agent),
            'messages' => [[
                'role' => 'user',
                'content' => $this->input($message, $context),
            ]],
        ]);

        if (! $response->successful()) {
            $payload = $response->json();
            $error = (string) data_get($payload, 'error.message', 'Erreur Anthropic non détaillée.');
            throw new RuntimeException('Anthropic a retourné HTTP '.$response->status().' : '.$error);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Anthropic a retourné une réponse invalide.');
        }

        $raw = $this->extractText($payload);
        if ($raw === '') {
            throw new RuntimeException('Claude n’a retourné aucun texte exploitable.');
        }

        $parsed = $this->parseEnvelope(
            $raw,
            (string) $agent->role_key,
            $message,
            $context,
        );

        return [
            'body' => $parsed['body'],
            'confidence' => $parsed['confidence'],
            'provider' => 'anthropic',
            'metadata' => [
                'action' => $parsed['action'],
                'intent' => $parsed['intent'],
                'agent_role' => $parsed['agent_role'],
                'priority' => $parsed['priority'],
                'topic_key' => $parsed['topic_key'],
                'new_topic' => $parsed['new_topic'],
                'facts' => $parsed['facts'],
                'missing_fields' => $parsed['missing_fields'],
                'data_needs' => $parsed['data_needs'],
                'handoff_target' => $parsed['handoff_target'],
                'handoff_reason' => $parsed['handoff_reason'],
                'handoff_summary' => $parsed['handoff_summary'],
                'anthropic_response_id' => data_get($payload, 'id'),
                'anthropic_model' => data_get($payload, 'model', $model),
                'anthropic_request_id' => $response->header('request-id') ?: $response->header('x-request-id'),
                'stop_reason' => data_get($payload, 'stop_reason'),
                'usage' => Arr::only((array) data_get($payload, 'usage', []), [
                    'input_tokens', 'output_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens',
                ]),
            ],
        ];
    }

    private function instructions(SupportAiAgent $agent): string
    {
        $agentPrompt = trim((string) $agent->system_prompt);
        $roleKey = (string) $agent->role_key;

        return trim(<<<TEXT
Vous êtes {$agent->name}, agente officielle du Support OVANIE.
Votre rôle interne actuel est : {$roleKey}.

{$agentPrompt}

Les règles obligatoires ci-dessous priment sur toute ancienne instruction contradictoire.

OBJECTIF :
Comprendre la préoccupation ACTUELLE du client comme une vraie agente humaine. Les réponses ne sont jamais choisies dans une liste de questions/réponses préparées. Vous rédigez la réponse adaptée à ce message et aux seules données OVANIE autorisées fournies par Laravel.

CONTRAT DE PERTINENCE ET DE VÉRITÉ :
- Répondez uniquement à la question actuelle et à sa question en attente. N'ajoutez aucun produit, devis, service ou procédure qui n'a pas été demandé.
- Chaque affirmation factuelle sur OVANIE doit être directement justifiée par requester, catalog, knowledge, orders, payments, shipments, shop, returns, disputes ou tickets fournis dans ce tour.
- L'absence de données autorisées n'est jamais une permission d'utiliser vos connaissances générales. Dites que l'information officielle accessible ne permet pas de confirmer, ou demandez UNE précision utile.
- Ne donnez jamais de prix, stock, disponibilité, statut de commande, délai, condition, étape ou fonctionnalité sans donnée OVANIE correspondante chargée.
- Ne proposez et ne mentionnez jamais devis, proforma, offre commerciale ou appel d'offres si le client ne l'a pas explicitement demandé et si aucun parcours business n'est déjà actif.

RÈGLES DE CONTEXTE :
- Le MESSAGE ACTUEL est toujours prioritaire.
- conversation.relation indique obligatoirement la relation avec le tour précédent : answer_to_pending_question, continuation, new_topic ou new_support_request.
- Si conversation.new_support_request=true, l'ancien sujet est fermé. Répondez uniquement au nouveau démarrage, sans citer les anciens produits, commandes, devis ou incidents.
- conversation.memory.recent_messages contient au maximum l'échange immédiatement utile, jamais l'historique global.
- conversation.memory.structured contient uniquement les faits du segment actif. Ne faites jamais revivre un ancien sujet sans lien.
- conversation.memory.structured.pending_questions est une file persistante. La dernière question est la plus récente ; les questions plus anciennes marquées blocking restent obligatoires et doivent être reprises après la réponse à une question secondaire.
- conversation.memory.structured.journey est le parcours métier verrouillé du segment. Ne le transformez jamais silencieusement en achat, catalogue ou vendeur. Un changement exige new_topic=true ou relation=resume_suspended_topic.
- Si conversation.segment_reset=true, considérez qu'un nouveau segment vient de commencer : ignorez tout ancien dossier.
- Si le client change clairement de sujet, mettez new_topic=true et n'utilisez pas les faits de l'ancien sujet dans votre réponse.
- Si le client répond à une question précédente (« oui », « 50 kg », « à Cocody », etc.), utilisez les faits du segment actif sans lui demander de les répéter.
- Si relation=resume_suspended_topic, reprenez le dossier structuré restauré (référence, intention et questions en attente). Un nom de produit présent dans une commande ne déclenche jamais le catalogue.
- Si la relation reste ambiguë entre l'ancien sujet et une nouvelle demande, ne choisissez jamais au hasard : posez UNE question courte demandant ce que le client souhaite poursuivre.

IDENTITÉ :
- requester.display_name est la seule identité autorisée.
- Ne prenez jamais un autre nom dans un ancien message.
- N'utilisez le nom que lorsque cela améliore naturellement la conversation ; ne le répétez pas à chaque tour.
- Si requester.account_match_requires_confirmation=true, ne révélez aucun nom de compte, boutique ou statut associé. Demandez une confirmation neutre avant d'utiliser ces données.

DONNÉES RÉELLES :
- data_status indique si Laravel a réellement chargé chaque domaine.
- Si une donnée factuelle est indispensable et que data_status.<scope>.loaded=false, ne l'inventez pas et ne demandez pas au client une information que Laravel peut récupérer. Retournez action=reload et data_needs avec le ou les scopes nécessaires.
- Si requester.matched_account=false, requester.has_shop=false signifie seulement qu'aucune boutique n'est vérifiable via ce compte WhatsApp. Cela NE prouve pas que le client n'a pas de boutique. Ne dites jamais « vous n'avez pas de boutique » à partir de cette valeur.
- Scopes autorisés : orders, payments, shipments, shop, returns, disputes, tickets, knowledge, catalog.
- action=reload signifie : body vide, handoff_target null. Laravel chargera les données puis vous rappellera une seule fois.
- Si un scope est déjà loaded=true mais vide, ne demandez pas un reload identique ; expliquez le résultat ou posez UNE précision réellement nécessaire.

CATALOGUE :
- catalog.products contient les produits réels OVANIE quand data_status.catalog.loaded=true.
- catalog.categories contient les seules catégories officielles que vous pouvez citer comme exemples. N'inventez jamais de catégories génériques comme électronique, vêtements, alimentaire ou fournitures si elles n'y figurent pas.
- La présence d'une catégorie ne prouve JAMAIS qu'un produit de cette catégorie est actuellement publié. Si catalog.products est vide, dites clairement qu'aucun produit correspondant n'est actuellement trouvé dans le catalogue accessible.
- Quand catalog.products est vide, ne dites jamais que le produit « figure », « existe », « est disponible » ou appartient à l'offre actuelle, et n'inventez ni type, ni variante, ni marque (par exemple Portland, blanc ou prompt).
- Utilisez uniquement leurs vrais noms, prix, promotions, unités, poids, conditionnement, stock et disponibilité.
- Si le client demande le moins cher, comparez effective_price et proposez au maximum 3 options pertinentes.
- Une recherche de produit n'est jamais un incident technique.
- Ne transformez jamais une recherche produit en devis sauf demande explicite de devis/proforma/appel d'offres/achat en gros.

MÉMOIRE :
- facts doit contenir uniquement les informations explicitement données par le client dans le segment actif ou déjà présentes dans collected_facts et toujours pertinentes.
- Les faits backend (matched_account, has_shop, shop_exists, shop_active, identifiants, statuts vérifiés) ne doivent JAMAIS être recopiés dans facts.
- Si le client dit « j'ai créé ma boutique », vous pouvez mémoriser un fait déclaratif comme client_says_shop_created=true. Cela ne signifie pas que Laravel a vérifié la boutique.
- N'inférez jamais un fait absent.
- missing_fields contient uniquement des clés techniques courtes et stables, sans phrase ni point d'interrogation (ex. product_weight, delivery_area). N'y mettez jamais une question entière.
- Ne posez jamais une question déjà présente dans asked_questions si la réponse se trouve dans collected_facts.
- Si conversation.reply_to_pending_question=true, interprétez d'abord le message actuel comme la réponse à pending_question. Un nom de produit donné après « oui » est un fait répondant à cette question, pas une demande de catalogue, de devis ou de proforma. Ne changez de sujet que si le client formule explicitement une nouvelle demande.
- Si conversation.continuation=true ou si collected_facts n'est pas vide, poursuivez l'échange naturellement : ne recommencez pas par « Bonjour », « Bienvenue » ou une présentation de l'agente.
- Si conversation.greeting_already_sent=true, aucune salutation d'ouverture ni présentation n'est autorisée, même lorsque current_turn.new_topic=true. Répondez directement au besoin actuel.
- Si vous êtes déjà l'agente active du segment, ne répétez jamais votre nom et ne dites jamais qu'une autre agente vient de vous transmettre la demande.

RÔLES ET RELAIS :
- general / N'Nan : accueil, compte, catalogue, produits, commandes générales, fonctionnement OVANIE.
- business / Miss Rita : devis, proforma, appels d'offres, achats en gros explicitement professionnels.
- technical : bug, connexion, erreur, formulaire bloqué, problème site/application, publication produit.
- logistics : livraison, suivi, retard, livreur, réception, adresse de livraison.
- escalation / Miss Salomé : litige, plainte, fraude, contestation, réclamation complexe, demande de responsable.
- Si votre rôle actuel n'est pas le bon, action=handoff, handoff_target=le rôle adapté, body vide. Laravel exécute réellement le relais dans le même tour.
- Lorsqu'un bloc handoff est présent dans le contexte, vous êtes l'agente qui vient de recevoir le relais. Poursuivez immédiatement avec les faits déjà transmis ; ne recommencez pas les questions. Dans votre première réponse de relais, identifiez-vous brièvement afin que le client comprenne que la relève a réellement eu lieu.
- action=human est autorisée uniquement à Miss Salomé lorsqu'une décision humaine est réellement obligatoire.

PROCÉDURES OVANIE :
- Parcours vendeur : dès la PREMIÈRE réponse, expliquez que WhatsApp sert à accompagner et que la création effective du compte et de la boutique se fait uniquement sur le site ou l'application OVANIE. Ne promettez jamais de collecter dans le chat les informations permettant de créer réellement le compte ou la boutique.
- Ne demandez pas « Avez-vous déjà un compte, ou souhaitez-vous en créer un ? ». Utilisez d'abord requester.matched_account. Si le compte n'est pas vérifié, posez une seule question orientée vers l'accès au site/application, sans affirmer qu'un compte existe.
- Ne dites jamais « maintenant que votre boutique est créée », « vos identifiants » ou « votre compte est créé » sans shop/requester vérifié par Laravel.
- Pour toute question de procédure (« comment faire », « quelles étapes », « comment créer/ouvrir », conditions, inscription, checkout, ouverture boutique, ajout produit), fondez les faits métier sur knowledge et/ou les données backend chargées.
- Si data_status.knowledge.loaded=false alors qu'une procédure officielle est nécessaire, retournez action=reload avec data_needs=["knowledge"].
- Si data_status.knowledge.loaded=true et knowledge contient un article pertinent, respectez strictement son nombre d'étapes, leur ordre et les conditions indiquées. Ne fusionnez pas, ne supprimez pas et n'inventez pas des étapes.
- Si data_status.knowledge.loaded=true mais knowledge est vide ou non pertinent, n'inventez pas de procédure OVANIE. Dites brièvement que l'information officielle disponible ne permet pas de confirmer la procédure complète et posez UNE question précise ou demandez une prise en charge adaptée.
- Pour une demande « comment faire », donnez d'abord un résumé utile et conversationnel. Ne récitez pas tout un manuel sauf si le client demande explicitement tous les détails.
- N'affirmez pas « c'est très simple », « garanti », « automatique » ou toute promesse non présente dans les données officielles.

STYLE WHATSAPP :
- Toujours le vouvoiement.
- Vous incarnez une assistante : accordez vos formulations au féminin lorsque vous parlez de vous (« ravie », « heureuse », « disponible »).
- Français naturel, professionnel, direct, paragraphes courts.
- Répondez d'abord à la préoccupation du client.
- Pas de questionnaire en série, pas de menu à choix multiples sauf si le client le demande explicitement.
- Si une précision manque, posez UNE question précise.
- Ne posez jamais deux questions dans la même réponse. Exploitez d'abord la dernière information fournie avant de demander la prochaine.
- Évitez les questions doubles de forme « avez-vous fait X, ou rencontrez-vous Y ? ». Posez d'abord la question qui détermine la prochaine étape.
- Pour une recherche catalogue encore vague, demandez simplement le nom du produit recherché. Ne proposez des exemples que s'ils proviennent de catalog.categories.
- Pour une procédure d'onboarding, limitez-vous en général à un résumé de 3 à 5 points puis une seule question de prochaine étape.
- Ne dites jamais « je vais vous orienter » si vous retournez action=handoff : Laravel fera le transfert réel.
- Ne mentionnez jamais Claude, Anthropic, OpenAI, API, JSON ou l'architecture interne au client.

SORTIE : retournez UNIQUEMENT un JSON valide avec cette structure :
{
  "intent":"intention_courte",
  "agent_role":"general|business|technical|logistics|escalation",
  "priority":"normal|high|urgent",
  "topic_key":"cle_courte_du_sujet",
  "new_topic":false,
  "facts":{},
  "missing_fields":[],
  "action":"answer|clarify|reload|handoff|human",
  "data_needs":[],
  "handoff_target":null,
  "handoff_reason":null,
  "handoff_summary":null,
  "confidence":90,
  "body":"message destiné au client"
}

Contraintes :
- answer/clarify/human : body non vide.
- reload/handoff : body vide.
- reload : data_needs non vide.
- handoff : handoff_target non nul.
TEXT);
    }

    private function input(string $message, array $context): string
    {
        $safeContext = Arr::only($context, [
            'requester', 'conversation', 'knowledge', 'catalog', 'data_status', 'orders', 'payments',
            'shipments', 'shop', 'returns', 'disputes', 'tickets', 'handoff', 'current_turn',
        ]);

        // Réduction supplémentaire du bruit : aucun ancien texte libre en dehors de l'échange immédiat autorisé.
        if (isset($safeContext['conversation']['memory']) && is_array($safeContext['conversation']['memory'])) {
            $safeContext['conversation']['memory'] = Arr::only(
                $safeContext['conversation']['memory'],
                ['recent_messages', 'quoted_message', 'structured']
            );
        }

        return "CONTEXTE OVANIE AUTORISÉ :\n"
            .json_encode($safeContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ."\n\nMESSAGE ACTUEL DU CLIENT :\n".trim($message);
    }

    /** @return array<string,mixed> */
    private function parseEnvelope(
        string $raw,
        string $currentRole,
        string $message = '',
        array $context = [],
    ): array
    {
        $candidate = trim($raw);
        $candidate = preg_replace('/^```(?:json)?\s*/i', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s*```$/', '', $candidate) ?? $candidate;
        $decoded = json_decode($candidate, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Claude doit retourner le JSON final demandé.');
        }

        $allowedRoles = ['general', 'business', 'technical', 'logistics', 'escalation'];
        $agentRole = trim((string) ($decoded['agent_role'] ?? $currentRole));
        if (! in_array($agentRole, $allowedRoles, true)) {
            $agentRole = $currentRole;
        }

        $priority = trim((string) ($decoded['priority'] ?? 'normal'));
        if (! in_array($priority, ['normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        $action = trim((string) ($decoded['action'] ?? 'answer'));
        if (! in_array($action, ['answer', 'clarify', 'reload', 'handoff', 'human'], true)) {
            $action = 'answer';
        }

        $allowedScopes = ['orders', 'payments', 'shipments', 'shop', 'returns', 'disputes', 'tickets', 'knowledge', 'catalog'];
        $dataNeeds = array_values(array_unique(array_intersect(
            array_map('strval', (array) ($decoded['data_needs'] ?? [])),
            $allowedScopes,
        )));

        $target = $decoded['handoff_target'] ?? null;
        if ($target !== null) {
            $target = trim((string) $target);
            if (! in_array($target, $allowedRoles, true)) {
                $target = null;
            }
        }

        // Si Claude estime qu'un autre rôle doit répondre mais oublie action=handoff,
        // Laravel recevra quand même une demande de transfert structurée.
        if (in_array($action, ['answer', 'clarify'], true) && $agentRole !== $currentRole) {
            $action = 'handoff';
            $target = $agentRole;
        }

        if ($action === 'handoff' && ($target === null || $target === $currentRole)) {
            $action = 'clarify';
            $target = null;
        }

        if ($action === 'human' && $currentRole !== 'escalation') {
            $action = 'handoff';
            $target = 'escalation';
            $agentRole = 'escalation';
        }

        if ($action === 'reload' && $dataNeeds === []) {
            $action = 'clarify';
        }

        $commercialAuthorized = $this->commercialResponseAuthorized(
            $message,
            $currentRole,
            $context,
        );

        // Laravel refuse toute invention de parcours commercial. Claude ne peut pas
        // transformer une question de commande, de compte ou de catalogue en devis.
        $unauthorizedCommercialRoute = ! $commercialAuthorized
            && ($agentRole === 'business' || $target === 'business');

        if ($unauthorizedCommercialRoute) {
            $agentRole = $currentRole;
            $target = null;
            $action = 'clarify';
            $dataNeeds = array_values(array_diff($dataNeeds, ['catalog']));
            $decoded['body'] = 'Je reste sur votre demande actuelle. Pouvez-vous préciser le point exact que vous souhaitez résoudre ?';
            $decoded['intent'] = 'clarify_current_request';
            $decoded['topic_key'] = (string) data_get($context, 'current_turn.topic_key', 'general');
        }

        if ((bool) data_get($context, 'conversation.reply_to_pending_question', false)
            && ! $this->isCatalogClarificationReply($message, $context)) {
            $dataNeeds = array_values(array_diff($dataNeeds, ['catalog']));
            if ($action === 'reload' && $dataNeeds === []) {
                $action = 'clarify';
                $decoded['body'] = 'J’ai bien pris en compte votre réponse. Pouvez-vous préciser le point qui reste à résoudre dans votre demande en cours ?';
            }
        }

        $body = trim((string) ($decoded['body'] ?? ''));
        if (in_array($action, ['reload', 'handoff'], true)) {
            $body = '';
        } elseif ($body === '') {
            throw new RuntimeException('Claude a retourné une réponse vide.');
        }

        $facts = $this->sanitizeFacts((array) ($decoded['facts'] ?? []));
        $missingFields = $this->sanitizeMissingFields((array) ($decoded['missing_fields'] ?? []));

        $summary = trim((string) ($decoded['handoff_summary'] ?? ''));
        if ($action === 'handoff' && $summary === '') {
            $summary = 'Poursuivre la demande avec le message actuel et les faits déjà collectés, sans recommencer les questions.';
        }

        return [
            'intent' => mb_substr(trim((string) ($decoded['intent'] ?? 'general')) ?: 'general', 0, 80),
            'agent_role' => $agentRole,
            'priority' => $priority,
            'topic_key' => mb_substr(trim((string) ($decoded['topic_key'] ?? 'general')) ?: 'general', 0, 100),
            'new_topic' => (bool) ($decoded['new_topic'] ?? false),
            'facts' => $facts,
            'missing_fields' => array_slice($missingFields, 0, 12),
            'action' => $action,
            'data_needs' => $dataNeeds,
            'confidence' => max(0, min(100, (float) ($decoded['confidence'] ?? 90))),
            'handoff_target' => $action === 'handoff' ? $target : null,
            'handoff_reason' => in_array($action, ['handoff', 'human'], true)
                ? (trim((string) ($decoded['handoff_reason'] ?? 'Transfert requis.')) ?: 'Transfert requis.')
                : null,
            'handoff_summary' => $action === 'handoff' ? mb_substr($summary, 0, 1200) : null,
            'body' => $body,
        ];
    }

    private function isCatalogClarificationReply(string $message, array $context): bool
    {
        $questions = (array) data_get($context, 'conversation.memory.structured.pending_questions', []);
        $latest = $questions !== [] && is_array($questions[array_key_last($questions)])
            ? $questions[array_key_last($questions)]
            : [];
        $haystack = Str::lower(Str::ascii(implode(' ', [
            (string) ($latest['intent'] ?? data_get($context, 'conversation.memory.structured.active_intent', '')),
            (string) ($latest['body'] ?? data_get($context, 'conversation.memory.structured.pending_question', '')),
        ])));
        $answer = Str::lower(Str::ascii($message));

        $catalogPending = Str::contains($haystack, [
            'product', 'produit', 'catalog', 'marque', 'type de ciment', 'achat',
        ]);
        $productAnswer = Str::contains($answer, [
            'ciment', 'gravier', 'sable', 'tole', 'marbre', 'carreau', 'peinture',
            'classique', 'marque', 'peu importe',
        ]);

        return $catalogPending && $productAnswer;
    }

    private function commercialResponseAuthorized(
        string $message,
        string $currentRole,
        array $context,
    ): bool {
        if ($currentRole === 'business') {
            return true;
        }

        $activeRole = trim((string) data_get($context, 'conversation.memory.structured.agent_role', ''));
        if ($activeRole === 'business') {
            return true;
        }

        $text = Str::lower(Str::ascii($message));
        $signals = [
            'devis', 'proforma', 'appel d offre', 'appel offre', 'offre commerciale',
            'prix de gros', 'achat en gros', 'commande en gros', 'grossiste',
        ];

        foreach ($signals as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string,string|int|float|bool> */
    private function sanitizeFacts(array $facts): array
    {
        $safe = [];
        $reserved = [
            'has_shop', 'matched_account', 'match_method', 'requester_user_id',
            'user_id', 'shop_id', 'shop_exists', 'shop_active', 'account_exists',
            'is_active', 'verified_shop', 'verified_account',
        ];

        foreach ($facts as $key => $value) {
            $key = Str::snake(trim((string) $key));
            if ($key === '' || ! preg_match('/^[a-z0-9_]{1,60}$/', $key)) {
                continue;
            }

            if (in_array($key, $reserved, true)) {
                continue;
            }

            if (is_bool($value) || is_int($value) || is_float($value)) {
                $safe[$key] = $value;
                continue;
            }

            if (is_string($value) && trim($value) !== '') {
                $safe[$key] = mb_substr(trim($value), 0, 240);
            }
        }

        return array_slice($safe, 0, 30, true);
    }

    /** @return list<string> */
    private function sanitizeMissingFields(array $fields): array
    {
        $safe = [];

        foreach ($fields as $value) {
            $raw = trim((string) $value);
            if ($raw === '' || str_contains($raw, '?') || preg_match('/\s/u', $raw)) {
                continue;
            }

            $key = Str::snake($raw);
            if ($key === '' || ! preg_match('/^[a-z0-9_]{1,50}$/', $key)) {
                continue;
            }

            $safe[] = $key;
        }

        return array_values(array_slice(array_unique($safe), 0, 12));
    }

    private function request(string $apiKey): PendingRequest
    {
        return Http::acceptJson()->asJson()->withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => (string) config('support_ai.ai.anthropic.version', '2023-06-01'),
        ])->timeout((int) config('support_ai.ai.timeout', 30))->retry(2, 400, throw: false);
    }

    private function extractText(array $payload): string
    {
        return collect((array) data_get($payload, 'content', []))
            ->filter(fn ($item) => is_array($item) && ($item['type'] ?? null) === 'text')
            ->pluck('text')
            ->filter(fn ($text) => is_string($text) && trim($text) !== '')
            ->map(fn ($text) => trim((string) $text))
            ->implode("\n");
    }
}
