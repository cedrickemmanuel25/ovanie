@extends('admin.layouts.app')

@section('title', 'Detail optimisation panier | Admin OVANIE')
@section('page-title', 'Detail optimisation panier')

@section('content')
@php($meta = $optimization->meta ?? [])
<div style="display:grid;gap:18px;max-width:1100px;">
    <a href="{{ route('admin.logistics.cart-optimizations.index') }}" style="color:#0f172a;">Retour aux optimisations</a>

    <section style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:18px;">
        <h2 style="margin:0 0 14px;font-size:20px;">Synthese</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
            <p><strong>Commande</strong><br>{{ $optimization->order_id ? '#'.$optimization->order_id : '-' }}</p>
            <p><strong>Panier</strong><br>{{ $optimization->cart_id ? '#'.$optimization->cart_id : '-' }}</p>
            <p><strong>Date</strong><br>{{ optional($optimization->created_at)->format('d/m/Y H:i') }}</p>
            <p><strong>Raison</strong><br>{{ $optimization->reason ?: '-' }}</p>
            <p><strong>Frais avant</strong><br>{{ number_format((float) $optimization->original_delivery_fee, 0, ',', ' ') }} FCFA</p>
            <p><strong>Frais apres</strong><br>{{ number_format((float) $optimization->optimized_delivery_fee, 0, ',', ' ') }} FCFA</p>
            <p><strong>Economie</strong><br>{{ number_format((float) $optimization->savings, 0, ',', ' ') }} FCFA</p>
            <p><strong>Scenario</strong><br>{{ $meta['scenario'] ?? '-' }}</p>
        </div>
    </section>

    <section style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:18px;">
        <h2 style="margin:0 0 14px;font-size:20px;">Produit et point de vente</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px;">
            <div>
                <h3 style="margin:0 0 8px;">Original</h3>
                <p><strong>Produit</strong><br>{{ $optimization->originalProduct?->name ?: '-' }}</p>
                <p><strong>Point</strong><br>{{ $optimization->originalShop?->name ?: '-' }}</p>
                <p><strong>Commune</strong><br>{{ $optimization->originalShop?->commune ?: '-' }}</p>
                <p><strong>Distance avant</strong><br>{{ $meta['original_distance_km'] ?? '-' }}</p>
            </div>
            <div>
                <h3 style="margin:0 0 8px;">Final</h3>
                <p><strong>Produit</strong><br>{{ $optimization->fulfillmentProduct?->name ?: '-' }}</p>
                <p><strong>Point</strong><br>{{ $optimization->fulfillmentShop?->name ?: '-' }}</p>
                <p><strong>Commune</strong><br>{{ $optimization->fulfillmentShop?->commune ?: '-' }}</p>
                <p><strong>Distance apres</strong><br>{{ $meta['optimized_distance_km'] ?? '-' }}</p>
            </div>
        </div>
    </section>

    <section style="background:#fff;border:1px solid #e5e7eb;border-radius:8px;padding:18px;">
        <h2 style="margin:0 0 14px;font-size:20px;">Details JSON</h2>
        <pre style="white-space:pre-wrap;background:#0f172a;color:#e2e8f0;border-radius:8px;padding:14px;overflow:auto;">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
    </section>
</div>
@endsection
