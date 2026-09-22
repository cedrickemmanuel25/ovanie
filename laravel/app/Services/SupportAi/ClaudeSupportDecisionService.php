<?php

namespace App\Services\SupportAi;

use App\Models\SupportConversation;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ClaudeSupportDecisionService
{
    /** @return array<string,mixed> */
    public function decide(
        SupportConversation $conversation,
        string $message,
        array $authorizedContext,
    ): array {
        $standaloneGreeting = $this->isStandaloneGreeting($message);

        if ($standaloneGreeting) {
            $authorizedContext = $this->sanitizeGreetingContext($authorizedContext);
        }

        try {
            $apiKey = trim((string) config('support_ai.ai.anthropic.api_key'));
            $model = trim((string) config('support_ai.ai.anthropic.model'));
            $baseUrl = rtrim((string) config('support_ai.ai.anthropic.base_url'), '/');

            if ($apiKey === '' || $model === '' || $baseUrl === '') {
                throw new RuntimeException('Configuration Anthropic incomplète pour le planificateur Support OVANIE.');
            }

            $response = $this->request($apiKey)->post($baseUrl.'/messages', [
                'model' => $model,
                'max_tokens' => max(350, (int) config('support_ai.ai.anthropic.planner_max_output_tokens', 650)),
                'system' => $this->instructions(),
                'messages' => [[
                    'role' => 'user',
                    'content' => json_encode([
                        'current_message' => trim($message),
                        'authorized_context' => $authorizedContext,
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]],
            ]);

            if (! $response->successful()) {
                $payload = $response->json();
                $error = (string) data_get($payload, 'error.message', 'Erreur Anthropic non détaillée.');
                throw new RuntimeException('Anthropic planificateur a retourné HTTP '.$response->status().' : '.$error);
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                throw new RuntimeException('Anthropic planificateur a retourné une réponse invalide.');
            }

            $text = $this->extractText($payload);
            if ($text === '') {
                throw new RuntimeException('Claude planificateur n’a retourné aucune décision exploitable.');
            }

            $decision = $this->parseDecision($text);

            if ($standaloneGreeting) {
                $decision = array_merge($decision, [
                    'intent' => 'greeting',
                    'agent_role' => 'general',
                    'priority' => 'normal',
                    'topic_key' => 'greeting',
                    'facts' => [],
                    'missing_fields' => [],
                    'reason' => 'Salutation autonome protégée par Laravel.',
                ]);
            }

            $decision['metadata'] = [
                'planner_provider' => 'anthropic',
                'anthropic_response_id' => data_get($payload, 'id'),
                'anthropic_model' => data_get($payload, 'model', $model),
                'anthropic_request_id' => $response->header('request-id') ?: $response->header('x-request-id'),
                'usage' => Arr::only((array) data_get($payload, 'usage', []), [
                    'input_tokens', 'output_tokens', 'cache_creation_input_tokens', 'cache_read_input_tokens',
                ]),
            ];

            return $decision;
        } catch (Throwable $exception) {
            report($exception);
            $decision = $this->fallbackDecision($message, $authorizedContext);
            $decision['metadata'] = [
                'planner_provider' => 'deterministic_fallback',
                'fallback_reason' => mb_substr($exception->getMessage(), 0, 1000),
            ];
            return $decision;
        }
    }

    private function instructions(): string
    {
        return <<<'TEXT'
Tu es le PLANIFICATEUR interne du Support OVANIE. Tu ne réponds JAMAIS au client.

ARCHITECTURE : Laravel a déjà identifié le client, rassemblé les données utiles et filtré les données autorisées. Tu dois uniquement comprendre le besoin, choisir l'agente et structurer les informations explicites déjà données.

RÈGLES DE CONTINUITÉ :
- Le message actuel est prioritaire.
- conversation.memory.structured.collected_facts contient les informations déjà fournies par le client. Ne les redemande pas.
- conversation.memory.structured.missing_fields contient les informations encore utiles. Supprime mentalement toute information déjà présente dans collected_facts.
- Ne ressors jamais un ancien sujet sans lien avec le message actuel.
- Une salutation simple = general, sans ancien dossier.

ROUTAGE :
- general / N'Nan : accueil, compte, catalogue, recherche de produit, prix affiché, disponibilité catalogue, boutique, commande générale et fonctionnement OVANIE.
- business / Miss Rita : devis, proforma, appels d'offres, achat EN GROS ou besoin professionnel explicitement commercial.
- technical : bug, erreur, connexion impossible, page qui ne charge pas, formulaire bloqué, ajout/modification/publication produit impossible. IMPORTANT : « je ne trouve pas un produit dans le catalogue » n'est PAS technique ; c'est general/product_search. « le catalogue affiche une erreur / ne charge pas » est technique.
- logistics : livraison, suivi, retard, livreur, expédition, réception, adresse de livraison.
- escalation / Miss Salomé : litige, plainte, fraude, contestation, réclamation complexe, demande de responsable.

PRODUITS :
- Si catalog.products contient des résultats, le Support a bien accès au catalogue. Ne prétends jamais l'inverse.
- Un client qui cherche un sac de ciment ou le prix le moins cher veut d'abord une recherche catalogue, pas un devis.
- Ne transforme pas une recherche normale en devis simplement parce qu'une quantité ou un lieu sont mentionnés.

FACTS :
Retourne dans facts uniquement les informations EXPLICITEMENT données ou clairement présentes dans le contexte courant : product, brand, category, weight, quantity, packaging, delivery_area, delivery_quarter, purchase_frequency, order_reference, payment_reference, etc. N'invente rien.

MISSING_FIELDS :
- Liste seulement les informations réellement indispensables pour répondre au prochain tour.
- N'inclus jamais un champ déjà présent dans collected_facts ou facts.
- Pour une simple recherche catalogue, si le nom du produit suffit pour obtenir des résultats, missing_fields doit être vide.

Retourne UNIQUEMENT un JSON valide :
{
  "intent":"nom_court",
  "agent_role":"general|business|technical|logistics|escalation",
  "priority":"normal|high|urgent",
  "topic_key":"cle_courte",
  "facts":{},
  "missing_fields":[],
  "reason":"raison interne courte"
}
TEXT;
    }

    /** @return array<string,mixed> */
    private function parseDecision(string $raw): array
    {
        $candidate = trim($raw);
        $candidate = preg_replace('/^```(?:json)?\s*/i', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s*```$/', '', $candidate) ?? $candidate;
        $decoded = json_decode($candidate, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Décision Claude invalide : JSON attendu.');
        }

        $role = (string) ($decoded['agent_role'] ?? 'general');
        if (! in_array($role, ['general', 'business', 'technical', 'logistics', 'escalation'], true)) {
            $role = 'general';
        }

        $priority = (string) ($decoded['priority'] ?? 'normal');
        if (! in_array($priority, ['normal', 'high', 'urgent'], true)) {
            $priority = 'normal';
        }

        $facts = [];
        foreach ((array) ($decoded['facts'] ?? []) as $key => $value) {
            $key = Str::snake(trim((string) $key));
            if ($key === '' || ! preg_match('/^[a-z0-9_]{1,60}$/', $key)) {
                continue;
            }
            if (is_bool($value) || is_int($value) || is_float($value)) {
                $facts[$key] = $value;
            } elseif (is_string($value) && trim($value) !== '') {
                $facts[$key] = mb_substr(trim($value), 0, 240);
            }
        }

        $missing = collect((array) ($decoded['missing_fields'] ?? []))
            ->map(fn ($value) => Str::snake(trim((string) $value)))
            ->filter(fn ($value) => $value !== '' && preg_match('/^[a-z0-9_]{1,60}$/', $value))
            ->unique()
            ->take(12)
            ->values()
            ->all();

        return [
            'intent' => trim((string) ($decoded['intent'] ?? 'general')) ?: 'general',
            'agent_role' => $role,
            'priority' => $priority,
            'topic_key' => trim((string) ($decoded['topic_key'] ?? 'general')) ?: 'general',
            'facts' => array_slice($facts, 0, 30, true),
            'missing_fields' => $missing,
            'reason' => trim((string) ($decoded['reason'] ?? '')),
        ];
    }

    /** @return array<string,mixed> */
    private function fallbackDecision(string $message, array $context): array
    {
        if ($this->isStandaloneGreeting($message)) {
            return [
                'intent' => 'greeting', 'agent_role' => 'general', 'priority' => 'normal',
                'topic_key' => 'greeting', 'facts' => [], 'missing_fields' => [],
                'reason' => 'Salutation autonome : fallback Laravel.',
            ];
        }

        $text = Str::lower(Str::ascii($message));
        $role = 'general';
        $intent = 'general';
        $priority = 'normal';

        if ($this->containsAny($text, ['litige', 'plainte', 'fraude', 'contestation', 'reclamation', 'responsable'])) {
            $role = 'escalation'; $intent = 'dispute_or_escalation'; $priority = 'high';
        } elseif ($this->containsAny($text, ['livraison', 'livreur', 'suivi', 'colis', 'retard', 'expedition'])) {
            $role = 'logistics'; $intent = 'logistics';
        } elseif ($this->containsAny($text, ['bug', 'erreur', 'ne charge pas', 'connexion impossible', 'formulaire bloque', 'publication impossible'])) {
            $role = 'technical'; $intent = 'technical';
        } elseif ($this->containsAny($text, ['devis', 'proforma', 'appel d offres', 'prix de gros', 'achat en gros'])) {
            $role = 'business'; $intent = 'business';
        } elseif ($this->containsAny($text, ['catalogue', 'produit', 'ciment', 'brique', 'peinture', 'gravier', 'sable', 'prix'])) {
            $role = 'general'; $intent = 'product_search';
        } elseif ($this->containsAny($text, ['paiement', 'debite', 'wave', 'orange money', 'mtn', 'moov', 'transaction'])) {
            $intent = 'payment';
        } elseif ($this->containsAny($text, ['commande', 'facture', 'cmd-'])) {
            $intent = 'order';
        }

        return [
            'intent' => $intent,
            'agent_role' => $role,
            'priority' => $priority,
            'topic_key' => $intent,
            'facts' => [],
            'missing_fields' => [],
            'reason' => 'Routeur déterministe de secours.',
        ];
    }

    private function isStandaloneGreeting(string $message): bool
    {
        $clean = Str::lower(Str::ascii(trim($message)));
        $clean = trim(preg_replace('/[^a-z0-9 ]+/', ' ', $clean) ?? $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? $clean);
        return in_array($clean, ['bonjour','bonsoir','salut','hello','coucou','bonjour ovanie','bonsoir ovanie','salut ovanie'], true);
    }

    /** @return array<string,mixed> */
    private function sanitizeGreetingContext(array $context): array
    {
        $context['conversation'] = [
            'channel' => data_get($context, 'conversation.channel'),
            'memory' => ['recent_messages' => [], 'quoted_message' => null, 'structured' => []],
        ];
        foreach (['knowledge','orders','payments','shipments','returns','disputes','tickets'] as $key) {
            $context[$key] = [];
        }
        $context['shop'] = null;
        $context['catalog'] = ['query' => '', 'terms' => [], 'products' => []];
        return $context;
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, Str::lower(Str::ascii((string) $needle)))) {
                return true;
            }
        }
        return false;
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
            ->pluck('text')->filter(fn ($text) => is_string($text) && trim($text) !== '')
            ->map(fn ($text) => trim((string) $text))->implode("\n");
    }
}
