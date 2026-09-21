<?php

namespace App\Services\SupportAi;

use App\Models\SupportConversation;
use App\Models\SupportConversationMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class SupportConversationMemoryService
{
    /** @return array<string,mixed> */
    public function build(
        SupportConversation $conversation,
        ?int $currentMessageId = null,
        array $currentMessageMetadata = [],
    ): array {
        $currentBody = '';
        if ($currentMessageId) {
            $currentBody = trim((string) $conversation->messages()
                ->whereKey($currentMessageId)
                ->value('body'));
        }

        $quotedMessage = $this->quotedMessage(
            $conversation,
            $currentMessageMetadata['whatsapp_context_message_id'] ?? null,
        );

        $stored = (array) data_get($conversation->metadata ?? [], 'ai_memory', []);

        $newSupportRequest = $this->isExplicitNewSupportRequest($currentBody);
        $resume = ! $newSupportRequest
            ? $this->findResumableSegment($currentBody, $stored)
            : null;
        if ($resume !== null) {
            $stored = $resume['segment'];
            $stored['suspended_segments'] = $resume['remaining'];
        }

        // Une salutation pure coupe l'ancien contexte uniquement si aucune session métier
        // récente n'est active. Cela évite de perdre un parcours en cours simplement parce
        // que le client dit « salut » au milieu de l'échange.
        $freshBusinessSegment = $this->hasFreshBusinessSegment($stored);
        $segmentReset = $newSupportRequest
            || ($this->isSocialGreeting($currentBody) && ! $freshBusinessSegment);

        $continuation = $resume !== null || (! $segmentReset && $this->isLikelyContinuation(
            $conversation,
            $currentMessageId,
            $currentBody,
            $stored,
            $quotedMessage,
        ));

        $recentMessages = $continuation && $resume === null
            ? $this->previousExchange($conversation, $currentMessageId, $quotedMessage)
            : [];

        // Un message autonome ne reçoit AUCUN état métier ancien.
        // La mémoire structurée n'est transmise que lorsqu'il existe un lien immédiat
        // démontrable avec le tour précédent (réponse courte, question en attente ou message cité).
        $structured = ($segmentReset || ! $continuation)
            ? []
            : Arr::only($stored, [
                'segment_id',
                'segment_started_at',
                'active_intent',
                'journey',
                'agent_role',
                'topic_key',
                'known_references',
                'collected_facts',
                'missing_fields',
                'asked_questions',
                'pending_question',
                'pending_questions',
                'last_handoff',
                'updated_at',
            ]);

        if ($structured !== []) {
            $structured['collected_facts'] = $this->sanitizeFacts(
                (array) ($structured['collected_facts'] ?? [])
            );
            $structured['missing_fields'] = $this->sanitizeMissingFields(
                (array) ($structured['missing_fields'] ?? [])
            );
        }

        $replyToPendingQuestion = $continuation
            && $this->isReplyToPendingQuestion($currentBody, $structured);

        $relation = $newSupportRequest
            ? 'new_support_request'
            : ($replyToPendingQuestion
                ? 'answer_to_pending_question'
                : ($resume !== null ? 'resume_suspended_topic' : ($continuation ? 'continuation' : 'new_topic')));

        return [
            // IMPORTANT : jamais d'historique global. Au maximum l'échange immédiatement utile.
            'recent_messages' => $recentMessages,
            'quoted_message' => $quotedMessage,
            'structured' => $structured,
            'continuation' => $continuation,
            'segment_reset' => $segmentReset,
            'reply_to_pending_question' => $replyToPendingQuestion,
            'new_support_request' => $newSupportRequest,
            'relation' => $relation,
            'resumed_segment' => $resume['segment'] ?? null,
            'suspended_segments' => (array) ($stored['suspended_segments'] ?? []),
        ];
    }

    /**
     * Détecte une volonté de repartir sur une nouvelle demande, indépendamment
     * d'une simple formule de politesse au milieu d'un parcours actif.
     */
    private function isExplicitNewSupportRequest(string $message): bool
    {
        $normalized = $this->normalize($message);
        if ($normalized === '') {
            return false;
        }

        $directSignals = [
            'bonjour ovanie j ai besoin d assistance',
            'bonsoir ovanie j ai besoin d assistance',
            'j ai une nouvelle demande',
            'nouvelle demande',
            'j ai un nouveau probleme',
            'j ai un autre probleme',
            'j ai un autre souci',
            'autre question',
            'nouvelle question',
            'changeons de sujet',
            'passons a autre chose',
            'sans rapport avec ma demande precedente',
            'je souhaite recommencer',
            'on peut recommencer',
        ];

        foreach ($directSignals as $signal) {
            if (str_contains($normalized, $signal)) {
                return true;
            }
        }

        // Le lien WhatsApp OVANIE utilise une salutation suivie d'une demande
        // d'assistance. Cette combinaison ouvre un nouveau dossier, contrairement
        // à un simple « bonjour » envoyé pendant une discussion.
        $startsWithGreeting = (bool) preg_match(
            '/^(bonjour|bonsoir|salut|hello|coucou)(?:\s+ovanie)?\b/u',
            $normalized,
        );
        $asksForFreshHelp = str_contains($normalized, 'besoin d assistance')
            || str_contains($normalized, 'besoin d aide')
            || str_contains($normalized, 'nouvelle demande');

        return $startsWithGreeting && $asksForFreshHelp;
    }

    /**
     * Une réponse courte complète d'abord la question posée au tour précédent.
     * Un nom de produit dans cette réponse est une information, pas automatiquement
     * une nouvelle demande de recherche catalogue.
     */
    private function isReplyToPendingQuestion(string $message, array $structured): bool
    {
        if (trim((string) ($structured['pending_question'] ?? '')) === '') {
            return false;
        }

        $normalized = $this->normalize($message);
        if ($normalized === '') {
            return false;
        }

        $wordCount = preg_match_all('/\b[\pL\pN]+\b/u', $normalized, $matches);
        if (! is_int($wordCount) || $wordCount > 12) {
            return false;
        }

        if (str_contains($message, '?') || preg_match('/^(?:est ce|comment|pourquoi|quand|ou|quel|quelle|quels|quelles)\b/u', $normalized)) {
            return false;
        }

        $explicitRequestSignals = [
            'je cherche', 'je recherche', 'trouve moi', 'trouver', 'montre moi',
            'affiche', 'catalogue', 'combien coute', 'quel prix', 'disponible',
            'je veux acheter', 'je veux commander', 'je vais prendre', 'je prends',
            'ajoute au panier', 'ajoutez au panier', 'devis', 'proforma',
        ];

        foreach ($explicitRequestSignals as $signal) {
            if (str_contains($normalized, $signal)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{segment:array<string,mixed>,remaining:list<array<string,mixed>>}|null */
    private function findResumableSegment(string $message, array $active): ?array
    {
        $normalized = $this->normalize($message);
        $currentFamily = $this->topicFamilyFromText($normalized);
        $references = $this->extractReferences($message);

        if ($currentFamily === null && $references === []) {
            return null;
        }

        $segments = array_values(array_filter(
            (array) ($active['suspended_segments'] ?? []),
            fn ($segment) => is_array($segment) && ! empty($segment['segment_id']),
        ));

        $bestIndex = null;
        $bestScore = 0;
        foreach ($segments as $index => $segment) {
            $segmentFamily = $this->topicFamilyFromIntent(
                trim((string) ($segment['active_intent'] ?? '')).' '.trim((string) ($segment['topic_key'] ?? ''))
            );
            $score = $currentFamily !== null && $segmentFamily === $currentFamily ? 4 : 0;
            if ($currentFamily !== null && $this->sameBusinessJourney($segment, $currentFamily, $segmentFamily, $normalized)) {
                $score = max($score, 2);
            }
            if (array_intersect($references, (array) ($segment['known_references'] ?? [])) !== []) {
                $score += 10;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        if ($bestIndex === null || $bestScore < 2) {
            return null;
        }

        $segment = $segments[$bestIndex];
        array_splice($segments, $bestIndex, 1);

        // Le sujet actuellement actif devient à son tour suspendu.
        if (! empty($active['segment_id'])) {
            $current = Arr::except($active, ['suspended_segments']);
            $segments[] = $current;
        }

        return [
            'segment' => $segment,
            'remaining' => array_values(array_slice($segments, -4)),
        ];
    }

    /**
     * La mémoire est mise à jour à partir de l'analyse du MÊME appel Claude qui a répondu.
     * Aucun second appel IA n'est nécessaire pour fabriquer la mémoire.
     */
    public function updateAfterTurn(
        SupportConversation $conversation,
        string $customerMessage,
        string $assistantMessage,
        array $decision,
        string $action,
    ): void {
        $metadata = (array) ($conversation->metadata ?? []);
        $existing = (array) data_get($metadata, 'ai_memory', []);
        $suspended = (array) ($existing['suspended_segments'] ?? []);

        if (is_array($decision['_resumed_segment'] ?? null)) {
            $existing = (array) $decision['_resumed_segment'];
            $suspended = (array) ($decision['_suspended_segments'] ?? []);
        }

        // IMPORTANT : Claude ne décide plus seul de réinitialiser la mémoire.
        // Le champ new_topic reçu ici est déjà fixé par Laravel dans l'orchestrateur.
        $newTopic = (bool) ($decision['new_topic'] ?? false)
            || empty($existing['segment_id']);

        if ($newTopic) {
            if (! empty($existing['segment_id'])) {
                $suspended[] = Arr::except($existing, ['suspended_segments']);
                $suspended = array_values(array_slice($suspended, -4));
            }
            $existing = [];
        }

        $references = array_values(array_unique(array_filter(array_merge(
            (array) ($existing['known_references'] ?? []),
            $this->extractReferences($customerMessage),
        ))));

        $facts = array_merge(
            $this->sanitizeFacts((array) ($existing['collected_facts'] ?? [])),
            $this->sanitizeFacts((array) ($decision['facts'] ?? [])),
        );
        $facts = array_filter($facts, fn ($value) => $value !== null && $value !== '');

        $missingFields = $this->sanitizeMissingFields(
            (array) ($decision['missing_fields'] ?? [])
        );
        $missingFields = array_values(array_filter(
            $missingFields,
            fn (string $field) => ! array_key_exists($field, $facts),
        ));

        $asked = (array) ($existing['asked_questions'] ?? []);
        if ($action === 'clarify' && trim($assistantMessage) !== '') {
            $asked[] = mb_substr(trim($assistantMessage), 0, 600);
        }
        $asked = array_values(array_slice(array_unique($asked), -6));

        $pendingQuestions = $this->normalizePendingQuestions($existing);
        if ((bool) ($decision['_reply_to_pending_question'] ?? false) && $pendingQuestions !== []) {
            array_pop($pendingQuestions);
        }
        if ($action === 'clarify' && trim($assistantMessage) !== '') {
            $nextQuestion = $this->pendingQuestionEntry($assistantMessage, $decision);
            $alreadyHasBlockingQuestion = (bool) collect($pendingQuestions)
                ->contains(fn ($question) => (bool) ($question['blocking'] ?? false));

            // Le post-traitement peut rappeler une vérification déjà en attente.
            // Ne pas l'empiler une seconde fois sous une formulation différente.
            if (! $nextQuestion['blocking'] || ! $alreadyHasBlockingQuestion) {
                $pendingQuestions[] = $nextQuestion;
            }
            $pendingQuestions = array_values(array_slice($pendingQuestions, -5));
        }
        $pendingQuestion = $pendingQuestions !== []
            ? (string) ($pendingQuestions[array_key_last($pendingQuestions)]['body'] ?? '')
            : null;

        $segmentId = $newTopic
            ? (string) Str::uuid()
            : (string) ($existing['segment_id'] ?? Str::uuid());

        $memory = [
            'segment_id' => $segmentId,
            'segment_started_at' => $newTopic
                ? now()->toISOString()
                : ($existing['segment_started_at'] ?? now()->toISOString()),
            'active_intent' => trim((string) ($decision['intent'] ?? 'general')) ?: 'general',
            // Un parcours actif ne change jamais silencieusement au milieu d'un segment.
            'journey' => $this->stableJourney($existing, $decision, $newTopic),
            'agent_role' => trim((string) ($decision['agent_role'] ?? 'general')) ?: 'general',
            'topic_key' => trim((string) ($decision['topic_key'] ?? 'general')) ?: 'general',
            'known_references' => array_slice($references, -10),
            'collected_facts' => array_slice($facts, 0, 30, true),
            'missing_fields' => $missingFields,
            'asked_questions' => $asked,
            'pending_question' => $pendingQuestion,
            'pending_questions' => $pendingQuestions,
            'suspended_segments' => $suspended,
            'last_handoff' => is_array($decision['handoff'] ?? null) ? $decision['handoff'] : null,
            'updated_at' => now()->toISOString(),
        ];

        data_set($metadata, 'ai_memory', $memory);

        // Anciennes mémoires devenues obsolètes. Elles ne doivent plus influencer le moteur V3.
        Arr::forget($metadata, ['ai_topic', 'ai_last_plan']);

        $conversation->forceFill([
            'metadata' => $metadata,
            'summary' => $this->summary($memory),
        ])->save();
    }

    private function journeyFromDecision(array $decision): string
    {
        $family = $this->topicFamilyFromIntent(
            trim((string) ($decision['intent'] ?? '')).' '.trim((string) ($decision['topic_key'] ?? ''))
        );

        return match ($family) {
            'product', 'order', 'payment', 'logistics' => 'purchase',
            'shop' => 'seller',
            'business' => 'business',
            'dispute' => 'after_sales',
            'account' => 'account',
            'technical' => 'technical_support',
            default => 'general_support',
        };
    }

    private function stableJourney(array $existing, array $decision, bool $newTopic): string
    {
        $current = trim((string) ($existing['journey'] ?? ''));

        return ! $newTopic && $current !== ''
            ? $current
            : $this->journeyFromDecision($decision);
    }

    /** @return list<array{body:string,intent:string,blocking:bool,created_at:string}> */
    private function normalizePendingQuestions(array $memory): array
    {
        $questions = array_values(array_filter(
            (array) ($memory['pending_questions'] ?? []),
            fn ($item) => is_array($item) && trim((string) ($item['body'] ?? '')) !== '',
        ));

        if ($questions === [] && trim((string) ($memory['pending_question'] ?? '')) !== '') {
            $questions[] = $this->pendingQuestionEntry(
                (string) $memory['pending_question'],
                ['intent' => $memory['active_intent'] ?? 'general'],
            );
        }

        return array_values(array_slice($questions, -5));
    }

    /** @return array{body:string,intent:string,blocking:bool,created_at:string} */
    private function pendingQuestionEntry(string $body, array $decision): array
    {
        $body = mb_substr(trim($body), 0, 800);
        $normalized = $this->normalize($body);
        $blocking = (str_contains($normalized, 'adresse e mail') || str_contains($normalized, 'email') || str_contains($normalized, 'telephone'))
            && (str_contains($normalized, 'verif') || str_contains($normalized, 'confirmer') || str_contains($normalized, 'associe'));

        return [
            'body' => $body,
            'intent' => mb_substr(trim((string) ($decision['intent'] ?? 'general')), 0, 80),
            'blocking' => $blocking,
            'created_at' => now()->toISOString(),
        ];
    }

    /** @return array<string,mixed>|null */
    private function quotedMessage(SupportConversation $conversation, mixed $providerMessageId): ?array
    {
        $providerMessageId = trim((string) $providerMessageId);
        if ($providerMessageId === '') {
            return null;
        }

        $message = $conversation->messages()
            ->where('provider_message_id', $providerMessageId)
            ->where('is_internal', false)
            ->first(['id', 'sender_type', 'ai_agent_id', 'body', 'provider_message_id', 'created_at']);

        if (! $message) {
            return null;
        }

        return [
            'id' => (int) $message->id,
            'role' => $this->roleFor($message),
            'agent_id' => $message->ai_agent_id ? (int) $message->ai_agent_id : null,
            'body' => mb_substr(trim((string) $message->body), 0, 1400),
            'provider_message_id' => $message->provider_message_id,
            'created_at' => $message->created_at?->toISOString(),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function previousExchange(
        SupportConversation $conversation,
        ?int $currentMessageId,
        ?array $quotedMessage,
    ): array {
        if ($quotedMessage) {
            return [$quotedMessage];
        }

        $query = $conversation->messages()->where('is_internal', false);
        if ($currentMessageId) {
            $query->where('id', '<', $currentMessageId);
        }

        $limit = max(2, min(20, (int) config('support_ai.conversation.recent_messages', 12)));

        return $query
            ->latest('id')
            ->limit($limit)
            ->get(['id', 'sender_type', 'ai_agent_id', 'body', 'created_at'])
            ->reverse()
            ->map(fn (SupportConversationMessage $item) => [
                'id' => (int) $item->id,
                'role' => $this->roleFor($item),
                'agent_id' => $item->ai_agent_id ? (int) $item->ai_agent_id : null,
                'body' => mb_substr(trim((string) $item->body), 0, 1200),
                'created_at' => $item->created_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    private function isLikelyContinuation(
        SupportConversation $conversation,
        ?int $currentMessageId,
        string $currentBody,
        array $stored,
        ?array $quotedMessage,
    ): bool {
        if ($quotedMessage) {
            return true;
        }

        $normalized = $this->normalize($currentBody);
        if ($normalized === '' || empty($stored['segment_id'])) {
            return false;
        }

        // Une mémoire ancienne n'est jamais réutilisée automatiquement.
        if (! $this->isFreshSegment($stored)) {
            return false;
        }

        // Les formulations explicites de changement de sujet ouvrent un nouveau segment.
        if ($this->looksLikeExplicitNewRequest($normalized)) {
            return false;
        }

        $storedTopic = $this->normalize((string) ($stored['topic_key'] ?? ''));
        if (in_array($storedTopic, ['welcome', 'greeting'], true)) {
            // Le premier besoin métier après l'accueil démarre toujours un vrai segment.
            return $this->topicFamilyFromText($normalized) === null;
        }

        $currentFamily = $this->topicFamilyFromText($normalized);
        $storedFamily = $this->topicFamilyFromIntent(
            trim((string) ($stored['active_intent'] ?? '')).' '.trim((string) ($stored['topic_key'] ?? ''))
        );

        // Certains domaines sont des étapes d'un même parcours métier.
        // Exemple vendeur : ouverture boutique -> ajout produit -> paiement vendeur.
        if ($currentFamily !== null && $storedFamily !== null && $currentFamily !== $storedFamily) {
            if ($this->sameBusinessJourney($stored, $currentFamily, $storedFamily, $normalized)) {
                return true;
            }

            return false;
        }

        if ($currentFamily !== null && $storedFamily === null) {
            return $this->sameBusinessJourney($stored, $currentFamily, null, $normalized);
        }

        // Dans une session récente, un message reste dans le segment actif par défaut.
        // On transmet uniquement les faits structurés + au maximum l'échange précédent,
        // jamais l'historique global.
        return true;
    }

    private function isFreshSegment(array $stored): bool
    {
        $updatedAt = trim((string) ($stored['updated_at'] ?? ''));
        if ($updatedAt === '') {
            return false;
        }

        try {
            $ttl = max(5, (int) config('support_ai.conversation.memory_ttl_minutes', 1440));

            return now()->diffInMinutes(\Illuminate\Support\Carbon::parse($updatedAt)) <= $ttl;
        } catch (\Throwable) {
            return false;
        }
    }

    public function hasFreshBusinessSegment(array $stored): bool
    {
        if (! $this->isFreshSegment($stored)) {
            return false;
        }

        $topic = $this->normalize((string) ($stored['topic_key'] ?? ''));
        $intent = $this->normalize((string) ($stored['active_intent'] ?? ''));

        if ($topic === '' && $intent === '') {
            return false;
        }

        return ! in_array($topic, ['welcome', 'greeting', 'general'], true)
            || ! in_array($intent, ['welcome', 'greeting', 'general'], true);
    }

    private function sameBusinessJourney(
        array $stored,
        ?string $currentFamily,
        ?string $storedFamily,
        string $currentText = '',
    ): bool {
        $haystack = $this->normalize(
            (string) ($stored['topic_key'] ?? '').' '.
            (string) ($stored['active_intent'] ?? '')
        );

        $vendorJourney = str_contains($haystack, 'vendor')
            || str_contains($haystack, 'vendeur')
            || str_contains($haystack, 'boutique')
            || str_contains($haystack, 'shop')
            || str_contains($haystack, 'publication')
            || str_contains($haystack, 'ajout produit');

        if ($vendorJourney) {
            if (in_array($currentFamily, ['account', 'shop', 'logistics', 'technical'], true)) {
                return true;
            }

            if ($currentFamily === 'product') {
                return str_contains($currentText, 'ajouter')
                    || str_contains($currentText, 'publier')
                    || str_contains($currentText, 'publication')
                    || str_contains($currentText, 'catalogue vendeur')
                    || str_contains($currentText, 'mes produits');
            }

            if ($currentFamily === 'payment') {
                $vendorPayment = str_contains($currentText, 'mode de paiement')
                    || str_contains($currentText, 'moyen de paiement')
                    || str_contains($currentText, 'paiement vendeur')
                    || str_contains($currentText, 'reversement')
                    || str_contains($currentText, 'recevoir mes gains')
                    || (str_contains($currentText, 'configur') && str_contains($currentText, 'paiement'));

                $transactionIssue = str_contains($currentText, 'debite')
                    || str_contains($currentText, 'transaction')
                    || str_contains($currentText, 'wave')
                    || str_contains($currentText, 'orange money')
                    || str_contains($currentText, 'mtn')
                    || str_contains($currentText, 'moov')
                    || str_contains($currentText, 'commande');

                return $vendorPayment && ! $transactionIssue;
            }
        }

        $purchaseJourney = str_contains($haystack, 'achat')
            || str_contains($haystack, 'checkout')
            || str_contains($haystack, 'commande')
            || str_contains($haystack, 'product_search')
            || str_contains($haystack, 'recherche produit');

        if ($purchaseJourney) {
            $allowed = ['product', 'order', 'payment', 'logistics'];
            if ($currentFamily !== null && in_array($currentFamily, $allowed, true)) {
                return true;
            }
        }

        // Catalogue, panier, commande, paiement et livraison constituent les étapes
        // d'un seul parcours acheteur, même si le libellé d'intention précédent varie.
        $purchaseFamilies = ['product', 'order', 'payment', 'logistics'];
        if ($storedFamily !== null
            && $currentFamily !== null
            && in_array($storedFamily, $purchaseFamilies, true)
            && in_array($currentFamily, $purchaseFamilies, true)) {
            return true;
        }

        return $storedFamily !== null && $currentFamily === $storedFamily;
    }

    private function looksLikeExplicitNewRequest(string $normalized): bool
    {
        return str_contains($normalized, 'oubliez ')
            || str_contains($normalized, 'laissez tomber ')
            || str_contains($normalized, 'autre question')
            || str_contains($normalized, 'nouvelle question')
            || str_contains($normalized, 'changeons de sujet')
            || str_contains($normalized, 'sans rapport avec')
            || str_contains($normalized, 'passons a autre chose')
            || str_contains($normalized, 'je suis nouveau sur')
            || str_contains($normalized, 'je suis nouvelle sur')
            || str_contains($normalized, 'nouveau sur la plateforme')
            || str_contains($normalized, 'nouvelle sur la plateforme');
    }

    /**
     * Détecte uniquement une salutation sociale afin de COUPER l'ancien contexte.
     * Ce n'est pas un moteur de réponse préparée.
     */
    public function isSocialGreeting(string $message): bool
    {
        $clean = $this->normalize($message);
        if ($clean === '') {
            return false;
        }

        $businessWords = [
            'commande', 'paiement', 'livraison', 'litige', 'devis', 'remboursement',
            'produit', 'catalogue', 'prix', 'boutique', 'vendeur', 'compte', 'inscription',
            'erreur', 'probleme', 'reclamation', 'retour', 'acheter', 'achat',
        ];

        foreach ($businessWords as $word) {
            if (str_contains($clean, $word)) {
                return false;
            }
        }

        if (! preg_match('/^(bonjour|bonsoir|salut|coucou|hello|hey)(?:\s+ovanie)?\b/u', $clean)) {
            return false;
        }

        return mb_strlen($clean) <= 160;
    }

    private function topicFamilyFromText(string $normalized): ?string
    {
        $groups = [
            'dispute' => ['litige', 'reclamation', 'plainte', 'contestation', 'fraude', 'responsable', 'superviseur'],
            'payment' => ['paiement', 'paye', 'payer', 'debite', 'transaction', 'wave', 'orange money', 'mtn momo', 'moov money', 'remboursement'],
            'logistics' => ['livraison', 'livrer', 'livreur', 'colis', 'tracking', 'expedition', 'reception', 'adresse de livraison'],
            'business' => ['devis', 'proforma', 'appel d offres', 'appel offre', 'prix de gros', 'achat en gros'],
            'technical' => ['bug', 'erreur', 'ne fonctionne', 'bloque', 'impossible de me connecter', 'connexion impossible'],
            'account' => ['creer un compte', 'creation de compte', 'inscription', 'mot de passe', 'mon compte'],
            'shop' => ['ouvrir une boutique', 'ouverture boutique', 'compte vendeur', 'ma boutique', 'publication produit'],
            // La commande prime sur le nom du produit : « ma commande de gravier »
            // est un dossier de commande, jamais une recherche catalogue.
            'order' => ['commande', 'facture', 'numero de commande', 'reference commande'],
            'product' => ['catalogue', 'produit', 'ciment', 'brique', 'carreau', 'peinture', 'gravier', 'sable', 'tole', 'tuyau', 'robinet', 'marbre', 'panneau solaire'],
        ];

        foreach ($groups as $family => $terms) {
            foreach ($terms as $term) {
                if (str_contains($normalized, $term)) {
                    return $family;
                }
            }
        }

        return null;
    }

    private function topicFamilyFromIntent(string $intent): ?string
    {
        $intent = $this->normalize($intent);
        if ($intent === '' || in_array($intent, ['general', 'greeting'], true)) {
            return null;
        }

        $aliases = [
            'dispute' => ['litige', 'reclamation', 'plainte', 'contestation', 'fraude', 'escalation'],
            'payment' => ['payment', 'paiement', 'transaction', 'remboursement'],
            'logistics' => ['livraison', 'shipment', 'logistic', 'tracking', 'expedition'],
            'business' => ['business', 'devis', 'proforma', 'appel offre', 'gros'],
            'technical' => ['technical', 'technique', 'bug', 'erreur'],
            'account' => ['account', 'compte', 'inscription', 'onboarding'],
            'shop' => ['shop', 'boutique', 'vendeur'],
            'product' => ['product', 'produit', 'catalogue', 'catalog', 'ciment'],
            'order' => ['order', 'commande', 'achat'],
        ];

        foreach ($aliases as $family => $terms) {
            foreach ($terms as $term) {
                if (str_contains($intent, $term)) {
                    return $family;
                }
            }
        }

        return null;
    }

    private function roleFor(SupportConversationMessage $message): string
    {
        return match ((string) $message->sender_type) {
            'customer' => 'customer',
            'human' => 'support_human',
            default => 'assistant',
        };
    }

    /** @return list<string> */
    private function extractReferences(string $text): array
    {
        preg_match_all('/\b(?:CMD|ORD|OV|COM|PAY|TXN|TRX|TRK|SHIP|EXP|SUP)-[A-Z0-9-]{4,}\b/i', $text, $matches);

        return collect($matches[0] ?? [])
            ->map(fn ($ref) => Str::upper(trim((string) $ref)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<string,string|int|float|bool> */
    private function sanitizeFacts(array $facts): array
    {
        $safe = [];

        // Ces valeurs proviennent du backend, pas de la parole du client.
        // Elles ne doivent jamais être mémorisées comme « faits déclarés » par Claude.
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

    private function summary(array $memory): string
    {
        $parts = [
            'Segment : '.($memory['segment_id'] ?? '-'),
            'Sujet actif : '.($memory['active_intent'] ?? 'general'),
            'Agente : '.($memory['agent_role'] ?? 'general'),
        ];

        if (! empty($memory['collected_facts'])) {
            $facts = collect((array) $memory['collected_facts'])
                ->map(fn ($value, $key) => $key.'='.$value)
                ->implode(', ');
            $parts[] = 'Informations déjà données : '.$facts;
        }

        if (! empty($memory['missing_fields'])) {
            $parts[] = 'Informations encore nécessaires : '.implode(', ', (array) $memory['missing_fields']);
        }

        if (! empty($memory['known_references'])) {
            $parts[] = 'Références connues : '.implode(', ', (array) $memory['known_references']);
        }

        return implode("\n", $parts);
    }

    private function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii(trim($text)));
        $text = preg_replace('/[^a-z0-9 ]+/', ' ', $text) ?? $text;
        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }
}
