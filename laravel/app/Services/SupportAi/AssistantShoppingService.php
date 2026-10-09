<?php

namespace App\Services\SupportAi;

use App\Models\Product;
use App\Models\SupportConversation;
use App\Services\PublicProductVisibilityService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Achats depuis la conversation avec l'assistante.
 *
 * - Quand le client cherche un produit, l'assistante renvoie des fiches du
 *   catalogue (photo, prix, bouton « Ajouter au panier »).
 * - Quand il demande d'ajouter un produit au panier, elle identifie le produit
 *   (parmi ceux qu'elle vient de montrer, ou par une nouvelle recherche) et
 *   prépare l'ajout. L'ajout lui-même passe par le panier habituel du site :
 *   les contrôles de stock, de quantité minimale et de modèle restent les mêmes.
 */
class AssistantShoppingService
{
    private const STOP_WORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'd', 'l', 'au', 'aux', 'et', 'ou', 'en', 'a', 'à', 'pour', 'sur', 'dans', 'avec', 'sans',
        'je', 'j', 'tu', 'il', 'on', 'nous', 'vous', 'me', 'moi', 'mon', 'ma', 'mes', 'ce', 'cet', 'cette', 'ces', 'ca', 'ça', 'que', 'qui', 'quoi',
        'est', 'sont', 'ai', 'as', 'avez', 'avoir', 'veux', 'voudrais', 'veut', 'souhaite', 'cherche', 'recherche', 'chercher', 'trouve', 'trouver',
        'montre', 'montrer', 'besoin', 'acheter', 'achat', 'prix', 'combien', 'coute', 'coûte', 'produit', 'produits', 'article', 'articles',
        'catalogue', 'ovanie', 'bonjour', 'bonsoir', 'salut', 'merci', 'svp', 'plait', 'plaît', 's', 'il', 'y', 'ajoute', 'ajouter', 'ajoutez',
        'rajoute', 'mets', 'mettre', 'mettez', 'panier', 'commande', 'commander', 'prends', 'prendre', 'aussi', 'stp', 'peux', 'pouvez', 'peut',
        'disponible', 'disponibles', 'avez-vous', 'quel', 'quelle', 'quels', 'quelles', 'tous', 'toutes', 'tout', 'premier', 'premiere', 'première',
        'deuxieme', 'deuxième', 'second', 'troisieme', 'troisième', 'quatrieme', 'quatrième', 'dernier', 'derniere', 'dernière', 'celui', 'celle',
        'unite', 'unité', 'unites', 'unités', 'fois', 'pas', 'plus', 'moins', 'cher', 'chers',
        'plateforme', 'site', 'application', 'appli', 'boutique', 'boutiques', 'magasin', 'vendeur', 'vendeurs', 'marketplace', 'ligne',
        'vous', 'votre', 'vos', 'notre', 'nos', 'leur', 'leurs', 'ils', 'elle', 'elles', 'sur', 'chez', 'par', 'pas', 'bien', 'tres', 'très',
        'voir', 'vois', 'liste', 'lister', 'afficher', 'affiche', 'donne', 'donner', 'donnez', 'dire', 'dis', 'dites', 'savoir', 'existe',
        'existent', 'type', 'types', 'sorte', 'sortes', 'genre', 'modele', 'modèle', 'modeles', 'modèles', 'quelque', 'quelques', 'chose',
        'different', 'différent', 'differents', 'différents', 'autre', 'autres', 'meilleur', 'meilleurs', 'bon', 'bons', 'bonne', 'bonnes',
        'possible', 'vente', 'vendre', 'vendez', 'stock', 'actuellement', 'maintenant', 'aujourd', 'hui', 'comme', 'entre', 'tel', 'telle',
    ];

    public function __construct(private readonly PublicProductVisibilityService $visibility)
    {
    }

    /**
     * Recherche d'un produit du catalogue. Renvoie la réponse de l'assistante et
     * les fiches à afficher, ou null si le message porte sur autre chose
     * (commande, paiement, réclamation…), qui reste traité par le support.
     *
     * @return array{reply: string, products: list<array<string, mixed>>}|null
     */
    public function catalogRequest(string $message, SupportConversation $conversation): ?array
    {
        $text = $this->normalize($message);
        $expecting = (bool) data_get($conversation->metadata, 'assistant_expects_product', false);
        $wordCount = count(array_filter(explode(' ', $text)));

        // Une question de suivi, de paiement ou une réclamation n'est pas une recherche de produit.
        $otherTopic = (bool) preg_match('/\b(commande|commandes|livraison|livreur|paiement|payer|rembours\w*|retour|retourner|probleme|souci|reclamation|litige|compte|mot de passe|conseiller|humain|annul\w*|facture|suivi|suivre|recu|casse|defectueux|abime|erreur|bug)\b/u', $text);
        $smallTalk = (bool) preg_match('/^(bonjour|bonsoir|salut|merci|merci beaucoup|oui|non|ok|d accord|au revoir|rien|non merci)\b/u', $text);

        $isSearch = ! $otherTopic && (
            $this->looksLikeProductRequest($message)
            // Le client répond à « Quel produit recherchez-vous ? » par un simple nom : « Perceuse ».
            || ($expecting && ! $smallTalk && $wordCount <= 6)
        );

        if (! $isSearch) {
            if ($expecting) {
                $this->expectProduct($conversation, false);
            }

            return null;
        }

        $terms = $this->terms($message);
        if ($terms === []) {
            $this->expectProduct($conversation, true);

            return [
                'reply' => 'Avec plaisir. Quel produit recherchez-vous ? Indiquez-moi son nom, sa marque ou sa catégorie, par exemple « ciment », « perceuse » ou « peinture blanche ».',
                'products' => [],
            ];
        }

        $label = implode(' ', $terms);
        $products = $this->search($message, 4);
        if ($products->isEmpty()) {
            $this->expectProduct($conversation, true);

            return [
                'reply' => 'Je n’ai trouvé aucun produit correspondant à « '.$label.' » dans le catalogue. Essayez avec un autre nom, une marque ou une catégorie.',
                'products' => [],
            ];
        }

        $this->remember($conversation, $products);
        $this->expectProduct($conversation, false);
        $intro = $products->count() === 1
            ? 'Voici le produit que j’ai trouvé pour « '.$label.' ».'
            : 'Voici les '.$products->count().' produits les plus proches de « '.$label.' ».';

        return [
            'reply' => $intro.' Touchez « Ajouter au panier » sur celui de votre choix, ou écrivez-moi par exemple « ajoute le premier au panier ».',
            'products' => $products->map(fn (Product $product) => $this->card($product))->values()->all(),
        ];
    }

    private function expectProduct(SupportConversation $conversation, bool $expecting): void
    {
        $metadata = (array) ($conversation->metadata ?? []);
        if ((bool) ($metadata['assistant_expects_product'] ?? false) === $expecting) {
            return;
        }
        $metadata['assistant_expects_product'] = $expecting;
        $conversation->forceFill(['metadata' => $metadata])->save();
    }

    /**
     * Demande d'ajout au panier : renvoie le produit et la quantité à ajouter,
     * une demande de précision, ou null si le message ne parle pas du panier.
     *
     * @return array{status: string, reply: string, product?: array<string, mixed>, quantity?: int, products?: list<array<string, mixed>>}|null
     */
    public function cartRequest(string $message, SupportConversation $conversation): ?array
    {
        $text = $this->normalize($message);
        if (! preg_match('/\b(ajoute[rz]?|rajoute[rz]?|mets|mettre|mettez|prends|prendre)\b.*\bpanier\b|\bau panier\b|\bdans (mon|le) panier\b/u', $text)) {
            return null;
        }

        $shown = $this->remembered($conversation);
        $product = $this->pickFromShown($text, $shown) ?? $this->pickBySearch($message, $shown);

        if (! $product) {
            // Le client a nommé un produit que le catalogue ne contient pas.
            $named = $this->terms($message) !== [] && $this->search($message, 1)->isEmpty();
            if ($shown->count() > 1 && ! $named) {
                return [
                    'status' => 'clarify',
                    'reply' => 'Quel produit souhaitez-vous ajouter au panier ? Touchez « Ajouter au panier » sur la fiche de votre choix, ou dites-moi « le premier », « le deuxième »…',
                    'products' => $shown->map(fn (Product $item) => $this->card($item))->values()->all(),
                ];
            }

            return [
                'status' => 'clarify',
                'reply' => 'Je n’ai pas trouvé ce produit dans le catalogue. Donnez-moi son nom ou sa marque et je le cherche pour vous.',
            ];
        }

        $card = $this->card($product);
        if ($card['needs_variant']) {
            return [
                'status' => 'clarify',
                'reply' => 'Ce produit existe en plusieurs modèles : ouvrez sa fiche pour choisir le modèle ou la couleur avant de l’ajouter au panier.',
                'products' => [$card],
            ];
        }

        $quantity = max($this->quantity($text), (int) $card['min_quantity']);

        return [
            'status' => 'add',
            'reply' => 'Très bien, j’ajoute '.$quantity.' × « '.$product->name.' » à votre panier.',
            'product' => $card,
            'quantity' => $quantity,
        ];
    }

    private function looksLikeProductRequest(string $message): bool
    {
        $text = $this->normalize($message);

        return (bool) preg_match(
            '/\b(cherche|recherche|trouve|trouver|besoin|acheter|achat|prix|combien|coute|produit|produits|article|catalogue|disponible|avez vous|vendez|montre|montrer|propose|proposez|ciment|fer|sable|gravier|brique|parpaing|peinture|carrelage|carreau|tole|tuyau|cable|outil|perceuse|marteau|casque|gant|epi|bois|contreplaque|porte|fenetre|robinet|lavabo|wc|ampoule|interrupteur|vis|clou|colle|enduit|platre|beton|acier|treillis|echafaudage|brouette|pelle|niveau|disque|meuleuse)s?\b/u',
            $text
        );
    }

    /** @return Collection<int, Product> */
    private function search(string $message, int $limit): Collection
    {
        $terms = $this->terms($message);
        if ($terms === []) {
            return collect();
        }

        $candidates = $this->visibility->query(['category', 'images', 'variants'], false)
            ->where(function (Builder $builder) use ($terms): void {
                foreach ($terms as $term) {
                    $like = '%'.$term.'%';
                    $builder->orWhere('name', 'like', $like)
                        ->orWhere('brand', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like));
                }
            })
            ->limit(120)
            ->get();

        $ranked = $candidates
            ->map(function (Product $product) use ($terms): array {
                $name = $this->normalize((string) $product->name);
                $extra = $this->normalize(trim(($product->brand ?? '').' '.($product->category?->name ?? '')));
                $nameHits = 0;
                $extraHits = 0;
                foreach ($terms as $term) {
                    $needle = $this->normalize($term);
                    if ($this->containsWord($name, $needle)) {
                        $nameHits++;
                    } elseif ($this->containsWord($extra, $needle)) {
                        $extraHits++;
                    }
                }

                return [
                    'product' => $product,
                    'name_hits' => $nameHits,
                    'extra_hits' => $extraHits,
                    'in_stock' => (int) ($product->stock ?? 0) > 0,
                ];
            })
            // Un mot présent seulement dans la description ne suffit pas : « gravier » dont la
            // description parle de ciment n'est pas du ciment.
            ->filter(fn (array $item) => $item['name_hits'] + $item['extra_hits'] > 0);

        // Si des produits portent le mot dans leur nom, on ne montre que ceux-là.
        if ($ranked->contains(fn (array $item) => $item['name_hits'] > 0)) {
            $ranked = $ranked->filter(fn (array $item) => $item['name_hits'] > 0);
        }

        // Plusieurs mots (« peinture blanche ») : on garde les produits qui en réunissent le plus.
        $best = (int) $ranked->max(fn (array $item) => $item['name_hits'] + $item['extra_hits']);

        return $ranked
            ->filter(fn (array $item) => $item['name_hits'] + $item['extra_hits'] >= max(1, $best))
            ->sort(fn (array $a, array $b) => [$b['name_hits'], $b['in_stock'], $b['extra_hits']] <=> [$a['name_hits'], $a['in_stock'], $a['extra_hits']])
            // Le même produit vendu par plusieurs boutiques n'est proposé qu'une fois.
            ->unique(fn (array $item) => $this->normalize((string) $item['product']->name))
            ->take($limit)
            ->pluck('product')
            ->values();
    }

    /** Vrai si le mot (ou son pluriel) apparaît comme un mot entier, pas au milieu d'un autre. */
    private function containsWord(string $haystack, string $needle): bool
    {
        return $needle !== '' && (bool) preg_match('/(?<![a-z0-9])'.preg_quote($needle, '/').'(s|x)?(?![a-z0-9])/u', $haystack);
    }

    /** Le client désigne un produit déjà affiché : « le premier », « le 2 », ou une partie de son nom. */
    private function pickFromShown(string $text, Collection $shown): ?Product
    {
        if ($shown->isEmpty()) {
            return null;
        }

        $ordinals = [
            '/\b(premier|premiere|1er|1ere|numero 1|le 1)\b/u' => 0,
            '/\b(deuxieme|second|seconde|2e|2eme|numero 2|le 2)\b/u' => 1,
            '/\b(troisieme|3e|3eme|numero 3|le 3)\b/u' => 2,
            '/\b(quatrieme|4e|4eme|numero 4|le 4)\b/u' => 3,
        ];
        foreach ($ordinals as $pattern => $index) {
            if (preg_match($pattern, $text) && $shown->has($index)) {
                return $shown->get($index);
            }
        }
        if (preg_match('/\b(dernier|derniere)\b/u', $text)) {
            return $shown->last();
        }

        $terms = $this->terms($text);
        if ($terms !== []) {
            $best = $shown
                ->map(fn (Product $product) => [
                    'product' => $product,
                    'hits' => collect($terms)->filter(fn ($term) => str_contains($this->normalize((string) $product->name), $this->normalize($term)))->count(),
                ])
                ->sortByDesc('hits')
                ->first();
            if ($best && $best['hits'] > 0) {
                return $best['product'];
            }
        }

        // « Ajoute-le au panier » alors qu'un seul produit est affiché.
        return $shown->count() === 1 ? $shown->first() : null;
    }

    private function pickBySearch(string $message, Collection $shown): ?Product
    {
        if ($this->terms($message) === []) {
            return null;
        }

        $found = $this->search($message, 2);
        if ($found->isEmpty()) {
            return null;
        }

        // Plusieurs résultats de même pertinence : mieux vaut demander que deviner.
        return $found->count() === 1 || $shown->isEmpty() ? $found->first() : null;
    }

    private function quantity(string $text): int
    {
        // On ignore les rangs (« le 2 », « numéro 2 ») : ce ne sont pas des quantités.
        $clean = preg_replace('/\b(le|numero) \d+\b|\b\d+(e|eme|er|ere)\b/u', ' ', $text) ?? $text;
        if (preg_match('/\b(\d{1,5})\b/u', $clean, $match)) {
            return max(1, (int) $match[1]);
        }

        $words = ['deux' => 2, 'trois' => 3, 'quatre' => 4, 'cinq' => 5, 'six' => 6, 'sept' => 7, 'huit' => 8, 'neuf' => 9, 'dix' => 10, 'vingt' => 20, 'cinquante' => 50, 'cent' => 100];
        foreach ($words as $word => $value) {
            if (preg_match('/\b'.$word.'\b/u', $clean)) {
                return $value;
            }
        }

        return 1;
    }

    /** @return array<string, mixed> */
    private function card(Product $product): array
    {
        // Prix client (commission comprise) : celui de la fiche produit et du panier.
        $price = (float) $product->final_price;
        $minimum = max(1, (int) ($product->min_order_quantity ?? 1));
        $stock = max(0, (int) ($product->stock ?? 0));

        return [
            'id' => (int) $product->id,
            'name' => (string) $product->name,
            'brand' => $product->brand ?: null,
            'category' => $product->category?->name,
            'price' => $price,
            'price_label' => number_format($price, 0, ',', ' ').' FCFA',
            'unit_label' => $product->display_unit ?? null,
            'image_url' => $product->card_image_url,
            'url' => route('product.show', $product),
            'min_quantity' => $minimum,
            'in_stock' => $stock >= $minimum,
            'needs_variant' => $product->relationLoaded('variants')
                ? $product->variants->where('is_active', true)->isNotEmpty()
                : $product->variants()->where('is_active', true)->exists(),
        ];
    }

    /** @param Collection<int, Product> $products */
    private function remember(SupportConversation $conversation, Collection $products): void
    {
        if ($products->isEmpty()) {
            return;
        }

        $metadata = (array) ($conversation->metadata ?? []);
        $metadata['assistant_products'] = $products->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $conversation->forceFill(['metadata' => $metadata])->save();
    }

    /** @return Collection<int, Product> */
    private function remembered(SupportConversation $conversation): Collection
    {
        $ids = array_values(array_filter(array_map('intval', (array) data_get($conversation->metadata, 'assistant_products', []))));
        if ($ids === []) {
            return collect();
        }

        $products = $this->visibility->query(['category', 'images', 'variants'], false)->whereKey($ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $products->get($id))->filter()->values();
    }

    /** @return list<string> */
    private function terms(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text)) ?: [];
        $stop = array_map(fn (string $word) => $this->normalize($word), self::STOP_WORDS);

        return collect($words)
            ->map(fn (string $word) => trim($word))
            ->filter(fn (string $word) => mb_strlen($word) >= 3 && ! ctype_digit($word) && ! in_array($this->normalize($word), $stop, true))
            // « sacs » doit aussi trouver « sac ».
            ->map(fn (string $word) => mb_strlen($word) > 4 ? preg_replace('/s$/u', '', $word) : $word)
            ->unique()
            ->take(6)
            ->values()
            ->all();
    }

    private function normalize(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', str_replace(['’', '\'', '-'], ' ', Str::lower(Str::ascii($text)))));
    }
}
