<?php

namespace App\Services\SupportAi\Providers;

use App\Models\SupportAiAgent;
use App\Models\SupportConversation;
use App\Services\SupportAi\Contracts\SupportAiProvider;
use Illuminate\Support\Str;

class LocalSupportAiProvider implements SupportAiProvider
{
    public function generate(
        SupportConversation $conversation,
        SupportAiAgent $agent,
        string $message,
        array $context = []
    ): array {
        $normalized = Str::lower(Str::ascii($message));

        $body = match ($agent->role_key) {
            'business' => $this->businessResponse($normalized, $conversation, $context),
            'logistics' => $this->logisticsResponse($conversation, $context),
            'technical' => $this->technicalResponse($conversation, $context),
            'escalation' => $this->escalationResponse($conversation, $context),
            default => $this->generalResponse($normalized, $conversation, $context),
        };

        return [
            'body' => $body,
            'confidence' => $agent->role_key === 'escalation' ? 96.0 : 86.0,
            'provider' => 'local',
            'metadata' => [
                'role_key' => $agent->role_key,
                'answered' => ! Str::contains($body, [
                    'Je veux vous répondre précisément',
                    'Pour vous répondre précisément',
                ]),
            ],
        ];
    }

    private function generalResponse(string $message, SupportConversation $conversation, array $context): string
    {
        $name = $conversation->requester_name ?: $conversation->requester?->name;
        $hello = $name ? "Bonjour {$name}, je suis N’Nan." : 'Bonjour, je suis N’Nan.';

        if (! empty($context['knowledge'])) {
            $article = $context['knowledge'][0];
            return "{$hello} {$article['content']}";
        }

        if (Str::contains($message, ['bonjour', 'bonsoir', 'salut', 'hello']) && mb_strlen($message) < 40) {
            return "{$hello} Que souhaitez-vous savoir sur OVANIE ?";
        }

        if (Str::contains($message, ['comment commander', 'passer une commande', 'acheter un produit'])) {
            return "{$hello} Recherchez le produit souhaité, ouvrez sa fiche, ajoutez-le au panier puis validez votre commande. Vous pourrez ensuite choisir votre adresse, le mode de livraison et le moyen de paiement.";
        }

        if (Str::contains($message, ['creer un compte', 'ouvrir un compte', 'inscription', "s'inscrire"])) {
            return "{$hello} Cliquez sur « Comptes », puis sur « Commencer ici ». Renseignez vos informations et validez votre inscription pour accéder à votre espace client.";
        }

        if (Str::contains($message, ['moyen de paiement', 'moyens de paiement', 'comment payer', 'mode de paiement'])) {
            return "{$hello} Les moyens de paiement disponibles sont affichés au moment de valider votre commande. Leur disponibilité peut varier selon le vendeur, le montant et le lieu de livraison.";
        }

        if (Str::contains($message, ['devenir vendeur', 'ouvrir une boutique', 'vendre sur ovani', 'vendre mes produit'])) {
            return "{$hello} Cliquez sur « Vendez sur OVANIE », créez votre compte vendeur, renseignez les informations de votre activité puis transmettez les documents demandés pour vérification.";
        }

        if (Str::contains($message, ['livraison', 'livreur', 'colis', 'suivi', 'expedition', 'retard'])) {
            if ($context['shipment']) {
                $tracking = $context['shipment']['tracking_number'] ?: 'non encore attribué';
                return "{$hello} La livraison liée à votre dossier est actuellement au statut « {$context['shipment']['status']} ». Le numéro de suivi est {$tracking}. Si ce statut ne correspond pas à votre situation, décrivez-moi ce que vous constatez.";
            }

            return "{$hello} Pour vérifier une livraison réelle, indiquez la référence de commande ou de suivi. Pour protéger vos données, aucun détail sensible ne sera communiqué sans rattachement du dossier à votre compte.";
        }

        if (Str::contains($message, ['commande', 'achat', 'facture'])) {
            if ($context['order']) {
                return "{$hello} La commande {$context['order']['number']} est au statut « {$context['order']['status']} » et son paiement est « {$context['order']['payment_status']} ». Dites-moi précisément ce qui vous bloque afin que je poursuive le traitement.";
            }

            return "{$hello} Indiquez la référence de la commande et décrivez précisément le problème rencontré afin que je puisse vérifier votre dossier.";
        }

        if (Str::contains($message, ['paiement', 'paye', 'debite', 'transaction'])) {
            if ($context['payment']) {
                return "{$hello} Le paiement associé est au statut « {$context['payment']['status']} ». Pour toute modification financière, remboursement ou annulation, une validation humaine est obligatoire.";
            }

            return "{$hello} Indiquez la référence de la commande ou de la transaction ainsi que le problème rencontré afin que je puisse vérifier son statut.";
        }

        if (Str::contains($message, ['retour', 'remplacer', 'echange'])) {
            if ($context['return']) {
                return "{$hello} Votre demande de retour est au statut « {$context['return']['status']} ». Dites-moi si vous souhaitez ajouter une précision à votre demande.";
            }

            return "{$hello} Pour demander un retour, indiquez la référence de la commande, le produit concerné et la raison du retour. Des photos pourront vous être demandées selon la situation.";
        }

        return "{$hello} Je veux vous répondre précisément. Pouvez-vous reformuler votre question en indiquant le service concerné et, si nécessaire, la référence de votre commande ?";
    }

    private function businessResponse(string $message, SupportConversation $conversation, array $context): string
    {
        $name = $conversation->requester_name ?: $conversation->requester?->name;
        $hello = $name ? "Bonjour {$name}, je suis Miss Rita." : 'Bonjour, je suis Miss Rita.';

        if (! empty($context['knowledge'])) {
            return "{$hello} {$context['knowledge'][0]['content']}";
        }

        if (Str::contains($message, ['comment', 'obtenir', 'demander', 'faire']) && Str::contains($message, ['devis', 'proforma'])) {
            return "{$hello} Pour préparer votre devis, indiquez les matériaux ou produits recherchés, les quantités, le lieu de livraison et le délai souhaité. Ajoutez le nom de votre entreprise et un numéro de contact si la demande est professionnelle.";
        }

        return "{$hello} Pour vous répondre précisément, indiquez votre besoin, les produits ou matériaux recherchés, les quantités, le lieu de livraison et le délai souhaité.";
    }

    private function escalationResponse(SupportConversation $conversation, array $context): string
    {
        $name = $conversation->requester_name ?: $conversation->requester?->name;
        $hello = $name ? "Bonjour {$name}, je suis Miss Salomé." : 'Bonjour, je suis Miss Salomé.';
        $phone = $context['support_phone'] ?? config('support_ai.support_phone');
        $rate = $context['local_rate_notice'] ?? config('support_ai.local_rate_notice');

        return "{$hello} J’ai bien pris en compte votre réclamation. Décrivez les faits, indiquez la référence concernée et laissez un numéro où vous joindre. Vous pouvez également appeler le {$phone}, {$rate}.";
    }

    private function logisticsResponse(SupportConversation $conversation, array $context): string
    {
        $name = $conversation->requester_name ?: $conversation->requester?->name;
        $hello = $name ? "Bonjour {$name}, je suis votre Assistante Logistique OVANIE." : 'Bonjour, je suis votre Assistante Logistique OVANIE.';

        if ($context['shipment']) {
            $tracking = $context['shipment']['tracking_number'] ?: 'non encore attribué';
            return "{$hello} Votre livraison est au statut « {$context['shipment']['status']} » et son numéro de suivi est {$tracking}. Que souhaitez-vous vérifier ?";
        }

        return "{$hello} Indiquez votre référence de commande ou de suivi et précisez le problème de livraison rencontré.";
    }

    private function technicalResponse(SupportConversation $conversation, array $context): string
    {
        $name = $conversation->requester_name ?: $conversation->requester?->name;
        $hello = $name ? "Bonjour {$name}, je suis votre Assistante Technique OVANIE." : 'Bonjour, je suis votre Assistante Technique OVANIE.';

        if (! empty($context['knowledge'])) {
            return "{$hello} {$context['knowledge'][0]['content']}";
        }

        return "{$hello} Indiquez le nom et la référence du produit, son modèle ainsi que le problème exact constaté. Évitez toute manipulation présentant un risque électrique ou mécanique.";
    }
}
