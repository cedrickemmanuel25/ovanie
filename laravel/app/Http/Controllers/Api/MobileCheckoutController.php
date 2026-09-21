<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Services\CartFulfillmentOptimizer;
use App\Services\CheckoutAddressResolver;
use App\Services\CheckoutSummaryService;
use App\Services\CommissionService;
use App\Services\CheckoutPaymentOptionsService;
use App\Services\CheckoutCartSelectionService;
use App\Services\MobileCartResolver;
use App\Services\Geo\AbidjanLocalityRegistry;
use App\Services\Geo\GeocodingService;
use App\Services\Geo\AbidjanLocationResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileCheckoutController extends Controller
{
    public function preview(
        Request $request,
        CheckoutAddressResolver $addresses,
        CheckoutSummaryService $checkoutSummary,
        CartFulfillmentOptimizer $optimizer,
        CommissionService $commissions,
        MobileCartResolver $carts,
        GeocodingService $geocoding,
        AbidjanLocalityRegistry $registry,
        AbidjanLocationResolver $locations,
        CheckoutPaymentOptionsService $paymentOptions,
        CheckoutCartSelectionService $selection
    ) {
        $addresses->applyToRequest($request);
        $this->normalizeDeliveryLocation($request, $geocoding, $registry, $locations);

        $request->validate([
            'saved_address_id' => ['nullable', 'integer'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'address' => [
                Rule::requiredIf(fn () => $request->input('delivery_destination_type', 'home') !== 'pickup'),
                'nullable',
                'string',
                'max:500',
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'delivery_destination_type' => ['nullable', Rule::in(['home', 'pickup'])],
            'delivery_zone' => ['required', Rule::in(['abidjan', 'interieur'])],
            'delivery_city' => ['nullable', 'string', 'max:150'],
            'delivery_commune' => ['nullable', 'string', 'max:150'],
            'delivery_quartier' => ['nullable', 'string', 'max:150'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'delivery_geo_accuracy' => ['nullable', 'numeric', 'min:0'],
            'delivery_geo_source' => ['nullable', 'string', 'max:50'],
            'delivery_locality_type' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['nullable', Rule::in(['paydunya', 'bank_transfer', 'cash_on_delivery'])],
            'checkout_product_ids' => ['nullable', 'array'],
            'checkout_product_ids.*' => ['integer', 'min:1', 'distinct'],
            'checkout_cart_item_ids' => ['nullable', 'array'],
            'checkout_cart_item_ids.*' => ['integer', 'min:1', 'distinct'],
        ]);

        app(\App\Services\Geo\DeliveryPointGuard::class)->validateOperationalPoint($request);
        $territoryZone = app(\App\Services\LogisticsTerritoryCoverageService::class)->summaryForRequest($request);

        $cart = $carts->forUser($request->user());
        if ($cart) {
            $selection->apply(
                $cart,
                (array) $request->input('checkout_product_ids', []),
                (array) $request->input('checkout_cart_item_ids', [])
            );
        }

        if (! $cart || $cart->items->isEmpty()) {
            return response()->json([
                'subtotal' => 0,
                'delivery_fee' => 0,
                'total' => 0,
                'commission' => 0,
                'delivery_breakdown' => [],
                'total_weight_kg' => 0,
                'total_volume_m3' => 0,
                'recommended_vehicle' => null,
                'recommended_vehicle_code' => null,
                'delivery_quote_required' => false,
                'delivery_calculated' => false,
                'delivery_available' => false,
                'delivery_message' => 'Votre panier est vide.',
                'resolved_delivery_location' => $this->resolvedDeliveryLocation($request),
                'territory_zone' => $territoryZone,
                'payment_options' => $paymentOptions->forCart($cart, 0),
                'checkout_state' => $this->checkoutState($request, false, false, $paymentOptions->forCart($cart, 0)),
            ]);
        }

        if (! $this->hasSufficientDeliveryAddress($request)) {
            $subtotal = (float) $cart->items->sum(
                fn ($item) => (float) $item->price * (int) $item->quantity
            );

            return response()->json([
                'subtotal' => $subtotal,
                'delivery_fee' => 0,
                'total' => $subtotal,
                'commission' => $commissions->commissionFromPublicItems($cart->items),
                'delivery_breakdown' => [],
                'total_weight_kg' => 0,
                'total_volume_m3' => 0,
                'recommended_vehicle' => null,
                'recommended_vehicle_code' => null,
                'delivery_quote_required' => false,
                'delivery_calculated' => false,
                'delivery_available' => false,
                'delivery_message' => 'Complétez votre adresse de livraison pour calculer les frais.',
                'resolved_delivery_location' => $this->resolvedDeliveryLocation($request),
                'territory_zone' => $territoryZone,
                'payment_options' => $paymentOptions->forCart($cart, $subtotal),
                'checkout_state' => $this->checkoutState($request, true, false, $paymentOptions->forCart($cart, $subtotal)),
            ]);
        }

        $preview = $optimizer->previewForAddress($cart, $this->optimizationAddressPayload($request));
        $checkout = $checkoutSummary->build($preview['cart'], $request);

        return response()->json([
            'subtotal' => $checkout['subtotal'],
            'delivery_fee' => $checkout['delivery_fee'],
            'total' => $checkout['total'],
            'commission' => $checkout['commission'],
            'delivery_quote_required' => $checkout['delivery_quote_required'] ?? false,
            'delivery_available' => $checkout['delivery_available'] ?? false,
            'delivery_message' => $checkout['client_delivery_message'] ?? null,
            'delivery_debug' => $checkout['delivery_issues'] ?? [],
            'delivery_calculated' => true,
            'total_weight_kg' => $checkout['total_weight_kg'] ?? 0,
            'total_volume_m3' => $checkout['total_volume_m3'] ?? 0,
            'recommended_vehicle' => $checkout['recommended_vehicle'] ?? null,
            'recommended_vehicle_code' => $checkout['recommended_vehicle_code'] ?? null,
            'shop_deliveries' => $checkout['shop_deliveries'] ?? [],
            'resolved_delivery_location' => $this->resolvedDeliveryLocation($request),
            'territory_zone' => $territoryZone,
            'payment_options' => $paymentOptions->forCart($preview['cart'], (float) $checkout['total']),
            'checkout_state' => $this->checkoutState(
                $request,
                true,
                ! empty($checkout['delivery_available']) && empty($checkout['delivery_quote_required']),
                $paymentOptions->forCart($preview['cart'], (float) $checkout['total'])
            ),
        ]);
    }

    /**
     * Normalise la destination mobile avant le calcul de livraison.
     *
     * Une localité (quartier, sous-quartier, cité, village, carrefour) doit
     * toujours être rattachée à sa commune parente. Exemple :
     * « Feh Kessé » => commune « Bingerville », quartier/localité « Feh Kessé ».
     * Le moteur tarifaire travaille ensuite sur la commune, tandis que la
     * localité précise reste disponible pour la logistique et l'affichage.
     */
    private function normalizeDeliveryLocation(
        Request $request,
        GeocodingService $geocoding,
        AbidjanLocalityRegistry $registry,
        AbidjanLocationResolver $locations
    ): void {
        $textParts = collect([
            $request->input('delivery_commune'),
            $request->input('delivery_quartier'),
            $request->input('address'),
            $request->input('delivery_city'),
        ])->filter(fn ($value) => filled($value))->values()->all();

        $textResolved = $registry->resolve(
            $textParts,
            $request->input('delivery_commune'),
            $request->input('delivery_quartier')
        );

        if (($textResolved['is_abidjan'] ?? false) && filled($textResolved['commune'] ?? null)) {
            $request->merge([
                'delivery_zone' => 'abidjan',
                'delivery_commune' => $textResolved['commune'],
                'delivery_quartier' => $textResolved['quarter']
                    ?: $request->input('delivery_quartier'),
                'delivery_city' => null,
                'delivery_locality_type' => $textResolved['locality_type'] ?? null,
            ]);
        } elseif (filled($request->input('delivery_commune'))) {
            $canonical = $locations->detectCommune($textParts)
                ?: $registry->canonicalCommune($request->input('delivery_commune'));
            if ($canonical) {
                $request->merge([
                    'delivery_zone' => 'abidjan',
                    'delivery_commune' => $canonical,
                    'delivery_city' => null,
                ]);
            }
        }

        // Si le libellé déjà validé par le checkout correspond à une localité
        // officielle OVANIE, ne pas refaire un reverse-geocoding réseau : la
        // hiérarchie commune/localité est déjà déterministe et c'est exactement
        // la destination qui doit alimenter la grille tarifaire.
        if (($textResolved['locality_catalogued'] ?? false)
            && filled($textResolved['commune'] ?? null)) {
            return;
        }

        if (! $request->filled('delivery_latitude') || ! $request->filled('delivery_longitude')) {
            return;
        }

        try {
            $geo = $geocoding->reverse(
                (float) $request->input('delivery_latitude'),
                (float) $request->input('delivery_longitude')
            );
            $resolved = (array) ($geo['resolved_location'] ?? []);

            $geoParts = collect([
                $resolved['commune'] ?? null,
                $resolved['quartier'] ?? null,
                $resolved['repere'] ?? null,
                $geo['display_name'] ?? null,
                $request->input('delivery_commune'),
                $request->input('delivery_quartier'),
                $request->input('address'),
            ])->filter(fn ($value) => filled($value))->values()->all();

            $registryResolved = $registry->resolve(
                $geoParts,
                $resolved['commune'] ?? $request->input('delivery_commune'),
                $resolved['quartier'] ?? $request->input('delivery_quartier')
            );

            $isAbidjan = (bool) ($resolved['is_abidjan'] ?? false)
                || (bool) ($registryResolved['is_abidjan'] ?? false);

            $commune = $registryResolved['commune']
                ?? $locations->detectCommune($geoParts)
                ?? ($resolved['commune'] ?? null)
                ?? $request->input('delivery_commune');

            $quartier = $registryResolved['quarter']
                ?? ($resolved['quartier'] ?? null)
                ?? $request->input('delivery_quartier');

            $request->merge([
                'delivery_zone' => $isAbidjan
                    ? 'abidjan'
                    : ($resolved['zone'] ?? $request->input('delivery_zone')),
                'delivery_commune' => $isAbidjan ? $commune : $request->input('delivery_commune'),
                'delivery_quartier' => $quartier,
                'delivery_city' => $isAbidjan
                    ? null
                    : ($resolved['city'] ?? $request->input('delivery_city')),
                'address' => $request->input('address') ?: ($geo['display_name'] ?? null),
                'delivery_geo_source' => $request->input('delivery_geo_source') ?: 'mobile_gps',
                'delivery_locality_type' => $registryResolved['locality_type']
                    ?? ($resolved['locality_type'] ?? null),
            ]);
        } catch (\Throwable $exception) {
            // La résolution textuelle ci-dessus reste utilisable même si un
            // fournisseur cartographique est momentanément indisponible.
            report($exception);
        }
    }

    private function resolvedDeliveryLocation(Request $request): array
    {
        return [
            'zone' => (string) $request->input('delivery_zone', ''),
            'commune' => (string) $request->input('delivery_commune', ''),
            'quartier' => (string) $request->input('delivery_quartier', ''),
            'locality_type' => (string) $request->input('delivery_locality_type', ''),
            'city' => (string) $request->input('delivery_city', ''),
            'address' => (string) $request->input('address', ''),
            'latitude' => $request->input('delivery_latitude'),
            'longitude' => $request->input('delivery_longitude'),
        ];
    }

    private function hasSufficientDeliveryAddress(Request $request): bool
    {
        if ($request->input('delivery_destination_type') === 'pickup') {
            return true;
        }

        if (! $request->filled('address') || ! $request->filled('delivery_zone')) {
            return false;
        }

        if ($request->input('delivery_zone') === 'abidjan') {
            return $request->filled('delivery_commune')
                || ($request->filled('delivery_latitude') && $request->filled('delivery_longitude'));
        }

        return $request->input('delivery_zone') === 'interieur'
            && $request->filled('delivery_city');
    }

    private function checkoutState(Request $request, bool $cartValid, bool $deliveryValid, array $paymentOptions): array
    {
        $addressValid = $this->hasSufficientDeliveryAddress($request);
        $phoneDigits = preg_replace('/\D+/', '', (string) $request->input('phone', ''));
        if (str_starts_with($phoneDigits, '225') && strlen($phoneDigits) === 13) {
            $phoneDigits = substr($phoneDigits, 3);
        }
        $contactValid = $request->filled('full_name') && strlen($phoneDigits) === 10;
        $method = trim((string) $request->input('payment_method', ''));
        $selected = collect($paymentOptions['methods'] ?? [])->firstWhere('code', $method);
        $paymentMethodValid = $method !== '' && (bool) ($selected['enabled'] ?? false);

        $nextStep = 'delivery';
        if ($cartValid && $addressValid && $contactValid && $deliveryValid) {
            $nextStep = $paymentMethodValid ? 'confirmation' : 'payment';
        }

        return [
            'cart_valid' => $cartValid,
            'address_valid' => $addressValid,
            'contact_valid' => $contactValid,
            'delivery_valid' => $deliveryValid,
            'payment_method_valid' => $paymentMethodValid,
            'can_place_order' => $cartValid && $addressValid && $contactValid && $deliveryValid && $paymentMethodValid,
            'next_step' => $nextStep,
        ];
    }

    private function optimizationAddressPayload(Request $request): array
    {
        return [
            'address' => $request->input('address'),
            'delivery_zone' => $request->input('delivery_zone'),
            'delivery_commune' => $request->input('delivery_commune'),
            'delivery_quartier' => $request->input('delivery_quartier'),
            'delivery_locality_type' => $request->input('delivery_locality_type'),
            'delivery_city' => $request->input('delivery_city'),
            'delivery_latitude' => $request->input('delivery_latitude'),
            'delivery_longitude' => $request->input('delivery_longitude'),
            'delivery_geo_source' => $request->input('delivery_geo_source'),
        ];
    }
}
