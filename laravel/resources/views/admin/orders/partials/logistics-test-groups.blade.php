@php
    $internalDeliveryGroups = $order->items
        ->groupBy(function ($item) {
            $shopId = $item->shop_id ?: $item->product?->shop_id ?: 0;
            $provider = $item->delivery_provider ?: 'non_defini';
            $vehicle = $item->logistics_vehicle_code ?: 'non_defini';

            return $shopId . '|' . $provider . '|' . $vehicle;
        });
@endphp

<section class="internal-logistics-test">
    <div class="internal-logistics-head">
        <div>
            <span>Visible uniquement par l’administration</span>
            <h2>Répartition interne vendeurs et logistique</h2>
            <p>
                Utilisez ce bloc pour vérifier qu’une commande multi-vendeurs est correctement divisée.
                Ces informations ne doivent jamais être affichées au client.
            </p>
        </div>
        <strong>{{ $internalDeliveryGroups->count() }} groupe(s)</strong>
    </div>

    <div class="internal-logistics-grid">
        @foreach($internalDeliveryGroups as $groupKey => $groupItems)
            @php
                $firstItem = $groupItems->first();
                $shop = $firstItem?->shop ?: $firstItem?->product?->shop;
                $seller = $shop?->user;
                $provider = $firstItem?->delivery_provider;
                $isSellerDelivery = $provider === 'seller' || $shop?->logistics_type === 'seller';
                $groupWeight = (float) $groupItems->sum('logistics_weight_kg');
                $groupVolume = (float) $groupItems->sum('logistics_volume_m3');
                $groupDeliveryPrice = (float) $groupItems->sum('delivery_price');
                $visibleLines = $groupItems->whereNotNull('vendor_visible_at')->count();
            @endphp

            <article class="internal-logistics-card">
                <div class="internal-logistics-card-top">
                    <div>
                        <span class="group-badge {{ $isSellerDelivery ? 'seller' : 'ovanie' }}">
                            {{ $isSellerDelivery ? 'Logistique vendeur' : 'OVANIE Logistics' }}
                        </span>
                        <h3>#{{ $shop?->id ?? '—' }} — {{ $shop?->name ?? 'Boutique introuvable' }}</h3>
                    </div>
                    <strong>{{ number_format($groupDeliveryPrice, 0, ',', ' ') }} FCFA</strong>
                </div>

                <div class="internal-logistics-meta">
                    <p><span>Compte vendeur</span><strong>{{ $seller?->name ?? '—' }}</strong></p>
                    <p><span>E-mail vendeur</span><strong>{{ $seller?->email ?? '—' }}</strong></p>
                    <p><span>Point de retrait</span><strong>{{ $shop?->commune ?? '—' }} / {{ $shop?->district ?? '—' }}</strong></p>
                    <p><span>Véhicule calculé</span><strong>{{ $firstItem?->logistics_vehicle_label ?: $firstItem?->logistics_vehicle_code ?: 'Non défini' }}</strong></p>
                    <p><span>Poids du groupe</span><strong>{{ number_format($groupWeight, 2, ',', ' ') }} kg</strong></p>
                    <p><span>Volume du groupe</span><strong>{{ number_format($groupVolume, 3, ',', ' ') }} m³</strong></p>
                    <p><span>Visibilité vendeur</span><strong>{{ $visibleLines }}/{{ $groupItems->count() }} ligne(s) libérée(s)</strong></p>
                    <p><span>Statut logistique</span><strong>{{ $firstItem?->delivery_status ?: 'pending' }}</strong></p>
                </div>

                <div class="internal-logistics-products">
                    <strong>Produits du groupe</strong>
                    <ul>
                        @foreach($groupItems as $item)
                            <li>
                                <span>{{ $item->product?->name ?? 'Produit supprimé' }}</span>
                                <small>
                                    Qté {{ $item->quantity }} · ID produit #{{ $item->product_id }} · ligne #{{ $item->id }}
                                </small>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </article>
        @endforeach
    </div>
</section>

<style>
    .internal-logistics-test {
        margin: 18px 0 22px;
        padding: 20px;
        border: 1px solid #dbe5f1;
        border-radius: 16px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
    }
    .internal-logistics-head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 18px;
        margin-bottom: 15px;
    }
    .internal-logistics-head span {
        display: block;
        color: #f97316;
        font-size: 10px;
        font-weight: 900;
        letter-spacing: .1em;
        text-transform: uppercase;
    }
    .internal-logistics-head h2 {
        margin: 4px 0 4px;
        color: #071f4f;
        font-size: 21px;
    }
    .internal-logistics-head p {
        max-width: 760px;
        margin: 0;
        color: #64748b;
        font-size: 13px;
    }
    .internal-logistics-head > strong {
        flex: 0 0 auto;
        padding: 7px 10px;
        border-radius: 999px;
        color: #0b5fe0;
        background: #eaf2ff;
        font-size: 12px;
    }
    .internal-logistics-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .internal-logistics-card {
        min-width: 0;
        padding: 15px;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        background: #fbfdff;
    }
    .internal-logistics-card-top {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #e7edf5;
    }
    .internal-logistics-card-top h3 {
        margin: 7px 0 0;
        color: #071f4f;
        font-size: 15px;
    }
    .internal-logistics-card-top > strong {
        color: #f15b00;
        white-space: nowrap;
    }
    .group-badge {
        display: inline-flex !important;
        padding: 5px 8px;
        border-radius: 999px;
        font-size: 10px !important;
        letter-spacing: 0 !important;
        text-transform: none !important;
    }
    .group-badge.ovanie { color: #0757c9; background: #eaf2ff; }
    .group-badge.seller { color: #9a4b00; background: #fff0df; }
    .internal-logistics-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px 12px;
        padding: 12px 0;
    }
    .internal-logistics-meta p { margin: 0; }
    .internal-logistics-meta span,
    .internal-logistics-meta strong {
        display: block;
    }
    .internal-logistics-meta span {
        color: #64748b;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
    }
    .internal-logistics-meta strong {
        margin-top: 2px;
        color: #1e293b;
        font-size: 12px;
        word-break: break-word;
    }
    .internal-logistics-products > strong {
        color: #334155;
        font-size: 12px;
    }
    .internal-logistics-products ul {
        margin: 7px 0 0;
        padding: 0;
        list-style: none;
    }
    .internal-logistics-products li {
        padding: 8px 0;
        border-top: 1px dashed #d9e2ec;
    }
    .internal-logistics-products li span,
    .internal-logistics-products li small { display: block; }
    .internal-logistics-products li span { color: #071f4f; font-weight: 750; }
    .internal-logistics-products li small { margin-top: 2px; color: #64748b; }
    @media (max-width: 1050px) {
        .internal-logistics-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 640px) {
        .internal-logistics-head { flex-direction: column; }
        .internal-logistics-meta { grid-template-columns: 1fr; }
    }
</style>
