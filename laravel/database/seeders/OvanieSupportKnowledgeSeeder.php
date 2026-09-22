<?php

namespace Database\Seeders;

use App\Models\SupportKnowledgeArticle;
use App\Models\User;
use Illuminate\Database\Seeder;

class OvanieSupportKnowledgeSeeder extends Seeder
{
    public function run(): void
    {
        $approverId = User::query()
            ->where(function ($query) {
                $query->where('is_admin', true)
                    ->orWhereIn('role', ['admin', 'super_admin']);
            })
            ->value('id') ?? User::query()->value('id');

        if (! $approverId) {
            $this->command?->warn('Aucun utilisateur disponible : les articles de connaissance OVANIE n’ont pas été créés.');
            return;
        }

        $articles = [
            [
                'slug' => 'compte-client-connexion',
                'title' => 'Compte client OVANIE : inscription et connexion',
                'category' => 'Compte client',
                'content' => <<<'TEXT'
Après une inscription client réussie, le client doit être connecté et redirigé vers son espace client. La vérification préalable de l’adresse e-mail ne doit pas bloquer l’accès à l’espace client. Un client qui possède déjà un compte et s’authentifie correctement est redirigé vers son espace client.
TEXT,
            ],
            [
                'slug' => 'parcours-commande-client',
                'title' => 'Parcours réel pour passer une commande sur OVANIE',
                'category' => 'Commandes',
                'content' => <<<'TEXT'
Le client recherche ou consulte les produits puis ajoute les produits souhaités au panier. Lorsqu’il poursuit vers le checkout, il choisit ou renseigne son adresse de livraison. OVANIE recalcule alors les frais de livraison en arrière-plan. Le checkout reste simple et présente le total produits, le total livraison et le total à payer. Le client choisit ensuite son mode de paiement et confirme la commande. Après validation, les informations de commande sont accessibles depuis l’espace client. Les détails des groupes de livraison apparaissent après validation dans les informations de commande ou la facture, pas comme une ventilation complexe avant validation.
TEXT,
            ],
            [
                'slug' => 'checkout-paiement',
                'title' => 'Checkout, adresse, livraison et paiement OVANIE',
                'category' => 'Paiement et livraison',
                'content' => <<<'TEXT'
L’adresse de livraison est demandée naturellement au checkout, et peut être différente du domicile du client. Le système peut utiliser la position GPS et les informations commune, quartier, sous-quartier ou repère pour le calcul logistique. Après le choix de l’adresse, les frais de livraison sont recalculés. Le checkout présente principalement total produits, total livraison et total à payer. Les modes de paiement disponibles dépendent des moyens configurés dans la plateforme, notamment les paiements en ligne et, lorsque la commande est éligible, le paiement à la livraison.
TEXT,
            ],
            [
                'slug' => 'ouverture-boutique',
                'title' => 'Ouverture d’une boutique vendeur OVANIE',
                'category' => 'Vendeurs et boutiques',
                'version' => 3,
                'content' => <<<'TEXT'
Le parcours officiel d’ouverture d’une boutique OVANIE est organisé en 5 blocs distincts, dans cet ordre. Il ne faut pas fusionner ni supprimer ces blocs lorsqu’on explique la procédure au vendeur.

Règle de canal obligatoire : WhatsApp sert uniquement à accompagner le vendeur, répondre à ses questions et expliquer les étapes. La création effective du compte, la saisie et l’envoi du formulaire de boutique, l’ajout des justificatifs et toute validation doivent être effectués sur le site ou l’application OVANIE. Le support ne collecte pas dans le chat les informations destinées à créer réellement le compte ou la boutique et ne doit jamais promettre de le faire.

1. Informations personnelles : nom complet, e-mail, téléphone, type de vendeur et, lorsqu’elles sont applicables, informations légales de l’entreprise. Le vendeur doit être rattaché à un compte OVANIE ; si aucun compte n’existe, les informations nécessaires à la création du compte sont collectées dans le parcours prévu.

2. Informations boutique : nom de la boutique, description, éléments de présentation demandés par le formulaire, région, ville, commune, quartier, point de repère ou adresse complète, position GPS lorsqu’elle est disponible, catégorie principale, informations professionnelles de contact et option logistique proposée par le formulaire. La localisation doit être suffisamment précise pour permettre les opérations logistiques.

3. Vérification d’identité : pays de délivrance, type de pièce accepté par le formulaire, numéro de pièce et ajout du justificatif demandé (scan, recto/verso ou PDF selon le cas).

4. Paiement vendeur : choix du mode ou du calendrier de reversement disponible, opérateur ou moyen de paiement vendeur configuré, numéro de paiement et titulaire lorsque ces champs sont demandés.

5. Conditions vendeur : lecture et acceptation des conditions vendeur OVANIE.

Une boutique ne doit pas être présentée comme totalement opérationnelle pour la publication si des informations obligatoires, la vérification d’identité ou les exigences logistiques nécessaires restent incomplètes.

Pour une réponse WhatsApp à un nouveau vendeur, annoncer d’abord la règle de canal, résumer les 5 blocs, puis poser une seule question de prochaine étape. Laravel doit vérifier l’existence du compte ou de la boutique avant toute affirmation ; ne jamais annoncer qu’ils sont créés à partir des seules paroles du client.
TEXT,
            ],
            [
                'slug' => 'ajout-publication-produit',
                'title' => 'Ajout et publication d’un produit vendeur',
                'category' => 'Produits vendeur',
                'content' => <<<'TEXT'
Le formulaire d’ajout produit est structuré autour des informations du produit, du prix et de l’unité, des détails techniques, de la logistique et des médias. Les données logistiques utiles au calcul de livraison, comme le poids et les dimensions lorsque demandées par le formulaire, doivent être renseignées. Les images produit doivent respecter le standard marketplace et les champs obligatoires doivent être complétés. Lorsqu’un produit est refusé ou ne peut pas être publié, le support technique doit rechercher le message d’erreur exact et l’étape de blocage avant d’affirmer la cause.
TEXT,
            ],
            [
                'slug' => 'support-escalade-agents',
                'title' => 'Rôles des agentes IA et escalade humaine',
                'category' => 'Support',
                'content' => <<<'TEXT'
N’Nan traite l’accueil et les demandes générales. Miss Rita traite OVANIE Pro, les devis, proformas, appels d’offres et achats professionnels importants explicitement demandés. L’Assistante Technique traite les problèmes techniques de la plateforme. L’Assistante Logistique traite les demandes de livraison et incidents logistiques. Miss Salomé traite les litiges, réclamations complexes, contestations et demandes de responsable. Le numéro d’appel humain ne doit être communiqué qu’en dernier recours, lorsque Miss Salomé ne peut réellement plus résoudre ou faire avancer la demande et qu’une prise en charge humaine est nécessaire.
TEXT,
            ],
        ];

        foreach ($articles as $article) {
            SupportKnowledgeArticle::query()->updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'title' => $article['title'],
                    'category' => $article['category'],
                    'content' => $article['content'],
                    'status' => 'published',
                    'source_type' => 'internal_policy',
                    'source_reference' => 'OVANIE Support Architecture',
                    'created_by' => $approverId,
                    'updated_by' => $approverId,
                    'approved_by' => $approverId,
                    'published_at' => now(),
                    'reviewed_at' => now(),
                    'expires_at' => null,
                    'version' => (int) ($article['version'] ?? 1),
                ]
            );
        }
    }
}
