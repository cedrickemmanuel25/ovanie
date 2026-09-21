<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClientOrderResource;
use App\Models\Order;
use App\Services\CheckoutAddressResolver;
use App\Services\ClientOrderStatusService;
use App\Services\ClientOrderTimelineService;
use App\Services\Geo\AbidjanLocalityRegistry;
use App\Services\Geo\GeocodingService;
use App\Services\MobileCheckoutService;
use App\Services\PayDunyaOrderReconciliationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MobileOrderController extends Controller
{
    public function index(
        Request $request,
        PayDunyaOrderReconciliationService $reconciliation,
        ClientOrderStatusService $statusService
    ) {
        // Répare aussi un paiement effectué si le navigateur externe a été fermé
        // avant d'atteindre le return_url. On limite volontairement aux trois
        // dernières commandes PayDunya en attente pour ne pas multiplier les
        // appels au prestataire lors de l'ouverture de « Mes commandes ».
        $bucket = $request->string('bucket')->toString();

        // Seule la vue « En cours » a besoin de réconcilier les paiements en
        // attente. Les autres onglets ne doivent pas provoquer quatre séries
        // d'appels PayDunya lorsque l'écran mobile charge ses catégories.
        if ($bucket === '' || $bucket === 'in_progress') {
            Order::query()
                ->where('client_id', $request->user()->id)
                ->where('payment_method', 'paydunya')
                ->whereNotIn('payment_status', ['paid', 'commission_paid', 'escrow_held'])
                ->latest('id')
                ->limit(3)
                ->get()
                ->each(fn (Order $pendingOrder) => $reconciliation->reconcile($pendingOrder));
        }

        // V76 : la liste mobile doit utiliser EXACTEMENT le même périmètre
        // métier que l'espace client Web. Les tentatives PayDunya encore en
        // attente sont des brouillons techniques : le Web les masque via le
        // scope operational(), le mobile doit faire de même. Sans ce scope,
        // un même client voyait des commandes différentes selon la plateforme.
        $query = Order::query()
            ->operational()
            ->where('client_id', $request->user()->id)
            ->with([
                'items.product.images',
                'payments',
                'returns',
                'shipments:id,order_id,order_item_id,status',
            ]);

        $statusService->applyMobileBucket($query, $bucket);

        $orders = $query
            ->latest('created_at')
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->get('per_page', 20))));

        return ClientOrderResource::collection($orders)->additional([
            'summary' => $statusService->mobileCountsForClient((int) $request->user()->id),
            'source' => 'orders',
        ]);
    }

    public function show(
        Request $request,
        Order $order,
        PayDunyaOrderReconciliationService $reconciliation,
        ClientOrderTimelineService $timelineService
    ) {
        abort_unless((int) $order->client_id === (int) $request->user()->id, 403);

        // V75 : en local le webhook PayDunya ne peut pas joindre 127.0.0.1 ou
        // 192.168.x.x. Pendant que l'APK vérifie la commande, OVANIE interroge
        // directement PayDunya avec le token déjà enregistré. Le mobile ne
        // dépend donc plus du navigateur externe pour mettre le statut à jour.
        if ((string) $order->payment_method === 'paydunya'
            && ! in_array((string) $order->payment_status, ['paid', 'commission_paid', 'escrow_held'], true)) {
            $reconciliation->reconcile($order);
            $order = $order->fresh();
        }

        $order->load([
            'items.product.images',
            'items.statusHistories',
            'payments',
            'shipments.statusHistories',
            'returns.orderItem',
        ]);

        $timeline = $timelineService->build($order)->map(fn (array $event) => [
            'label' => $event['label'],
            'message' => $event['message'],
            'date' => $event['date']?->toIso8601String(),
            'type' => $event['type'],
            'state' => $event['state'],
            'order_item_id' => $event['order_item_id'],
        ])->values()->all();

        return response()->json([
            'data' => (new ClientOrderResource($order))->resolve($request),
            'timeline' => $timeline,
        ]);
    }

    public function store(
        Request $request,
        CheckoutAddressResolver $addresses,
        MobileCheckoutService $checkout,
        GeocodingService $geocoding,
        AbidjanLocalityRegistry $registry
    ) {
        // En développement local, php.ini peut imposer 30 s. L'initialisation
        // PayDunya peut dépasser ce délai sur une connexion lente. On relève le
        // plafond pour cette requête sans modifier globalement PHP.
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }
        @ini_set('max_execution_time', '120');

        Log::info('OVANIE mobile order: request started.', [
            'user_id' => $request->user()?->id,
            'payment_method' => $request->input('payment_method'),
            'existing_order_id' => $request->input('existing_order_id'),
        ]);

        $checkoutOperatorCodes = collect(app(\App\Services\OvanieReferenceDataService::class)->checkoutOperators())
            ->pluck('code')
            ->map(fn ($code) => (string) $code)
            ->filter()
            ->values()
            ->all();

        // Deuxième étape du paiement en ligne mobile. Comme sur le web,
        // la commande existe déjà avant que le client choisisse Wave,
        // Orange Money, MTN MoMo, Moov Money ou la carte bancaire.
        if ($request->filled('existing_order_id')) {
            $paymentData = $request->validate([
                'existing_order_id' => ['required', 'integer'],
                'payment_method' => ['required', Rule::in(['paydunya'])],
                'online_operator' => ['required', Rule::in($checkoutOperatorCodes)],
                'payment_phone' => [
                    Rule::requiredIf(fn () => $request->input('online_operator') !== 'card'),
                    'nullable',
                    'string',
                    'max:50',
                ],
                'orange_otp' => ['nullable', 'string', 'max:20'],
            ]);

            if (($paymentData['online_operator'] ?? '') !== 'card'
                && ! $this->isValidCiPhone((string) ($paymentData['payment_phone'] ?? ''))) {
                throw ValidationException::withMessages([
                    'payment_phone' => 'Saisissez un numéro Mobile Money ivoirien valide à 10 chiffres.',
                ]);
            }

            $order = Order::where('client_id', $request->user()->id)
                ->findOrFail((int) $paymentData['existing_order_id']);
            $result = $checkout->startOnlinePayment($request, $order, $paymentData);

            return response()->json([
                'message' => 'Paiement sécurisé initialisé.',
                'data' => (new ClientOrderResource($result['order']))->resolve($request),
                'payment' => [
                    'status' => $result['payment']->status,
                    'method' => $result['payment']->method,
                    'amount' => (float) $result['payment']->amount,
                    'payment_url' => $result['payment_url'],
                    'operator' => $result['payment_operator'] ?? $result['payment']->operator,
                ],
            ]);
        }

        $addresses->applyToRequest($request);
        $this->resolveGpsLocation($request, $geocoding, $registry);

        $data = $request->validate([
            'saved_address_id' => ['nullable', 'integer'],
            'full_name' => ['required', 'string', 'max:255'],
            'address' => [
                Rule::requiredIf(fn () => $request->input('delivery_destination_type', 'home') !== 'pickup'),
                'nullable',
                'string',
                'max:500',
            ],
            'phone' => ['required', 'string', 'max:50'],
            'delivery_destination_type' => ['nullable', Rule::in(['home', 'pickup'])],
            'delivery_zone' => ['required', Rule::in(['abidjan', 'interieur'])],
            'delivery_city' => ['nullable', 'string', 'max:150'],
            'delivery_commune' => ['nullable', 'string', 'max:150'],
            'delivery_quartier' => ['nullable', 'string', 'max:150'],
            'delivery_locality_type' => ['nullable', 'string', 'max:50'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_geo_accuracy' => ['nullable', 'numeric', 'min:0'],
            'delivery_geo_source' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', Rule::in(['paydunya', 'bank_transfer', 'cash_on_delivery'])],
            // Le choix de l'opérateur intervient sur l'écran de paiement,
            // après la création de la commande, exactement comme sur le web.
            'online_operator' => ['nullable', Rule::in($checkoutOperatorCodes)],
            'payment_phone' => ['nullable', 'string', 'max:50'],
            'orange_otp' => ['nullable', 'string', 'max:20'],
            'client_payment_method_id' => ['nullable', 'integer'],
            'loyalty_points' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'checkout_product_ids' => ['nullable', 'array'],
            'checkout_product_ids.*' => ['integer', 'min:1', 'distinct'],
            'checkout_cart_item_ids' => ['nullable', 'array'],
            'checkout_cart_item_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);

        if (! $this->isValidCiPhone((string) ($data['phone'] ?? ''))) {
            throw ValidationException::withMessages([
                'phone' => 'Saisissez un numéro ivoirien valide à 10 chiffres pour le contact de livraison.',
            ]);
        }

        if (($data['payment_method'] ?? '') === 'paydunya'
            && ! empty($data['online_operator'])
            && $data['online_operator'] !== 'card'
            && ! $this->isValidCiPhone((string) ($data['payment_phone'] ?? ''))) {
            throw ValidationException::withMessages([
                'payment_phone' => 'Saisissez un numéro Mobile Money ivoirien valide à 10 chiffres.',
            ]);
        }

        app(\App\Services\Geo\DeliveryPointGuard::class)->validateOperationalPoint($request);

        $result = $checkout->place($request, $data);

        Log::info('OVANIE mobile order: request completed.', [
            'user_id' => $request->user()?->id,
            'order_id' => $result['order']?->id,
            'payment_method' => $data['payment_method'],
            'has_payment_url' => ! empty($result['payment_url']),
        ]);

        $clientMessage = match ((string) $data['payment_method']) {
            'cash_on_delivery' => 'Commande confirmée. Le montant sera réglé à la livraison.',
            'paydunya' => 'Commande créée. Choisissez votre moyen de paiement sécurisé.',
            default => 'Commande créée. Le paiement est en attente de validation.',
        };

        return response()->json([
            'message' => $clientMessage,
            'data' => (new ClientOrderResource($result['order']))->resolve($request),
            'payment' => [
                'status' => $result['payment']->status,
                'method' => $result['payment']->method,
                'amount' => (float) $result['payment']->amount,
                'payment_url' => $result['payment_url'],
                'operator' => $result['payment_operator'] ?? $result['payment']->operator,
            ],
        ], 201);
    }

    private function isValidCiPhone(string $value): bool
    {
        $digits = preg_replace('/\D+/', '', $value) ?: '';
        if (str_starts_with($digits, '225') && strlen($digits) === 13) {
            $digits = substr($digits, 3);
        }

        return strlen($digits) === 10;
    }

    private function resolveGpsLocation(Request $request, GeocodingService $geocoding, AbidjanLocalityRegistry $registry): void
    {
        // Le checkout a déjà résolu l'adresse pendant l'étape preview. Ne pas
        // relancer un reverse-geocoding externe au moment du paiement si les
        // informations métier sont déjà présentes : cela évite plusieurs
        // secondes d'attente et rend l'initialisation PayDunya plus fiable.
        if ($request->filled('address') && $request->filled('delivery_commune')) {
            $canonical = $registry->canonicalCommune($request->input('delivery_commune'));
            if ($canonical) {
                $request->merge([
                    'delivery_commune' => $canonical,
                    'delivery_zone' => 'abidjan',
                ]);
            }
            return;
        }

        if (! $request->filled('delivery_latitude') || ! $request->filled('delivery_longitude')) {
            $canonical = $registry->canonicalCommune($request->input('delivery_commune'));
            if ($canonical) {
                $request->merge(['delivery_commune' => $canonical, 'delivery_zone' => 'abidjan']);
            }
            return;
        }

        try {
            $geo = $geocoding->reverse(
                (float) $request->input('delivery_latitude'),
                (float) $request->input('delivery_longitude')
            );
            $resolved = (array) ($geo['resolved_location'] ?? []);
            if ($resolved === []) {
                return;
            }

            $request->merge([
                'delivery_zone' => $resolved['zone'] ?? $request->input('delivery_zone'),
                'delivery_commune' => $resolved['commune']
                    ?: $registry->canonicalCommune($request->input('delivery_commune'))
                    ?: $request->input('delivery_commune'),
                'delivery_quartier' => $resolved['quartier'] ?: $request->input('delivery_quartier'),
                'delivery_city' => ($resolved['is_abidjan'] ?? false)
                    ? null
                    : ($resolved['city'] ?? $request->input('delivery_city')),
                'address' => $request->input('address') ?: ($geo['display_name'] ?? null),
                'delivery_geo_source' => $request->input('delivery_geo_source') ?: 'mobile_gps',
            ]);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
