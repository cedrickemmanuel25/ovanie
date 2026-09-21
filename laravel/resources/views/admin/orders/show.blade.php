@extends('admin.layouts.app')

@section('title', 'Détail commande | Admin OVANIE')
@section('page-title', 'Détail de la commande')

@push('styles')
    <link rel="stylesheet" href="{{ asset('admin/css/admin_order_detail.css') }}">
@endpush

@section('content')
    @php
        $statusLabels = [
            'pending' => 'En attente',
            'confirmed' => 'Confirmée',
            'processing' => 'En traitement',
            'shipped' => 'Expédiée',
            'delivered' => 'Livrée',
            'cancelled' => 'Annulée',
            'completed' => 'Finalisée',
            'paid' => 'Payée',
        ];

        $paymentLabels = [
            'pending' => 'En attente',
            'paid' => 'Payé',
            'failed' => 'Échoué',
            'commission_paid' => 'Commission payée',
            'cod_completed' => 'PAL finalisé',
            'verified' => 'Vérifié',
        ];

        $paymentMethodLabels = [
            'online' => 'Paiement en ligne sécurisé',
            'paydunya' => 'Paiement en ligne sécurisé',
            'cash_on_delivery' => 'Paiement à la livraison',
            'bank_transfer' => 'Virement bancaire',
            'mobile_money' => 'Paiement Mobile Money',
            'wave' => 'Paiement Wave',
            'orange_money' => 'Paiement Orange Money',
            'mtn_momo' => 'Paiement MTN MoMo',
            'moov_money' => 'Paiement Moov Money',
        ];

        $deliveryStatusLabels = [
            'pending' => 'En attente',
            'accepted' => 'Acceptée',
            'confirmed' => 'Confirmée',
            'preparing' => 'En préparation',
            'vendor_preparing' => 'En préparation',
            'processing' => 'En traitement',
            'ready' => 'Prête',
            'ready_for_pickup' => 'Prête pour enlèvement',
            'assigned' => 'Livreur affecté',
            'driver_assigned' => 'Livreur affecté',
            'picked_up' => 'Collectée',
            'collected' => 'Collectée',
            'loading' => 'Chargement en cours',
            'loaded' => 'Chargement terminé',
            'in_delivery' => 'En livraison',
            'out_for_delivery' => 'En cours de livraison',
            'in_transit' => 'En transit',
            'on_the_way' => 'En route',
            'arrived' => 'Arrivée à destination',
            'delivered' => 'Livrée',
            'completed' => 'Terminée',
            'failed' => 'Échec',
            'rejected' => 'Refusée',
            'cancelled' => 'Annulée',
            'returned' => 'Retournée',
        ];

        $vehicleLabels = [
            'moto' => 'Moto',
            'motorbike' => 'Moto',
            'tricycle' => 'Tricycle',
            'pickup' => 'Véhicule utilitaire',
            'camion_3t' => 'Camion 3 tonnes',
            'truck_3t' => 'Camion 3 tonnes',
            'camion_10t' => 'Camion 10 tonnes',
            'truck_10t' => 'Camion 10 tonnes',
        ];

        $clientName = $order->client?->name
            ?: trim(($order->client?->first_name ?? '') . ' ' . ($order->client?->last_name ?? ''))
            ?: ($order->customer_name ?? 'Client non identifié');

        $displayNumber = $order->order_number ?: ('#' . $order->id);
        $itemsCount = $order->items->sum('quantity');
        $paidLines = $order->items->where('is_paid', true)->count();
        $releasedLines = $order->items->whereNotNull('vendor_visible_at')->count();
        $realSubtotal = (float) ($order->subtotal ?? $order->items->sum(fn ($item) => (float) $item->price * (int) $item->quantity));
        $realDelivery = (float) ($order->delivery_fee_total ?? $order->delivery_fee ?? 0);
        $realTotal = (float) ($order->total_amount ?? ($realSubtotal + $realDelivery));
        $clientLat = $order->delivery_latitude ?? $order->delivery_lat;
        $clientLng = $order->delivery_longitude ?? $order->delivery_lng;
    @endphp

    <div class="order-detail-page">
        <div class="order-detail-header">
            <div>
                <a href="{{ route('admin.orders.index') }}" class="back-link">
                    <svg viewBox="0 0 24 24"><path d="m15 18-6-6 6-6" /></svg>
                    Retour aux commandes
                </a>

                <div class="order-title-line">
                    <div>
                        <span class="page-eyebrow">Commande client</span>
                        <h2>{{ $displayNumber }}</h2>
                        <p>Créée le {{ $order->created_at?->format('d/m/Y à H:i') ?? '—' }}</p>
                    </div>

                    <div class="header-badges">
                        <span class="status-badge status-{{ $order->status ?: 'pending' }}">
                            {{ $statusLabels[$order->status] ?? ucfirst($order->status ?: 'pending') }}
                        </span>
                        <span class="status-badge status-{{ $order->payment_status ?: 'pending' }}">
                            Paiement : {{ $paymentLabels[$order->payment_status] ?? ucfirst($order->payment_status ?: 'pending') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="header-actions">
                @if($hasReceptionForm)
                    <a href="{{ route('admin.orders.reception.show', $order) }}" class="btn-light">
                        Voir la réception
                    </a>
                @endif
            </div>
        </div>

        <section class="order-kpis">
            <article>
                <span>Total commande</span>
                <strong>{{ number_format($realTotal, 0, ',', ' ') }} FCFA</strong>
                <small>{{ $paymentMethodLabels[$order->payment_method] ?? ($order->payment_method ?: 'Mode non défini') }}</small>
            </article>
            <article>
                <span>Produits</span>
                <strong>{{ $itemsCount }}</strong>
                <small>{{ $order->items->count() }} ligne(s) de commande</small>
            </article>
            <article>
                <span>Boutiques concernées</span>
                <strong>{{ $shopGroups->count() }}</strong>
                <small>Commande multi-vendeurs contrôlée</small>
            </article>
            <article>
                <span>Lignes transmises</span>
                <strong>{{ $releasedLines }}/{{ $order->items->count() }}</strong>
                <small>Visibles dans les espaces vendeurs</small>
            </article>
        </section>

        <section class="detail-card vendors-card">
            <div class="section-heading">
                <div>
                    <span class="section-kicker">Visible uniquement par l’administration</span>
                    <h3>Répartition par boutique et vendeur</h3>
                    <p>Chaque bloc indique précisément les produits que le vendeur doit préparer et la logistique applicable.</p>
                </div>
                <span class="section-counter">{{ $shopGroups->count() }} boutique(s)</span>
            </div>

            <div class="vendor-groups">
                @forelse($shopGroups as $group)
                    @php
                        $shop = $group['shop'];
                        $seller = $group['seller'];
                        $groupItems = $group['items'];
                        $firstItem = $groupItems->first();
                        $isSellerLogistics = ($firstItem?->delivery_provider ?: $firstItem?->delivery_mode ?: $shop?->logistics_type) === 'seller';
                        $groupReleased = $group['visible_lines'];
                    @endphp

                    <article class="vendor-group-card">
                        <header class="vendor-group-header">
                            <div class="shop-identity">
                                <span class="shop-avatar">{{ strtoupper(substr($shop?->name ?? 'B', 0, 1)) }}</span>
                                <div>
                                    <div class="shop-title-row">
                                        <h4>{{ $shop?->name ?? 'Boutique non reliée' }}</h4>
                                        <span class="logistics-badge {{ $isSellerLogistics ? 'seller' : 'ovanie' }}">
                                            {{ $isSellerLogistics ? 'Logistique vendeur' : 'OVANIE Logistics' }}
                                        </span>
                                    </div>
                                    <p>Boutique #{{ $shop?->id ?? '—' }} · {{ $shop?->commune ?? 'Commune non renseignée' }}</p>
                                </div>
                            </div>

                            <div class="vendor-release-state">
                                <span>{{ $groupReleased }}/{{ $groupItems->count() }} ligne(s) transmise(s)</span>
                                <strong>{{ $groupReleased === $groupItems->count() && $groupItems->count() > 0 ? 'Visible par le vendeur' : 'Transmission en attente' }}</strong>
                            </div>
                        </header>

                        <div class="vendor-contact-grid">
                            <div>
                                <span>Vendeur</span>
                                <strong>{{ $seller?->name ?? 'Non identifié' }}</strong>
                            </div>
                            <div>
                                <span>E-mail vendeur</span>
                                <strong>{{ $seller?->email ?? $shop?->business_email ?? '—' }}</strong>
                            </div>
                            <div>
                                <span>Téléphone / WhatsApp</span>
                                <strong>{{ $seller?->phone ?? $shop?->whatsapp ?? $shop?->mm_number ?? '—' }}</strong>
                            </div>
                            <div>
                                <span>Point de retrait</span>
                                <strong>{{ $shop?->commune ?? '—' }}{{ $shop?->district ? ' / ' . $shop->district : '' }}</strong>
                            </div>
                        </div>

                        <div class="vendor-products-list">
                            <div class="vendor-products-head" aria-hidden="true">
                                <span>Produit</span>
                                <span>Quantité</span>
                                <span>Prix unitaire</span>
                                <span>Montant</span>
                                <span>Véhicule planifié</span>
                                <span>Livraison</span>
                                <span>Transmission</span>
                            </div>

                            @forelse($groupItems as $item)
                                @php
                                    $product = $item->product;
                                    $lineTotal = (float) $item->price * (int) $item->quantity;
                                    $deliveryStatus = $item->delivery_status ?: $item->vendor_delivery_status ?: 'pending';
                                    $deliveryLabel = $deliveryStatusLabels[$deliveryStatus] ?? 'En cours de traitement';
                                    $productImage = $product?->thumb_image_url ?: $product?->card_image_url ?: $product?->image_url;
                                    $vehicleCode = strtolower((string) ($item->logistics_vehicle_code ?? ''));
                                    $vehicleLabel = $item->logistics_vehicle_label
                                        ?: ($vehicleLabels[$vehicleCode] ?? 'Non défini');
                                @endphp

                                <div class="vendor-product-entry">
                                    <div class="vendor-product-row">
                                        <div class="product-cell" data-label="Produit">
                                            <div class="product-thumb">
                                                @if($productImage)
                                                    <img src="{{ $productImage }}" alt="{{ $product?->name ?? 'Produit' }}">
                                                @else
                                                    <span>IMG</span>
                                                @endif
                                            </div>
                                            <div>
                                                <strong>{{ $product?->name ?? 'Produit supprimé' }}</strong>
                                                <small>Produit n° {{ $item->product_id ?? '—' }} · Ligne n° {{ $item->id }}</small>
                                            </div>
                                        </div>

                                        <div class="product-value" data-label="Quantité">
                                            <strong>{{ $item->quantity }}</strong>
                                        </div>

                                        <div class="product-value" data-label="Prix unitaire">
                                            <strong>{{ number_format((float) $item->price, 0, ',', ' ') }} FCFA</strong>
                                        </div>

                                        <div class="product-value" data-label="Montant">
                                            <strong>{{ number_format($lineTotal, 0, ',', ' ') }} FCFA</strong>
                                        </div>

                                        <div class="product-value" data-label="Véhicule planifié">
                                            <span class="vehicle-pill">{{ $vehicleLabel }}</span>
                                            <small class="cell-note">Charge groupée de la mission</small>
                                        </div>

                                        <div class="product-value delivery-value" data-label="Livraison">
                                            <span class="status-badge status-{{ $deliveryStatus }}">
                                                {{ $deliveryLabel }}
                                            </span>
                                            @if((float) ($item->delivery_price ?? 0) > 0)
                                                <small class="cell-note">{{ number_format((float) $item->delivery_price, 0, ',', ' ') }} FCFA</small>
                                            @endif
                                        </div>

                                        <div class="product-value transmission-value" data-label="Transmission">
                                            @if($item->vendor_visible_at)
                                                <span class="release-badge released">Transmise</span>
                                                <small class="cell-note">{{ $item->vendor_visible_at->format('d/m/Y H:i') }}</small>
                                            @else
                                                <span class="release-badge waiting">Non transmise</span>
                                            @endif
                                        </div>
                                    </div>

                                    @if(
                                        $item->driver_name || $item->driver_phone || $item->vehicle_plate ||
                                        $item->pickup_photo || $item->delivery_photo || $item->delivery_otp_code ||
                                        $item->vendor_delivery_note || $item->driver_latitude || $item->driver_longitude
                                    )
                                        <details class="tracking-details">
                                            <summary>Afficher le suivi logistique de ce produit</summary>

                                            <div class="tracking-detail-grid">
                                                <div><span>Chauffeur</span><strong>{{ $item->driver_name ?? '—' }}</strong></div>
                                                <div><span>Téléphone du chauffeur</span><strong>{{ $item->driver_phone ?? '—' }}</strong></div>
                                                <div><span>Immatriculation</span><strong>{{ $item->vehicle_plate ?? '—' }}</strong></div>
                                                <div><span>Code de remise</span><strong>{{ $item->delivery_otp_code ?? '—' }}</strong></div>
                                                <div><span>Poids de la ligne</span><strong>{{ number_format((float) ($item->logistics_weight_kg ?? 0), 2, ',', ' ') }} kg</strong></div>
                                                <div><span>Volume de la ligne</span><strong>{{ number_format((float) ($item->logistics_volume_m3 ?? 0), 3, ',', ' ') }} m³</strong></div>
                                            </div>

                                            @if($item->vendor_delivery_note)
                                                <p class="tracking-note"><strong>Note :</strong> {{ $item->vendor_delivery_note }}</p>
                                            @endif

                                            <div class="tracking-media">
                                                @if($item->pickup_photo)
                                                    <a href="{{ route('admin.private-documents.order-item', [$item, 'pickup']) }}" target="_blank" rel="noopener">
                                                        <img src="{{ route('admin.private-documents.order-item', [$item, 'pickup']) }}" alt="Photo de chargement">
                                                        <span>Chargement</span>
                                                    </a>
                                                @endif

                                                @if($item->delivery_photo)
                                                    <a href="{{ route('admin.private-documents.order-item', [$item, 'delivery']) }}" target="_blank" rel="noopener">
                                                        <img src="{{ route('admin.private-documents.order-item', [$item, 'delivery']) }}" alt="Photo de livraison">
                                                        <span>Livraison</span>
                                                    </a>
                                                @endif

                                                @if($item->driver_latitude && $item->driver_longitude)
                                                    <a class="tracking-map-link"
                                                       href="https://waze.com/ul?ll={{ $item->driver_latitude }},{{ $item->driver_longitude }}&navigate=yes"
                                                       target="_blank" rel="noopener">
                                                        Ouvrir la position du chauffeur
                                                    </a>
                                                @endif
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            @empty
                                <div class="empty-cell">Aucun produit rattaché à cette boutique.</div>
                            @endforelse
                        </div>

                        <footer class="vendor-group-footer">
                            <div>
                                <span>Poids total</span>
                                <strong>{{ number_format((float) $group['weight_kg'], 2, ',', ' ') }} kg</strong>
                            </div>
                            <div>
                                <span>Volume total</span>
                                <strong>{{ number_format((float) $group['volume_m3'], 3, ',', ' ') }} m³</strong>
                            </div>
                            <div>
                                <span>Livraison du groupe</span>
                                <strong>{{ number_format((float) $group['delivery_fee'], 0, ',', ' ') }} FCFA</strong>
                            </div>
                            <div class="group-total">
                                <span>Sous-total produits</span>
                                <strong>{{ number_format((float) $group['subtotal'], 0, ',', ' ') }} FCFA</strong>
                            </div>
                        </footer>
                    </article>
                @empty
                    <div class="empty-panel">
                        Aucune boutique n’est rattachée aux lignes de cette commande.
                    </div>
                @endforelse
            </div>
        </section>

        <div class="detail-two-columns">
            <section class="detail-card">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Client</span>
                        <h3>Informations de contact</h3>
                    </div>
                </div>

                <div class="info-list">
                    <div><span>Nom complet</span><strong>{{ $clientName }}</strong></div>
                    <div><span>E-mail</span><strong>{{ $order->client?->email ?? '—' }}</strong></div>
                    <div><span>Téléphone du compte</span><strong>{{ $order->client?->phone ?? '—' }}</strong></div>
                    <div><span>Contact de livraison</span><strong>{{ $order->delivery_recipient_phone ?? $order->phone ?? $order->cod_mobile_number ?? '—' }}</strong></div>
                    <div><span>Destinataire</span><strong>{{ $order->delivery_recipient_name ?? $clientName }}</strong></div>
                    <div><span>N° facture</span><strong>{{ $order->invoice_number ?? '—' }}</strong></div>
                </div>
            </section>

            <section class="detail-card">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Livraison</span>
                        <h3>Adresse du client</h3>
                    </div>
                </div>

                <div class="info-list">
                    <div><span>Adresse</span><strong>{{ $order->delivery_address ?? $order->address ?? '—' }}</strong></div>
                    <div><span>Ville</span><strong>{{ $order->delivery_city ?? '—' }}</strong></div>
                    <div><span>Commune</span><strong>{{ $order->delivery_commune ?? '—' }}</strong></div>
                    <div><span>Quartier</span><strong>{{ $order->delivery_quartier ?? '—' }}</strong></div>
                    <div><span>Zone</span><strong>{{ $order->delivery_zone ?? '—' }}</strong></div>
                    <div><span>Note</span><strong>{{ $order->delivery_note ?? '—' }}</strong></div>
                </div>

                @if($clientLat && $clientLng)
                    <a class="location-link"
                       href="https://waze.com/ul?ll={{ $clientLat }},{{ $clientLng }}&navigate=yes"
                       target="_blank" rel="noopener">
                        <svg viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Zm-8 3a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" /></svg>
                        Ouvrir la position du client
                    </a>
                @endif
            </section>
        </div>

        <div class="detail-two-columns detail-bottom-grid">
            <section class="detail-card payment-card">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Finances</span>
                        <h3>Résumé de la commande</h3>
                    </div>
                </div>

                <div class="payment-progress">
                    <div class="payment-progress-head">
                        <span>Lignes produits payées</span>
                        <strong>{{ $paidLines }}/{{ $order->items->count() }}</strong>
                    </div>
                    @php
                        $paymentPercent = $order->items->count() > 0
                            ? round(($paidLines / $order->items->count()) * 100)
                            : 0;
                    @endphp
                    <div class="progress-track"><span style="width: {{ $paymentPercent }}%"></span></div>
                </div>

                <div class="money-summary">
                    <div><span>Sous-total produits</span><strong>{{ number_format($realSubtotal, 0, ',', ' ') }} FCFA</strong></div>
                    <div><span>Frais de livraison</span><strong>{{ number_format($realDelivery, 0, ',', ' ') }} FCFA</strong></div>
                    @if((float) ($order->discount ?? 0) > 0)
                        <div><span>Réduction</span><strong>- {{ number_format((float) $order->discount, 0, ',', ' ') }} FCFA</strong></div>
                    @endif
                    <div class="money-total"><span>Total commande</span><strong>{{ number_format($realTotal, 0, ',', ' ') }} FCFA</strong></div>
                </div>

                @if($order->payment_proof)
                    <a href="{{ route('admin.orders.payment-proof', $order) }}" target="_blank" rel="noopener" class="proof-link">
                        Voir la preuve de paiement
                    </a>
                @endif
            </section>

            <section class="detail-card status-management-card">
                <div class="section-heading compact">
                    <div>
                        <span class="section-kicker">Administration</span>
                        <h3>Mettre à jour la commande</h3>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.orders.updateStatus', $order) }}" class="status-form">
                    @csrf
                    @method('PUT')

                    <label>
                        <span>Statut de la commande</span>
                        <select name="status">
                            @foreach($statusLabels as $value => $label)
                                <option value="{{ $value }}" @selected($order->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Statut du paiement</span>
                        <select name="payment_status" required>
                            @foreach($paymentLabels as $value => $label)
                                <option value="{{ $value }}" @selected($order->payment_status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <button type="submit" class="btn-save-status">
                        Enregistrer les modifications
                    </button>
                </form>

                @if(in_array($order->status, ['confirmed', 'processing', 'shipped', 'delivered']))
                    <form action="{{ route('admin.orders.confirmDelivery', $order) }}" method="POST" class="confirm-delivery-form">
                        @csrf
                        <button type="submit" class="btn-confirm-delivery"
                                onclick="return confirm('Confirmer la livraison de toutes les lignes de cette commande ?')">
                            Confirmer la livraison complète
                        </button>
                    </form>
                @endif
            </section>
        </div>
    </div>
@endsection
