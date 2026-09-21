<?php

namespace App\Http\Controllers;

use App\Models\GiftCardProduct;
use App\Models\GiftCardPurchase;
use App\Models\Payment;
use App\Services\GiftCardService;
use App\Services\PayDunyaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class GiftCardController extends Controller
{
    public function index(): View
    {
        $cards = $this->buildCards(
            GiftCardProduct::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->keyBy('slug')
        );

        return view('gift-cards.index', [
            'categories' => collect($this->categoryDefinitions())->map(function (array $category, string $key) use ($cards) {
                $category['key'] = $key;
                $category['count'] = $cards->where('group', $key)->count();
                return $category;
            })->values(),
        ]);
    }

    public function howItWorks(): View
    {
        return view('gift-cards.how-it-works');
    }

    public function terms(): View
    {
        $products = GiftCardProduct::query()->where('is_active', true)->get();

        return view('gift-cards.terms', [
            'products' => $products,
            'purchaseValidity' => $this->validityLabel($products->first(fn ($card) => str_starts_with((string) $card->slug, 'bon-achat-'))),
            'giftValidity' => $this->validityLabel($products->first(fn ($card) => str_starts_with((string) $card->slug, 'carte-cadeau-'))),
            'virtualValidity' => $this->validityLabel($products->first(fn ($card) => (bool) $card->is_rechargeable)),
        ]);
    }

    public function category(string $category): View
    {
        $definitions = $this->categoryDefinitions();
        abort_unless(array_key_exists($category, $definitions), 404);

        $cards = $this->buildCards(
            GiftCardProduct::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
                ->keyBy('slug')
        )->where('group', $category)->values();

        return view('gift-cards.category', [
            'categoryKey' => $category,
            'category' => $definitions[$category],
            'cards' => $cards,
            'categories' => collect($definitions)->map(function (array $item, string $key) {
                $item['key'] = $key;
                return $item;
            })->values(),
        ]);
    }

    public function show(GiftCardProduct $giftCardProduct): View
    {
        abort_unless($giftCardProduct->is_active, 404);

        $presentation = $this->presentationFor($giftCardProduct);
        $category = $this->categoryDefinitions()[$presentation['group']] ?? null;
        $products = GiftCardProduct::query()
            ->where('is_active', true)
            ->orderBy('activation_price')
            ->get();

        $amountChoices = $this->buildCards($products->keyBy('slug'))
            ->where('group', $presentation['group'])
            ->values();

        return view('gift-cards.show', compact(
            'giftCardProduct',
            'presentation',
            'category',
            'amountChoices'
        ));
    }


    /**
     * Ancienne URL conservée pour les liens déjà présents dans le navigateur.
     * On ne montre plus de fiche intermédiaire : on arrive directement au paiement.
     */
    public function createPurchase(GiftCardProduct $giftCardProduct): RedirectResponse
    {
        abort_unless($giftCardProduct->is_active, 404);

        return redirect()->route('gift-cards.payment.show', $giftCardProduct);
    }

    /**
     * Formulaire de paiement OVANIE.
     * Le prestataire de paiement reste entièrement en arrière-plan.
     */
    public function showPayment(Request $request, GiftCardProduct $giftCardProduct): View
    {
        abort_unless($giftCardProduct->is_active, 404);

        $presentation = $this->presentationFor($giftCardProduct);
        $category = $this->categoryDefinitions()[$presentation['group']] ?? null;

        $savedPaymentMethods = $request->user()
            ->paymentMethods()
            ->defaultFirst()
            ->get();

        $defaultSavedPaymentMethod = $savedPaymentMethods->firstWhere('is_default', true)
            ?? $savedPaymentMethods->first();

        $defaultOperator = old(
            'online_operator',
            $defaultSavedPaymentMethod?->operator ?? 'wave'
        );

        $defaultPhone = old(
            'payment_phone',
            $defaultSavedPaymentMethod?->phone
                ?? $request->user()->whatsapp_phone
                ?? $request->user()->phone
                ?? ''
        );

        return view('gift-cards.payment', [
            'product' => $giftCardProduct,
            'presentation' => $presentation,
            'category' => $category,
            'savedPaymentMethods' => $savedPaymentMethods,
            'defaultSavedPaymentMethod' => $defaultSavedPaymentMethod,
            'defaultOperator' => $defaultOperator,
            'defaultPhone' => $defaultPhone,
            'localPaymentSimulation' => app()->environment('local')
                && strtolower((string) config('paydunya.mode', 'test')) === 'test',
        ]);
    }

    /**
     * Lance le paiement depuis l'interface OVANIE.
     *
     * IMPORTANT :
     * - aucune page PayDunya n'est affichée au client ;
     * - en production, l'appel SoftPay est fait côté serveur ;
     * - en local + mode test, on simule uniquement la confirmation afin
     *   de tester le parcours OVANIE sans exposer le checkout PayDunya.
     */
    public function startPayment(
        Request $request,
        GiftCardProduct $giftCardProduct,
        PayDunyaService $paydunya,
        GiftCardService $giftCards
    ): RedirectResponse {
        abort_unless($giftCardProduct->is_active, 404);

        $isLive = strtolower((string) config('paydunya.mode', 'test')) !== 'test';

        $data = $request->validate([
            'for_me' => ['nullable', 'boolean'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'recipient_email' => ['nullable', 'email', 'max:190'],
            'recipient_phone' => ['nullable', 'string', 'max:40'],
            'personal_message' => ['nullable', 'string', 'max:500'],
            'client_payment_method_id' => ['nullable', 'integer'],
            'online_operator' => ['required', Rule::in(['wave', 'orange', 'mtn', 'moov'])],
            'payment_phone' => ['required', 'string', 'max:30'],
            'orange_otp' => [
                Rule::requiredIf(
                    fn () => $isLive && $request->input('online_operator') === 'orange'
                ),
                'nullable',
                'string',
                'max:20',
            ],
        ]);

        $user = $request->user();

        // Les cartes virtuelles sont personnelles.
        // Pour les bons/cartes cadeaux, "Pour moi" est la valeur par défaut.
        $forMe = $giftCardProduct->is_rechargeable
            ? true
            : (! $request->has('for_me') || $request->boolean('for_me'));

        if (! $forMe
            && blank($data['recipient_name'] ?? null)
            && blank($data['recipient_phone'] ?? null)
            && blank($data['recipient_email'] ?? null)) {
            return back()->withErrors([
                'recipient_name' => 'Renseignez au moins le nom, le téléphone ou l’e-mail du bénéficiaire.',
            ])->withInput();
        }

        $operator = trim((string) $data['online_operator']);
        $phone = trim((string) $data['payment_phone']);
        $orangeOtp = trim((string) ($data['orange_otp'] ?? ''));
        $amount = round((float) $giftCardProduct->activation_price, 2);

        [$purchase, $payment] = DB::transaction(function () use (
            $data,
            $forMe,
            $user,
            $giftCardProduct,
            $amount,
            $operator,
            $phone
        ) {
            $purchase = GiftCardPurchase::create([
                'gift_card_product_id' => $giftCardProduct->id,
                'buyer_user_id' => $user->id,
                'recipient_name' => $forMe ? $user->name : ($data['recipient_name'] ?? null),
                'recipient_email' => $forMe ? $user->email : ($data['recipient_email'] ?? null),
                'recipient_phone' => $forMe
                    ? ($user->whatsapp_phone ?: $user->phone)
                    : ($data['recipient_phone'] ?? null),
                'personal_message' => $data['personal_message'] ?? null,
                'amount' => $amount,
                'status' => 'pending',

                // Nom technique du prestataire, jamais affiché dans l'interface client.
                'payment_method' => 'paydunya',
            ]);

            $payment = Payment::create([
                'order_id' => null,
                'method' => 'paydunya',
                'type' => 'gift_card_purchase',
                'amount' => $amount,
                'status' => Payment::STATUS_PENDING,
                'user_id' => $user->id,
                'operator' => $operator,
                'mobile_number' => $phone,
                'reference' => 'GIFTBUY-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(8)),
                'provider_payload' => [
                    'gift_card_purchase_id' => $purchase->id,
                    'gift_card_product_id' => $giftCardProduct->id,
                    'server_price' => $amount,
                    'payment_operator' => $operator,
                ],
            ]);

            $purchase->update(['payment_id' => $payment->id]);

            return [$purchase, $payment];
        }, 3);

        /*
        |--------------------------------------------------------------------------
        | TEST LOCAL
        |--------------------------------------------------------------------------
        |
        | PayDunya ne peut pas appeler 127.0.0.1 et son SoftPay sandbox peut être
        | indisponible. En local, on confirme donc le paiement uniquement pour
        | tester le parcours fonctionnel OVANIE.
        |
        | En production, ce bloc n'est jamais exécuté.
        */
        if (app()->environment('local')
            && strtolower((string) config('paydunya.mode', 'test')) === 'test') {
            $giftCards->confirmPayDunyaPayment($payment, [
                'data' => [
                    'status' => 'completed',
                    'transaction_id' => 'LOCAL-GIFT-' . Str::upper(Str::random(12)),
                    'invoice' => [
                        'total_amount' => $amount,
                        'currency' => 'XOF',
                        'token' => $payment->reference,
                    ],
                ],
                'local_simulation' => true,
            ]);

            return redirect()->route('gift-cards.payment.return', [
                'token' => $payment->reference,
            ])->with(
                'success',
                'Paiement local simulé avec succès. La carte OVANIE a été générée.'
            );
        }

        try {
            /*
            |--------------------------------------------------------------------------
            | PRODUCTION : facture technique + SoftPay en arrière-plan
            |--------------------------------------------------------------------------
            */
            $invoice = $paydunya->createOrderInvoice([
                'item_name' => $giftCardProduct->name,
                'description' => 'Achat d’une carte OVANIE',
                'amount' => $amount,
                'return_url' => route('gift-cards.payment.return'),
                'cancel_url' => route('gift-cards.payment.cancel'),
            ]);

            if (! $invoice->create()) {
                throw new \RuntimeException(
                    $invoice->response_text ?? 'Impossible d’initialiser le paiement.'
                );
            }

            $token = $this->extractInvoiceToken($invoice, $payment->reference);

            $payment->update(['reference' => $token]);
            $purchase->update(['provider_token' => $token]);

            $softPay = $paydunya->startSoftPay([
                'operator' => $operator,
                'payment_token' => $token,
                'full_name' => (string) $user->name,
                'email' => (string) $user->email,
                'phone' => $phone,
                'orange_otp' => $orangeOtp,
            ]);

            if (empty($softPay['success'])) {
                throw new \RuntimeException(
                    trim((string) ($softPay['message'] ?? ''))
                        ?: 'Le paiement n’a pas pu être lancé. Vérifiez les informations saisies.'
                );
            }

            /*
             * Certains opérateurs (ex. Wave) peuvent retourner leur propre URL.
             * On autorise uniquement une URL de l'opérateur.
             * Une URL PayDunya n'est jamais affichée au client.
             */
            $providerUrl = trim((string) ($softPay['url'] ?? ''));

            if ($providerUrl !== '' && filter_var($providerUrl, FILTER_VALIDATE_URL)) {
                $host = strtolower((string) parse_url($providerUrl, PHP_URL_HOST));

                if ($host !== ''
                    && ! str_contains($host, 'paydunya.com')
                    && ! str_contains($host, 'app.paydunya.com')) {
                    return redirect()->away($providerUrl);
                }
            }

            return redirect()->route('gift-cards.payment.return', [
                'token' => $token,
            ])->with(
                'success',
                trim((string) ($softPay['message'] ?? ''))
                    ?: 'Paiement lancé. Validez la demande sur votre téléphone.'
            );
        } catch (\Throwable $e) {
            report($e);

            $giftCards->failPayDunyaPayment($payment, 'failed');

            return back()
                ->with(
                    'error',
                    $e->getMessage() ?: 'Le paiement n’a pas pu être lancé.'
                )
                ->withInput($request->except('orange_otp'));
        }
    }

    private function presentationFor(GiftCardProduct $giftCardProduct): array
    {
        $presentation = collect($this->presentations())
            ->firstWhere('slug', $giftCardProduct->slug);

        if ($presentation) {
            return $presentation;
        }

        return [
            'slug' => $giftCardProduct->slug,
            'group' => $giftCardProduct->is_rechargeable
                ? 'carte-virtuelle'
                : 'carte-cadeau',
            'type' => $giftCardProduct->is_rechargeable
                ? 'Carte Virtuelle'
                : 'Carte Cadeau',
            'title' => $giftCardProduct->name,
            'summary' => $giftCardProduct->description,
            'accent' => $giftCardProduct->is_rechargeable
                ? 'Portefeuille rechargeable'
                : 'Paiement flexible',
        ];
    }

    private function validityLabel(?GiftCardProduct $product): string
    {
        if (! $product) {
            return 'Selon la carte';
        }

        return $product->validity_days
            ? $product->validity_days.' jours'
            : $product->validity_months.' mois';
    }

    private function extractInvoiceToken(object $invoice, string $fallback): string
    {
        $token = $invoice->token
            ?? $invoice->invoice_token
            ?? ($invoice->response_array['token'] ?? null)
            ?? ($invoice->response['token'] ?? null)
            ?? $fallback;

        return trim((string) $token);
    }

    private function buildCards(Collection $products): Collection
    {
        return collect($this->presentations())
            ->map(function (array $presentation) use ($products) {
                $product = $products->get($presentation['slug']);

                if (! $product) {
                    return null;
                }

                return [
                    ...$presentation,
                    'product' => $product,
                ];
            })
            ->filter()
            ->values();
    }

    private function categoryDefinitions(): array
    {
        return [
            'bon-achat' => [
                'title' => 'Bon d’Achat',
                'eyebrow' => 'ÉCONOMISER',
                'description' => 'Économisez sur vos prochains achats avec un bon de réduction OVANIE.',
                'long_description' => 'Choisissez un montant, achetez votre bon puis utilisez son code sécurisé au moment du paiement sur OVANIE. Le solde non utilisé reste disponible jusqu’à expiration.',
                'icon' => 'ticket',
            ],
            'carte-cadeau' => [
                'title' => 'Carte Cadeau',
                'eyebrow' => 'FAIRE PLAISIR',
                'description' => 'Offrez un bon shopping à vos proches sur toute la plateforme OVANIE.',
                'long_description' => 'Une solution simple pour offrir un budget shopping OVANIE. La carte peut être envoyée à un proche et utilisée en une ou plusieurs fois selon son solde.',
                'icon' => 'gift',
            ],
            'carte-virtuelle' => [
                'title' => 'Carte Virtuelle',
                'eyebrow' => 'PORTEFEUILLE PREMIUM',
                'description' => 'Votre portefeuille OVANIE premium. Rechargez et payez partout.',
                'long_description' => 'ACCÈS, PREMIUM et GOLD sont des cartes personnelles rechargeables rattachées à votre compte OVANIE, avec une durée et un plafond adaptés à chaque formule.',
                'icon' => 'card',
            ],
        ];
    }

    private function presentations(): array
    {
        return [
            [
                'slug' => 'bon-achat-50k',
                'group' => 'bon-achat',
                'type' => 'Bon d’Achat',
                'title' => 'Bon d’Achat OVANIE 50 000 FCFA',
                'summary' => 'Économisez sur vos prochains achats avec un bon de réduction OVANIE.',
                'accent' => 'Économiser',
            ],
            [
                'slug' => 'bon-achat-150k',
                'group' => 'bon-achat',
                'type' => 'Bon d’Achat',
                'title' => 'Bon d’Achat OVANIE 150 000 FCFA',
                'summary' => 'Économisez sur vos prochains achats avec un bon de réduction OVANIE.',
                'accent' => 'Économiser',
            ],
            [
                'slug' => 'bon-achat-200k',
                'group' => 'bon-achat',
                'type' => 'Bon d’Achat',
                'title' => 'Bon d’Achat OVANIE 200 000 FCFA',
                'summary' => 'Économisez sur vos prochains achats avec un bon de réduction OVANIE.',
                'accent' => 'Économiser',
            ],
            [
                'slug' => 'carte-cadeau-100k',
                'group' => 'carte-cadeau',
                'type' => 'Carte Cadeau',
                'title' => 'Carte Cadeau OVANIE 100 000 FCFA',
                'summary' => 'Offrez un bon shopping à vos proches sur toute la plateforme OVANIE.',
                'accent' => 'Faire plaisir',
            ],
            [
                'slug' => 'carte-cadeau-200k',
                'group' => 'carte-cadeau',
                'type' => 'Carte Cadeau',
                'title' => 'Carte Cadeau OVANIE 200 000 FCFA',
                'summary' => 'Offrez un bon shopping à vos proches sur toute la plateforme OVANIE.',
                'accent' => 'Faire plaisir',
            ],
            [
                'slug' => 'carte-cadeau-300k',
                'group' => 'carte-cadeau',
                'type' => 'Carte Cadeau',
                'title' => 'Carte Cadeau OVANIE 300 000 FCFA',
                'summary' => 'Offrez un bon shopping à vos proches sur toute la plateforme OVANIE.',
                'accent' => 'Faire plaisir',
            ],
            [
                'slug' => 'carte-cadeau-400k',
                'group' => 'carte-cadeau',
                'type' => 'Carte Cadeau',
                'title' => 'Carte Cadeau OVANIE 400 000 FCFA',
                'summary' => 'Offrez un bon shopping à vos proches sur toute la plateforme OVANIE.',
                'accent' => 'Faire plaisir',
            ],
            [
                'slug' => 'carte-acces-150k',
                'group' => 'carte-virtuelle',
                'type' => 'Carte Virtuelle',
                'title' => 'Carte Virtuelle OVANIE ACCÈS',
                'summary' => 'Votre portefeuille OVANIE premium. Rechargez et payez partout.',
                'accent' => 'Portefeuille rechargeable',
            ],
            [
                'slug' => 'carte-premium-250k',
                'group' => 'carte-virtuelle',
                'type' => 'Carte Virtuelle',
                'title' => 'Carte Virtuelle OVANIE PREMIUM',
                'summary' => 'Votre portefeuille OVANIE premium. Rechargez et payez partout.',
                'accent' => 'Portefeuille rechargeable',
            ],
            [
                'slug' => 'carte-gold-450k',
                'group' => 'carte-virtuelle',
                'type' => 'Carte Virtuelle',
                'title' => 'Carte Virtuelle OVANIE GOLD',
                'summary' => 'Votre portefeuille OVANIE premium. Rechargez et payez partout.',
                'accent' => 'Portefeuille rechargeable',
            ],
        ];
    }
}
